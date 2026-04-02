<?php
namespace WASP\Users;

use WASP\Helpers\HTML;
use WASP\Interfaces\Fields;

/**
 * User Meta
 *
 * @since 1.0.0
 */
abstract class User_Meta implements Fields
{

	/**
	 * User
	 * @access public
	 * @var object
	 *
	 * @since 1.0.0
	 */
	public $user_id;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		if ( ! function_exists( 'wp_get_current_user' ) )
			include_once( ABSPATH .'wp-includes/pluggable.php' );

		$current_user = wp_get_current_user();
		$user_id = ( isset( $current_user->ID ) ) ? (int) $current_user->ID : 0;
		$this->user_id = $user_id;

		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'user_new_form', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
		add_action( 'user_register', array( $this, 'save' ) );
	}

	/**
	 * Render the content of the section
	 * @see WASP\Helpers\HTML::field()
	 *
	 * @since 1.0.0
	 */
	public function render( $user = null )
	{
		if ( $user instanceof \WP_User )
			$this->user_id = (int) $user->ID;

		$fields = $this->fields();
		wp_nonce_field( 'wasp_user_meta_save', 'wasp_user_meta_nonce' );
	?>
		<table class="form-table">
	<?php
		foreach ( $fields as $key => $data ) :
			$value = ( isset( $this->user_id ) && $this->user_id > 0 && isset( $data['meta'] ) && '' !== (string) $data['meta'] )
						? get_user_meta( $this->user_id, $data['meta'], true )
						: null;

			$this->html( $data, $value );
		endforeach;
	?>
		</table>
	<?php
	}

	/**
	 * Form fields to render
	 * @param string $args 	This parameter is described in class WASP\Helpers\HTML::field() method
	 * @param string $value	This parameter is described in class WASP\Helpers\HTML::field() method
	 *
	 * @since 1.0.0
	 */
	private function html( $data, $value )
	{
		$screen = get_current_screen();
		$value 	= ( 'user' != $screen->id ) ? $value : null;
		$meta = isset( $data['meta'] ) ? (string) $data['meta'] : '';
		$label = isset( $data['label'] ) ? (string) $data['label'] : '';
	?>
		<tr>
			<th>
				<label for="<?php echo esc_attr( $meta ) ?>"><?php echo esc_html( $label ) ?></label>
			</th>
			<td>
				<?php HTML::field( $data, $value ); ?>
			</td>
		</tr>
	<?php
	}

	/**
	 * Save the user meta
	 * @param object $user_id
	 *
	 * @since 1.0.0
	 */
	public function save( $user_id )
	{
		if ( ! current_user_can( 'edit_user', $user_id ) )
			return;

		if ( ! isset( $_POST['wasp_user_meta_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['wasp_user_meta_nonce'] ) ),
				'wasp_user_meta_save'
			)
		)
			return;

		$fields = $this->fields();

		foreach ( $fields as $key => $field ) :
			if ( ! HTML::should_store_field( $field ) )
				continue;

			$meta = $field['meta'];
			if ( ! isset( $_POST[$meta] ) ) :
				delete_user_meta( $user_id, $meta );
				continue;
			endif;

			$sanitized = HTML::sanitize_value( $field, $_POST[$meta] );
			if ( HTML::is_empty_value( $sanitized ) ) :
				delete_user_meta( $user_id, $meta );
			else :
				update_user_meta( $user_id, $meta, $sanitized );
			endif;
		endforeach;
	}
}
