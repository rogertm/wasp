<?php
namespace WASP\Helpers;

use WASP\Helpers\Enqueue;

/**
 * Helper. HTML Form Field
 *
 * @since 1.0.0
 */
class HTML
{

	/**
	 * Form fields to render
	 * @param array $args {
	 *		Array of arguments, supports the following keys:
	 * 		@type string $label 	Field label.
	 * 		@type string $meta 		Field meta. 'key' stored in the database
	 * 		@type string $type 		Field type.
	 * 								Default 'text'.
	 * 								Supported values: 'button', 'checkbox', 'color', 'content',
	 * 								'date', 'datetime-local', 'email', 'file', 'hidden', 'html',
	 * 								'media', 'month', 'nonce' 'number', 'password', 'radio', 'range',
	 * 								'select', 'submit', 'tel', 'text', 'textarea', 'time', 'url', 'week'.
	 * 		@type array $multiple	Array used to define the values of field type 'radio' or 'select'.
	 * 								$multiple = array(
	 * 									'option_1' => 'Label 1',
	 * 									'option_2' => 'Label 2',
	 * 									'option_3' => 'Label 3',
	 * 									...
	 * 								)
	 * 		@type array $attr 		Array of HTML attributes
	 * 								$attr = array(
	 * 									'min' 		=> '1',
	 * 									'max' 		=> '999',
	 * 									'step' 		=> '3.14',
	 * 									...
	 * 								)
	 * 		@type mixed $default 	Default value before to save the data in the database.
	 * }
	 * @param string $value 		Value retrieved from database
	 *
	 * @since 1.0.0
	 * @since 1.0.1 				Added $args['default']
	 * @since 1.0.1 				Added $args['attr']
	 * @since 1.1.0 				Added $args['html']
	 * @since 1.1.0 				Added $args['description']
	 */
	public static function field( $args, $value )
	{
		$defaults = array(
			'type'		=> 'text',
			'label'		=> null,
			'meta'		=> null,
			'default'	=> null,
			'multiple'	=> null,
			'html'		=> null,
			'description' => null,
			'attr'		=> null
		);
		$args = wp_parse_args( $args, $defaults );

		$type_class = sanitize_html_class( (string) $args['type'] );

		echo '<div class="wasp-field field-'. esc_attr( $type_class ) .'" style="margin-bottom: .5rem">';
			self::title( $args );
			self::paragraph( $args );
			self::html( $args );
			self::default( $args, $value );
			self::content( $args, $value );
			self::textarea( $args, $value );
			self::checkbox( $args, $value );
			self::radio( $args, $value );
			self::select( $args, $value );
			self::media( $args, $value );
			self::file( $args, $value );
			self::nonce( $args );
			if ( isset( $args['description'] ) && ! empty( $args['description'] ) )
				echo '<p class="description">'. wp_kses_post( $args['description'] ) .'</p>';
		echo '</div>';
	}

	/**
	 * Sanitization by field type.
	 * @param array $args
	 * @param mixed $value
	 * @return mixed
	 *
	 * @since 1.1.1
	 */
	public static function sanitize_value( $args, $value )
	{
		$args = wp_parse_args(
			$args,
			array(
				'type' => 'text',
			)
		);

		$type = (string) $args['type'];
		$value = wp_unslash( $value );

		switch ( $type ) :
			case 'checkbox':
				return ( ! empty( $value ) ) ? 1 : 0;

			case 'file':
				if ( '' === $value || null === $value )
					return '';

				return absint( $value );

			case 'media':
				return implode( ',', self::sanitize_attachment_ids( $value ) );

			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

			case 'content':
			case 'html':
				return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';

			case 'url':
				return is_scalar( $value ) ? esc_url_raw( (string) $value ) : '';

			case 'email':
				return is_scalar( $value ) ? sanitize_email( (string) $value ) : '';

			case 'number':
			case 'range':
				if ( ! is_scalar( $value ) )
					return '';

				$value = trim( (string) $value );
				if ( '' === $value )
					return '';

				if ( is_numeric( $value ) )
					return 0 + $value;

				return sanitize_text_field( $value );

			case 'select':
				if ( is_array( $value ) )
					return self::sanitize_recursive_text( $value );

				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';

			default:
				if ( is_array( $value ) )
					return self::sanitize_recursive_text( $value );

				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		endswitch;
	}

