<?php
namespace WASP\Terms;

use WASP\Helpers\HTML;
use WASP\Interfaces\Fields;

/**
 * Term Meta
 *
 * @since 1.0.0
 */
abstract class Term_Meta implements Fields
{

	/**
	 * Taxonomy
	 * @access public
	 * @var string 	Required
	 *
	 * @since 1.0.0
	 */
	public $taxonomy;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Initializes hooks
	 *
	 * @since 1.0.0
	 */
	public function init()
	{
		add_action( $this->taxonomy .'_add_form_fields', array( $this, 'render' ), 10, 2 );
		add_action( $this->taxonomy .'_edit_form_fields', array( $this, 'render' ), 10, 2 );
		add_action( 'create_'. $this->taxonomy, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'edited_'. $this->taxonomy, array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Render the content of the meta box.
	 * @param object $term 	Current term.
	 *
	 * @since 1.0.0
	 */
	public function render( $term )
	{
		wp_nonce_field(
			'wasp_term_meta_'. $this->taxonomy,
			'wasp_term_meta_nonce_'. $this->taxonomy
		);

		$fields = $this->fields();

		foreach ( $fields as $key => $data ) :
			$value = ( isset( $term->term_id ) && isset( $data['meta'] ) && '' !== (string) $data['meta'] )
							? get_term_meta( $term->term_id, $data['meta'], true )
							: null;

			$this->html( $data, $value );
		endforeach;
	}

	/**
	 * Form fields to render
	 * @param string $args 	This parameter is described in class WASP\Helpers\HTML::field() method
	 * @param string $value	This parameter is described in class WASP\Helpers\HTML::field() method
	 *
	 * @since 1.0.0
	 */
	private function html( $args, $value )
	{
		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$label_text = isset( $args['label'] ) ? (string) $args['label'] : '';

		$tr 	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '<tr class="form-field term-order-wrap">'
					: '<div class="form-field">';
		$tr_end	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '</tr>'
					: '</div>';

		$th 	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '<th scope="row">'
					: null;
		$th_end	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '</th>'
					: null;

		$td 	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '<td>'
					: null;
		$td_end	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '</td>'
					: null;

		$label 	= ( $this->taxonomy .'_edit_form_fields' == current_filter() )
					? '<p><label for="'. esc_attr( $meta ) .'" class="description">'. esc_html( $label_text ) .'</label></p>'
					: null;

		echo $tr;
			echo $th;
				echo $label;
			echo $th_end;
			echo $td;

			HTML::field( $args, $value );

			echo $td_end;
		echo $tr_end;
	}

	/**
	 * Save the Term Meta
	 * @param int $term_id 	Current term.
	 *
	 * @since 1.0.0
	 */
	public function save_meta( $term_id )
	{
		$nonce_key = 'wasp_term_meta_nonce_'. $this->taxonomy;
		if ( ! isset( $_POST[ $nonce_key ] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ),
				'wasp_term_meta_'. $this->taxonomy
			)
		)
			return;

		$taxonomy = get_taxonomy( $this->taxonomy );
		if ( $taxonomy && isset( $taxonomy->cap->edit_terms ) && ! current_user_can( $taxonomy->cap->edit_terms ) )
			return;

		$fields = $this->fields();

		foreach ( $fields as $key => $field ) :
			if ( ! HTML::should_store_field( $field ) )
				continue;

			$meta = $field['meta'];
			if ( ! isset( $_POST[$meta] ) ) :
				delete_term_meta( $term_id, $meta );
				continue;
			endif;

			$sanitized = HTML::sanitize_value( $field, $_POST[$meta] );
			if ( HTML::is_empty_value( $sanitized ) ) :
				delete_term_meta( $term_id, $meta );
			else :
				update_term_meta( $term_id, $meta, $sanitized );
			endif;
		endforeach;
	}
}
