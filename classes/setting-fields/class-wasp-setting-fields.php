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
	 * Construct
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action( 'admin_menu', array( $this, 'register_setting' ) );
	}

	/**
	 * Register Setting
	 *
	 * @since 1.0.0
	 */
	public function register_setting()
	{
		register_setting(
			$this->option_group,
			$this->option_name,
			array( 'sanitize_callback' => array( $this, 'sanitize_options' ) )
		);

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
	 * @param string $meta 	Meta key stored in '$this->option_name' register in the data base option table.
	 *
	 * @since 1.0.0
	 */
	public function get_option( $meta )
	{
		$option = get_option( $this->option_name );

		if ( ! is_array( $option ) || ! array_key_exists( $meta, $option ) )
			return;

		return $option[$meta];
	}

	/**
	 * Sanitize Callback
	 *
	 * @since 1.0.0
	 */
	public function sanitize_options()
	{
		/**
		 * Filters the Options Input
		 * @param array 	Array of key => value
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'wasp_setting_fields_options_input', $this->validate() );
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
	 *
	 * @since 1.0.0
	 */
	private function validate()
	{
		$fields = $this->merged_fields();

		foreach ( $fields as $key => $value ) :
			if ( isset( $value['meta'] ) )
				$input[$value['meta']] = ( isset( $_POST[$value['meta']] ) && null !== $_POST[$value['meta']] )
											? ( is_array( $_POST[$value['meta']] ) )
												? $_POST[$value['meta']]
												: stripslashes( trim( $_POST[$value['meta']] ) )
											: '';
		endforeach;

		add_settings_error( 'wasp-update', 'wasp', __( 'Setting Updated', 'wasp' ), 'success' );

		return $input;
	}

	/**
	 * Merge fields from every child class
	 * @return array
	 *
	 * @since 1.0.2
	 */
	private function merged_fields()
	{
		$fields = $this->fields();

		$result = array();
		foreach ( get_declared_classes() as $class ) :
			if ( is_subclass_of( $class, __CLASS__ ) ) :
				$instance = new $class;
				$result = array_merge( $result, $instance->fields() );
			endif;
		endforeach;

		return $result;
	}
}