	/**
	 * Determine if a sanitized value should be considered empty for persistence.
	 * @param mixed $value
	 * @return bool
	 *
	 * @since 1.1.1
	 */
	public static function is_empty_value( $value )
	{
		if ( is_array( $value ) ) :
			foreach ( $value as $item ) :
				if ( ! self::is_empty_value( $item ) )
					return false;
			endforeach;

			return true;
		endif;

		return null === $value || '' === $value;
	}

	/**
	 * Whether this field should be persisted in db.
	 * @param array $args
	 * @return bool
	 *
	 * @since 1.1.1
	 */
	public static function should_store_field( $args )
	{
		$type = isset( $args['type'] ) ? (string) $args['type'] : 'text';
		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';

		if ( '' === $meta )
			return false;

		return ! in_array(
			$type,
			array( 'title', 'paragraph', 'button', 'submit', 'nonce', 'html' ),
			true
		);
	}

	/**
	 * Title
	 * @param array $args
	 *
	 * @since 1.0.0
	 */
	public static function title( $args )
	{
		if ( 'title' != $args['type'] )
			return;
		?>
			<h3><?php echo esc_html( (string) $args['label'] ) ?></h3>
		<?php
	}

	/**
	 * Paragraph
	 * @param array $args
	 *
	 * @since 1.0.1
	 */
	public static function paragraph( $args )
	{
		if ( 'paragraph' != $args['type'] )
			return;
		?>
			<p><?php echo esc_html( (string) $args['label'] ) ?></p>
		<?php
	}

	/**
	 * HTML
	 * @param array $args
	 *
	 * @since 1.1.0
	 */
	public static function html( $args )
	{
		if ( 'html' != $args['type'] )
			return;

		if ( ! empty( $args['html'] ) )
			echo wp_kses_post( $args['html'] );
	}

