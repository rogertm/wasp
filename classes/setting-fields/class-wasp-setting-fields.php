<?php
namespace WASP\Setting_Fields;

use WASP\Helpers\HTML;
use WASP\Interfaces\Fields;

/**
 * Setting Fields
 *
 * @since 1.0.0
 */
abstract class Setting_Fields implements Fields
{

	/**
	 * Option group
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $option_group;

	/**
	 * Option name
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $option_name;

	/**
	 * HTML section id
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $section_id;

	/**
	 * Section title
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $section_title;

	/**
	 * Page slug
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $slug;

	/**
	 * HTML field id
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $field_id;

	/**
	 * Field Title
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $field_title;

	/**
	 * Registered settings callbacks by "group|option".
	 * @var array<string,bool>
	 *
	 * @since 1.1.1
	 */
	private static $registered_settings = array();

	/**
	 * All instantiated classes grouped by option name.
	 * @var array<string,array<string,self>>
	 *
	 * @since 1.1.1
	 */
	private static $instances_by_option_name = array();

	/**
	 * Tracks success notices already added to avoid duplicates.
	 * @var array<string,bool>
	 *
	 * @since 1.1.1
	 */
	private static $success_notice_added = array();

	/**
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Register Setting
	 *
	 * @since 1.0.0
	 */
	public function register_setting()
	{
		$this->register_instance();

		if ( empty( $this->option_group ) || empty( $this->option_name ) )
			return;

		$setting_key = $this->option_group .'|'. $this->option_name;
		if ( ! isset( self::$registered_settings[ $setting_key ] ) ) :
			register_setting(
				$this->option_group,
				$this->option_name,
				array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
			);

			self::$registered_settings[ $setting_key ] = true;
		endif;

		if ( empty( $this->slug ) || empty( $this->section_id ) || empty( $this->field_id ) )
			return;

		add_settings_section(
			$this->slug .'-'. $this->section_id,
			$this->section_title,
			'__return_false',
			$this->slug
		);

		add_settings_field(
			$this->slug .'-'. $this->field_id,
			$this->field_title,
			$this->callback(),
			$this->slug,
			$this->slug .'-'. $this->section_id
		);
	}

	/**
	 * Main configuration function
	 * Returns only values from fields declared by the current class.
	 * @param string $meta 	Meta key stored in '$this->option_name'
	 *
	 * @since 1.0.0
	 */
	public function get_option( $meta )
	{
		$meta = (string) $meta;
		if ( '' === $meta )
			return;

		$allowed = $this->get_own_fields_index();
		if ( ! isset( $allowed[ $meta ] ) )
			return;

		$option = get_option( $this->option_name );

		if ( ! is_array( $option ) || ! array_key_exists( $meta, $option ) )
			return;

		return $option[ $meta ];
	}

	/**
	 * Sanitize Callback
	 * @param mixed $input
	 * @return array
	 *
	 * @since 1.0.0
	 */
	public function sanitize_options( $input = array() )
	{
		if ( ! is_array( $input ) )
			$input = array();

		$sanitized = $this->validate( $input );

		$notice_key = sanitize_key( (string) $this->option_name );
		if ( ! isset( self::$success_notice_added[ $notice_key ] ) ) :
			add_settings_error(
				'wasp-update',
				'wasp_'. $notice_key,
				__( 'Setting Updated', 'wasp' ),
				'success'
			);
			self::$success_notice_added[ $notice_key ] = true;
		endif;

		$sanitized = apply_filters( 'wasp_setting_fields_options_input', $sanitized );

		return apply_filters(
			'wasp_setting_fields_options_input_'. $this->option_name,
			$sanitized,
			$this
		);
	}

	/**
	 * Fills the field with the desired form inputs.
	 * @return callable
	 *
	 * @since 1.0.0
	 */
	private function callback()
	{
		return array( $this, 'render' );
	}

	/**
	 * Render the content of the section
	 * @see WASP\Helpers\HTML::field()
	 *
	 * @since 1.0.0
	 */
	public function render()
	{
		$fields = $this->fields();

		foreach ( $fields as $key => $data ) :
			$value = $this->get_option( $data['meta'] ?? null );
			HTML::field( $data, $value );
		endforeach;
	}