	/**
	 * Defaults
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function default( $args, $value )
	{
		// Supported input types
		$types = array(
			'button',
			'color',
			'date',
			'datetime-local',
			'email',
			'hidden',
			'month',
			'number',
			'password',
			'range',
			'submit',
			'tel',
			'text',
			'time',
			'url',
			'week'
		);
		if ( ! in_array( $args['type'], $types, true ) )
			return;

		$default = $value ?? ( ( isset( $args['default'] ) ) ? $args['default'] : null );
		$label = isset( $args['label'] ) ? (string) $args['label'] : '';
		$field_value = ( 'button' !== $args['type'] && 'submit' !== $args['type'] ) ? $default : $label;
		$field_value = is_scalar( $field_value ) ? (string) $field_value : '';

		$class = 'regular-text form-control';
		if ( 'button' === $args['type'] )
			$class = 'button btn btn-secondary';
		if ( 'submit' === $args['type'] )
			$class = 'button btn btn-primary';

		$no_label = array(
			'button',
			'submit',
			'hidden'
		);

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
	?>

	<?php if ( ! in_array( $args['type'], $no_label, true )  ) : ?>
		<p>
			<label
				for="<?php echo esc_attr( $meta ) ?>"
				class="description form-label"
			>
				<?php echo esc_html( $label ) ?>
				<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
			</label>
		</p>
	<?php endif ?>
		<input
			id="<?php echo esc_attr( $meta ) ?>"
			class="<?php echo esc_attr( $class ) ?>"
			type="<?php echo esc_attr( (string) $args['type'] ) ?>"
			name="<?php echo esc_attr( $meta ) ?>"
			value="<?php echo esc_attr( $field_value ) ?>"
			<?php self::attr( $args['attr'] ) ?>
		>
	<?php
	}

	/**
	 * Content
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function content( $args, $value )
	{
		if ( 'content' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$editor_value = $value ?? ( ( isset( $args['default'] ) ) ? $args['default'] : null ) ?? '';
		$editor_value = is_scalar( $editor_value ) ? (string) $editor_value : '';
	?>
		<p>
			<label
				for="<?php echo esc_attr( $meta ) ?>"
				class="description"
			>
				<?php echo esc_html( (string) $args['label'] ) ?>
				<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
			</label>
		</p>
	<?php
		$settings = array(
			'media_buttons'	=> false,
			'textarea_rows'	=> 7,
			'teeny'			=> true,
			'quicktags'		=> false,
			'tinymce'		=> array(
				'resize'				=> false,
				'wordpress_adv_hidden'	=> false,
				'add_unload_trigger'	=> false,
				'statusbar'				=> false,
				'wp_autoresize_on'		=> false,
				'toolbar1'				=> 'bold,italic,underline,|,bullist,numlist,|,alignleft,aligncenter,alignright,|,link,unlink,|,undo,redo',
			),
		);
		wp_editor( $editor_value, $meta, $settings );
	}

	/**
	 * Textarea
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function textarea( $args, $value )
	{
		if ( 'textarea' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$textarea_value = $value ?? ( ( isset( $args['default'] ) ) ? $args['default'] : null ) ?? '';
		$textarea_value = is_scalar( $textarea_value ) ? (string) $textarea_value : '';
	?>
		<p>
			<label
				for="<?php echo esc_attr( $meta ) ?>"
				class="description form-label"
			>
				<?php echo esc_html( (string) $args['label'] ) ?>
				<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
			</label>
		</p>
		<textarea
			id="<?php echo esc_attr( $meta ) ?>"
			class="regular-text form-control mb-3"
			name="<?php echo esc_attr( $meta ) ?>"
			cols="30"
			rows="5"
			<?php static::attr( $args['attr'] ) ?>
		><?php echo esc_textarea( $textarea_value ) ?></textarea>
	<?php
	}

	/**
	 * Media
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function media( $args, $value )
	{
		if ( 'media' != $args['type'] )
			return;

		Enqueue::media_upload( true );
		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$ids = self::sanitize_attachment_ids( $value );
		$ids_csv = implode( ',', $ids );
	?>
		<p><label for="<?php echo esc_attr( $meta ) ?>" class="description"><?php echo esc_html( (string) $args['label'] ) ?></label></p>
		<div id="media-uploader-<?php echo esc_attr( $meta ) ?>" class="wasp-media-uploader" data-btn="insert-media-btn-<?php echo esc_attr( $meta ) ?>">
			<div id="insert-media-wrapper-<?php echo esc_attr( $meta ) ?>" class="insert-media-wrapper" style="display: flex; justify-content: flex-start;">
			<?php
			if ( ! empty( $ids ) ) :
				foreach ( $ids as $id ) :
					$image_url = wp_get_attachment_image_url( $id );
			?>
				<div id="thumbnail-<?php echo esc_attr( $meta .'-'. $id ) ?>" class="img-wrapper" style="display: flex; flex-direction: column; margin: .5rem">
					<img src="<?php echo esc_url( (string) $image_url ) ?>">
					<small class="img-remover" data-remove="thumbnail-<?php echo esc_attr( $meta .'-'. $id ) ?>" data-thumbnail-id="<?php echo esc_attr( (string) $id ) ?>" style="color:#a00; cursor: pointer;"><?php esc_html_e( 'Remove', 'wasp' ) ?></small>
				</div>
			<?php
				endforeach;
			endif;
			?>
			</div>
			<input id="insert-media-input-<?php echo esc_attr( $meta ) ?>" class="insert-media-input regular-text mb-3" type="hidden" name="<?php echo esc_attr( $meta ) ?>" value="<?php echo esc_attr( $ids_csv ) ?>">
			<button id="insert-media-btn-<?php echo esc_attr( $meta ) ?>" class="button insert-media-button" type="button" data-input="insert-media-input-<?php echo esc_attr( $meta ) ?>" data-wrapper="insert-media-wrapper-<?php echo esc_attr( $meta ) ?>">
				<?php esc_html_e( 'Upload images', 'wasp' ) ?>
			</button>
		</div>
	<?php
	}

	/**
	 * File
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function file( $args, $value )
	{
		if ( 'file' != $args['type'] )
			return;

		Enqueue::file_upload( true );
		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$file_id = absint( $value );
		$attach_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
	?>
		<p>
			<label
				for="insert-file-url-<?php echo esc_attr( $meta ) ?>"
				class="description form-label"
			>
				<?php echo esc_html( (string) $args['label'] ) ?>
				<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
			</label>
		</p>
		<div id="file-uploader-<?php echo esc_attr( $meta ) ?>" class="wasp-file-uploader">
			<input id="insert-file-input-<?php echo esc_attr( $meta ) ?>" class="insert-file-input mb-3" type="hidden" name="<?php echo esc_attr( $meta ) ?>" value="<?php echo esc_attr( (string) $file_id ) ?>">
			<input id="insert-file-url-<?php echo esc_attr( $meta ) ?>" class="insert-file-url mb-3 form-control" type="url" value="<?php echo esc_url( (string) $attach_url ) ?>">
			<button class="button insert-file-button btn btn-secondary" type="button" data-input="insert-file-input-<?php echo esc_attr( $meta ) ?>" data-url="insert-file-url-<?php echo esc_attr( $meta ) ?>">
				<?php esc_html_e( 'Upload file', 'wasp' ) ?>
			</button>
			<button class="button clear-file-button btn btn-secondary" type="button" data-input="insert-file-input-<?php echo esc_attr( $meta ) ?>" data-url="insert-file-url-<?php echo esc_attr( $meta ) ?>">
				<?php esc_html_e( 'Clear', 'wasp' ) ?>
			</button>
		</div>
	<?php
	}

	/**
	 * Checkbox
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function checkbox( $args, $value )
	{
		if ( 'checkbox' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$label = isset( $args['label'] ) ? (string) $args['label'] : '';
		$value = $value ?? ( ( 'checked' == $args['default'] ) ? 1 : null );
		?>
			<div class="form-check form-switch">
				<input
					id="<?php echo esc_attr( $meta ) ?>"
					class="form-check-input"
					type="checkbox"
					name="<?php echo esc_attr( $meta ) ?>"
					value="1"
					role="switch"
					<?php checked( $value, 1 ) ?>
					<?php static::attr( $args['attr'] ) ?>
				>
				<label for="<?php echo esc_attr( $meta ) ?>" class="form-check-label">
					<?php echo esc_html( $label ) ?>
					<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
				</label>
			</div>
		<?php
	}

	/**
	 * Checkbox
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function radio( $args, $value )
	{
		if ( 'radio' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$current = $value ?? ( ( isset( $args['default'] ) ) ? $args['default'] : null );
		$current = is_scalar( $current ) ? (string) $current : '';

		if ( is_array( $args['multiple'] ) ) :
			$i = 0;
			foreach ( $args['multiple'] as $k => $v ) :
		?>
		<div class="form-check">
			<input
				id="<?php echo esc_attr( $meta .'-'. $i ) ?>"
				class="form-check-input"
				type="radio"
				name="<?php echo esc_attr( $meta ) ?>"
				value="<?php echo esc_attr( (string) $k ) ?>"
				<?php checked( (string) $k, $current ) ?>
				<?php static::attr( $args['attr'] ) ?>
			>
			<label for="<?php echo esc_attr( $meta .'-'. $i ) ?>" class="form-check-label">
				<?php echo esc_html( (string) $v ) ?>
			</label>
		</div>
		<?php
				$i++;
			endforeach;
		endif;
	}

	/**
	 * Select
	 * @param array $args 	Array of arguments
	 * @param string $value Default value
	 *
	 * @since 1.0.0
	 */
	public static function select( $args, $value )
	{
		if ( 'select' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$label = isset( $args['label'] ) ? (string) $args['label'] : '';
		$option = ( ! is_array( $args['multiple'] ) )
					? __( 'No data available', 'wasp' )
					: __( '&mdash; Select an option &mdash;', 'wasp' );

		$name = ( isset( $args['attr']['multiple'] ) )
					? $meta .'[]'
					: $meta;
	?>
		<p>
			<label
				for="<?php echo esc_attr( $meta ) ?>"
				class="description form-label"
			>
				<?php echo esc_html( $label ) ?>
				<?php if ( self::is_required( $args ) ) : ?><small class="required">*</small><?php endif ?>
			</label>
		</p>
		<select
			id="<?php echo esc_attr( $meta ) ?>"
			class="form-select"
			name="<?php echo esc_attr( $name ) ?>"
			<?php static::attr( $args['attr'] ) ?>
		>
			<option value=""><?php echo wp_kses_post( $option ) ?></option>
			<?php
			if ( is_array( $args['multiple'] ) ) :
				$current = $value ?? ( ( isset( $args['default'] ) ) ? $args['default'] : null );
				$current_array = is_array( $current ) ? array_map( 'strval', $current ) : null;

				foreach ( $args['multiple'] as $k => $v ) :
					if ( is_array( $current_array ) ) :
						$selected = in_array( (string) $k, $current_array, true ) ? 'selected="selected"' : '';
					else :
						$current_scalar = is_scalar( $current ) ? (string) $current : '';
						$selected = selected( (string) $k, $current_scalar, false );
					endif;
			?>
			<option value="<?php echo esc_attr( (string) $k ) ?>" <?php echo $selected ?>><?php echo esc_html( (string) $v ) ?></option>
			<?php
				endforeach;
			endif;
			?>
		</select>
	<?php
	}

	/**
	 * Nonce
	 * @param array $args 	Array of arguments
	 *
	 * @since 1.0.1
	 */
	public static function nonce( $args )
	{
		if ( 'nonce' != $args['type'] )
			return;

		$meta = isset( $args['meta'] ) ? (string) $args['meta'] : '';
		$action = isset( $args['default'] ) ? (string) $args['default'] : '-1';
	?>
		<input
			id="<?php echo esc_attr( $meta ) ?>"
			type="hidden"
			name="<?php echo esc_attr( $meta ) ?>"
			value="<?php echo esc_attr( wp_create_nonce( $action ) ) ?>"
		>
	<?php
	}

	/**
	 * Process the $args['attr'] var
	 * @param array $attr
	 * @return string
	 *
	 * @since 1.0.1
	 */
	private static function attr( $attr )
	{
		if ( ! is_array( $attr ) )
			return;

		unset( $attr['id'] );
		unset( $attr['name'] );

		foreach ( $attr as $k => $v ) :
			$k = preg_replace( '/[^a-zA-Z0-9_:-]/', '', (string) $k );
			if ( '' === $k )
				continue;

			if ( is_bool( $v ) ) :
				if ( $v )
					echo esc_attr( $k ) .' ';

				continue;
			endif;

			if ( is_array( $v ) || is_object( $v ) )
				continue;

			echo esc_attr( $k ) .'="'. esc_attr( (string) $v ) .'" ';
		endforeach;
	}

	/**
	 * Sanitize nested arrays with sanitize_text_field.
	 * @param array $value
	 * @return array
	 *
	 * @since 1.1.1
	 */
	private static function sanitize_recursive_text( $value )
	{
		if ( ! is_array( $value ) )
			return array();

		$result = array();

		foreach ( $value as $k => $v ) :
			if ( is_array( $v ) ) :
				$nested = self::sanitize_recursive_text( $v );
				if ( ! empty( $nested ) )
					$result[ $k ] = $nested;

				continue;
			endif;

			if ( ! is_scalar( $v ) )
				continue;

			$sanitized = sanitize_text_field( (string) $v );
			if ( '' === $sanitized )
				continue;

			$result[ $k ] = $sanitized;
		endforeach;

		return $result;
	}

	/**
	 * Normalize media input (csv or array) to list of attachment IDs.
	 * @param mixed $value
	 * @return array<int>
	 *
	 * @since 1.1.1
	 */
	private static function sanitize_attachment_ids( $value )
	{
		if ( is_array( $value ) ) :
			$raw = $value;
		elseif ( is_scalar( $value ) ) :
			$raw = explode( ',', (string) $value );
		else :
			$raw = array();
		endif;

		$ids = array();
		foreach ( $raw as $id ) :
			$id = absint( $id );
			if ( $id > 0 )
				$ids[] = $id;
		endforeach;

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a field has required attribute.
	 * @param array $args
	 * @return bool
	 *
	 * @since 1.1.1
	 */
	private static function is_required( $args )
	{
		return isset( $args['attr'] ) && is_array( $args['attr'] ) && isset( $args['attr']['required'] );
	}
}