	/**
	 * Process validation fields
	 * Sanitizes known fields only for all classes sharing this option_name.
	 *
	 * @param array $input
	 * @return array
	 *
	 * @since 1.1.1
	 */
	private function validate( $input )
	{
		$stored = get_option( $this->option_name, array() );
		if ( ! is_array( $stored ) )
			$stored = array();

		$fields = $this->get_registered_fields();
		$input = $this->normalize_submitted_input( $input, $fields );

		foreach ( $fields as $meta => $field ) :
			if ( ! array_key_exists( $meta, $input ) ) :
				$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';
				if ( 'checkbox' === $type )
					unset( $stored[ $meta ] );

				continue;
			endif;

			$sanitized = HTML::sanitize_value( $field, $input[ $meta ] );
			if ( HTML::is_empty_value( $sanitized ) ) :
				unset( $stored[ $meta ] );
			else :
				$stored[ $meta ] = $sanitized;
			endif;
		endforeach;

		return $stored;
	}

	/**
	 * Normalize submitted data for both WASP legacy flat inputs and WP array input.
	 *
	 * Legacy format:
	 *   <input name="field_key" ...>
	 * WordPress format:
	 *   <input name="option_name[field_key]" ...>
	 *
	 * @param mixed $input
	 * @param array<string,array> $fields
	 * @return array
	 *
	 * @since 1.1.1
	 */
	private function normalize_submitted_input( $input, $fields )
	{
		$normalized = is_array( $input ) ? $input : array();

		$has_known_keys = false;
		foreach ( $fields as $meta => $field ) :
			if ( array_key_exists( $meta, $normalized ) ) :
				$has_known_keys = true;
				break;
			endif;
		endforeach;

		// Legacy fallback: current generated stubs render plain input names.
		if ( ! $has_known_keys ) :
			foreach ( $fields as $meta => $field ) :
				if ( isset( $_POST[ $meta ] ) )
					$normalized[ $meta ] = $_POST[ $meta ];
			endforeach;
		endif;

		return $normalized;
	}

	/**
	 * Registers current instance in option registry.
	 *
	 * @since 1.1.1
	 */
	private function register_instance()
	{
		if ( empty( $this->option_name ) )
			return;

		if ( ! isset( self::$instances_by_option_name[ $this->option_name ] ) )
			self::$instances_by_option_name[ $this->option_name ] = array();

		self::$instances_by_option_name[ $this->option_name ][ spl_object_hash( $this ) ] = $this;
	}

	/**
	 * Returns all registered fields for current option_name.
	 * @return array<string,array>
	 *
	 * @since 1.1.1
	 */
	private function get_registered_fields()
	{
		$result = array();

		if ( empty( $this->option_name ) )
			return $result;

		$instances = self::$instances_by_option_name[ $this->option_name ] ?? array();
		if ( empty( $instances ) )
			$instances = array( spl_object_hash( $this ) => $this );

		foreach ( $instances as $instance ) :
			if ( ! $instance instanceof self )
				continue;

			$fields = $instance->fields();
			if ( ! is_array( $fields ) )
				continue;

			foreach ( $fields as $field ) :
				if ( ! is_array( $field ) || ! HTML::should_store_field( $field ) )
					continue;

				$meta = isset( $field['meta'] ) ? (string) $field['meta'] : '';
				if ( '' === $meta )
					continue;

				$result[ $meta ] = $field;
			endforeach;
		endforeach;

		return $result;
	}

	/**
	 * Returns allowed meta keys for the current class only.
	 * @return array<string,bool>
	 *
	 * @since 1.1.1
	 */
	private function get_own_fields_index()
	{
		$result = array();
		$fields = $this->fields();

		if ( ! is_array( $fields ) )
			return $result;

		foreach ( $fields as $field ) :
			if ( ! is_array( $field ) || ! HTML::should_store_field( $field ) )
				continue;

			$meta = isset( $field['meta'] ) ? (string) $field['meta'] : '';
			if ( '' === $meta )
				continue;

			$result[ $meta ] = true;
		endforeach;

		return $result;
	}
}
