<?php
namespace WpDatabaseHelper;

class WpField {
	public $name = 'WpDatabaseHelper_field';
	private $version;
	public $id;
	public $args = [ 
		'object'         => '',
		'field'          => 'input',
		'attribute'      => [ 
			'name'  => '',
			'id'    => '',
			'class' => [],
			// 'type' => 'text', 
			// 'placeholder' => '...',
			// 'value'       => '',
			// 'required'    => '',
			// 'checked'  => false, // true|false
		],
		'value'          => '', // current field
		'suggest'        => '',
		'before'         => '<div class=___default_wrap>',
		'after'          => '</div>',
		'wrap_class'     => [],
		'note'           => '',
		'label'          => '',
		'label_position' => 'before',
		'options'        => [
			// 1 => 1,
			// 2 => 2,
			// 3 => 3,
		], // for select, radio, checkbox
		'term_select'    => [
			// 'taxonomy'       => 'age-categories',
			// 'option_value'   => 'term_id',
			// 'option_display' => 'name',
		],
		'post_select'    => [
			// 'post_type'      => 'club',
			// 'option_value'   => 'ID',
			// 'option_display' => 'post_title',
		],
		'show_copy'  	=> true,
		'show_copy_key'  => false,
	];

	function __construct() {
		$this->version = $this->getVersion();
	}

	private function getVersion() {
		$composerFile = __DIR__ . '/../composer.json';
		if ( file_exists( $composerFile ) ) {
			$composerData = json_decode( file_get_contents( $composerFile ), true );
			return $composerData['version'] ?? '0.0.0';
		}
		return '0.0.0';
	}

	function enqueue() {
		$plugin_url     = plugins_url( '', __DIR__ ) . "/assets";
		$enqueue_assets = function () use ($plugin_url) {
			// Check if the script is already enqueued to avoid adding it multiple times
			if ( wp_script_is( 'wpdatabasehelper-field-js', 'enqueued' ) ) {
				return;
			}
			wp_enqueue_style(
				'wpdatabasehelper-field-css',
				$plugin_url . "/css/field.css",
				[],
				$this->version,
				'all'
			);

			wp_enqueue_script(
				'wpdatabasehelper-field-js',
				$plugin_url . "/js/field.js",
				[],
				$this->version,
				true
			);

			// Add inline script only once
			wp_add_inline_script(
				'wpdatabasehelper-field-js',
				'const wpdatabasehelper_field_js = ' . json_encode(
					array(
						'ajax_url'     => admin_url( 'admin-ajax.php' ),
						'nonce'        => wp_create_nonce( 'wpdatabasehelper_field_js' ),
						'script_debug' => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ),
						'text'         => [ 
							'upload'         => __( 'Upload' ),
							'use_this_media' => __( 'Choose image' ),
						],
					)
				),
				'before'
			);
		};

		if ( did_action( 'admin_enqueue_scripts' ) ) {
			$enqueue_assets();
		} else {
			add_action( 'admin_enqueue_scripts', $enqueue_assets );
		}
	}

	function setup_args( $args ) {
		// parse args
		$_attribute = wp_parse_args(
			$args['attribute'], 
			[
				'type' => 'text',
				'value' => '',
				'class' => [],
			]
		);
		$this->args = wp_parse_args(
			$args, 
			$this->args
		);
		$this->args['attribute'] = $_attribute;
		$this->args['attribute']['class'] = (array)$this->args['attribute']['class'];
		

		// make sure $args['value'] and $args['attribute][value]
		if ( !isset( $this->args['attribute']['value'] ) ) {
			$this->args['attribute']['value'] = '';
		}
		if ( !isset( $this->args['value'] ) ) {
			$this->args['value'] = '';
		}

		if ( !$this->args['value'] and $this->args['attribute']['value'] ) {
			$this->args['value'] = $this->args['attribute']['value'];
		}
		if ( !$this->args['attribute']['value'] and $this->args['value'] ) {
			$this->args['attribute']['value'] = $this->args['value'];
		}

		// id
		$this->id = $this->name . "_" . wp_rand();
		if ( $this->args['attribute']['id'] ?? "" ) {
			$this->id = $this->args['attribute']['id'];
		}
		$this->args['attribute']['id'] = $this->id;

		// class
		$this->args['attribute']['class'][] = $this->name;
		if ( ( $this->args['field'] ?? '' ) == 'input' ) {
			if ( $this->args['attribute']['type'] != 'button' ) {
				$this->args['attribute']['class'][] = 'regular-text';
			}
		}

		// options term_select
		if ( !empty( $this->args['term_select'] ) ) {
			$options               = $this->get_options_term_select();
			$this->args['options'] = $options;
		}

		// option post_select
		if ( !empty( $this->args['post_select'] ) ) {
			$options               = $this->get_options_post_select();
			$this->args['options'] = $options;
		}

		// textarea
		if ( $this->args['field'] == 'textarea' ) {
			$this->args['attribute']['rows'] = 5;
		}

		// only for checkbox
		if ( $this->args['attribute']['type'] == 'checkbox' ) {

			// make sure at leat 1 options
			if ( empty( $this->args['options'] ) ) {
				$this->args['options'] = [ 
					"on" => "on"
				];
			}

			// nếu nhiều hơn 2 giá trị thì cho nó là 1 mảng.
			if ( count( $this->args['options'] ) > 1 ) {
				$this->args['attribute']['name'] .= '[]';
			}
		}

		// textarea
		if ( ( $this->args['field'] ?? '' ) == 'textarea' ) {
			$this->args['attribute']['rows'] = 3;
		}

		// show_copy_key
		if (
			in_array( $this->args['field'], [ 'media' ] ) or
			in_array( $this->args['attribute']['type'], [ 'button', 'file', 'checkbox', 'radio', 'file', 'hidden' ] )
		) {
			$this->args['show_copy'] = false;
		}
	}

	function init_field() {
		$this->enqueue();
		$field = $this->args['field'];
		$type  = $this->args['attribute']['type'] ?? '';
		ob_start();
		?>
		<?php echo wp_kses_post( $this->args['before'] ); ?>
		<?php
		$wrap_class = implode( ' ',
			array_merge(
				(array) $this->args['wrap_class'],
				[ 
					$this->name . '_wrap',
					'type-' . $type
				]
			)
		);

		?>
		<div class="<?= esc_attr( $wrap_class ); ?>">
			<?php if ( $this->args['label_position'] == 'before' ) echo $this->get_label(); ?>
			<?php
			if ( method_exists( $this, $field ) ) {
				echo $this->{$field}();
			} else {
				echo "method is not exists: $field";
			}
			echo $this->get_copy();
			?>
			<?php if ( $this->args['label_position'] == 'after' ) echo $this->get_label(); ?>
		</div>
		<?php echo $this->get_note(); ?>
		<?php echo $this->get_suggest(); ?>
		<?php echo wp_kses_post( $this->args['after'] ); ?>
		<?php
		return ob_get_clean();
	}

	function get_attribute($attr_override = false) {
		ob_start();

		$args = $this->args['attribute'];
		if($attr_override){
			$args = $attr_override;
		}

		foreach ( $args as $key => $value ) {
			$value = implode( " ", (array) $value );
			echo esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}
		return ob_get_clean();
	}

	function select() {
		ob_start();
		// echo "<pre>"; print_r($this->args); echo "</pre>";
		// var_dump($this->args->value);
		?>
		<select <?= $this->get_attribute(); ?>>
			<?php
			foreach ( $this->args['options'] as $key => $value ) {
				$selected = in_array( $key, (array) $this->args['value'] ) ? 'selected' : "";
				?>
				<option <?= esc_attr( $selected ) ?> value="<?= esc_attr( $key ); ?>">
					<?= esc_attr( $value ); ?>
				</option>
				<?php
			}
			?>
		</select>
		<?php
		return ob_get_clean();
	}

	function textarea() {
		ob_start();
		?>
		<textarea <?= $this->get_attribute(); ?>><?= esc_attr( $this->args['value'] ) ?></textarea>
		<?php
		return ob_get_clean();
	}

	function input() {
		$type = $this->args['attribute']['type'] ?? "text";
		if ( method_exists( $this, "input_" . $type ) ) {
			return $this->{"input_" . $type}();
		}
		return $this->input_text();
	}

	function input_text() {
		ob_start();
		?>
		<input <?php echo $this->get_attribute(); ?>>
		<?php
		return ob_get_clean();
	}

	function input_range(){
		ob_start();
		?>
		<div class="form_field_range">
			<div class="input_range_field">
				<?php echo $this->input_text(); ?>
			</div>
			<div class="input_range_value">
				<?php echo $this->args['attribute']['value'] ?? '' ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	function input_radio(){
		ob_start();
		?>
		<div class="form_field_radio form_field_flex">
			<?php 
			foreach ((array)$this->args['options'] as $key => $value) {
				$attr_override = $this->args['attribute'];
				$attr_override['value'] = $key;
				$attr_override['id'] .= $key;
				
				if( ($this->args['value'] ?? '') == $key){
					$attr_override['checked'] = 'checked';
				}else{
					if(isset($attr_override['checked'])){
						unset($attr_override['checked']);
					}
				}
				?>
				<div class="item">
					<input <?= $this->get_attribute($attr_override); ?>>
					<label for="<?= esc_attr( $attr_override['id'] ) ?>" style="vertical-align: middle;">
						<?= esc_attr( $value ) ?>
					</label>
				</div>
				<?php
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	function input_checkbox() {
		ob_start();
		?>
		<div class="form_field_checkbox form_field_flex">
			<?php 
			// echo "<pre>"; print_r($this->args['attribute']['name']); echo "</pre>";
			// echo "<pre>"; print_r($this->args); echo "</pre>";
			$field_value = (array)$this->args['value'];
			// var_dump($field_value);
			foreach ((array)$this->args['options'] as $key => $value) {
				$attr_override = $this->args['attribute'];
				$attr_override['value'] = $key;
				$attr_override['id'] .= $key;

				if(in_array($key, $field_value)){
					$attr_override['checked'] = 'checked';
				}else{
					if(isset($attr_override['checked'])){
						unset($attr_override['checked']);
					}
				}
				
				?>
				<div class="item">
					<input <?= $this->get_attribute($attr_override); ?>>
					<label for="<?= esc_attr( $attr_override['id'] ) ?>" style="vertical-align: middle;">
						<?= esc_attr( $value ) ?>
					</label>
				</div>
				<?php
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/* function input_checkbox2() {
		// remove attribute checked if false
		if ( isset( $this->args['attribute']['checked'] ) and !$this->args['attribute']['checked'] ) {
			unset( $this->args['attribute']['checked'] );
		}
		?>
		<input <?= $this->get_attribute(); ?>>
		<?php
	} */

	function media() {
		
		$value = $this->args['attribute']['value'] ?? '';
		wp_enqueue_media();
		$this->args['attribute']['type'] = 'hidden';
		// echo "<pre>"; print_r($this->args); echo "</pre>";
		ob_start();
		$image_url = $value ? wp_get_attachment_url( $value ) : '';
		?>
		<div class="form_field_media">
			<input <?php echo $this->get_attribute(); ?> />
			<div class="xpreview">
				<img class='image-preview' src='<?php echo esc_url( $image_url ); ?>'
					style='display: <?php echo $image_url ? 'block' : 'none'; ?>' />
			</div>
			<button type='button' class='button hepperMeta-media-upload'><?= __( 'Add' ); ?>
			</button>
			<button type='button' class='button hepperMeta-media-remove'><?= __( 'Delete' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_copy() {
		if(!$this->args['show_copy']){
			return;
		}

		$name = $this->args['attribute']['name'] ?? '';
		ob_start();
		$classes = implode( " ", [ 
			$this->name . "_click_to_copy",
			$this->name . "_name",
			$this->args['show_copy_key'] ? 'show_copy_key' : ''
		] );
		$text    = $this->args['show_copy_key'] ? $name : __( 'Copy' );

		?>
		<span class="<?= esc_attr( $classes ) ?>" data-text="<?= esc_attr( $name ) ?>">
			<?= esc_attr( $text ) ?>
		</span>
		<?php
		return ob_get_clean();
	}

	function get_suggest() {
		if ( !$this->args['suggest'] ) {
			return;
		}
		$this->args['suggest'] = (array) $this->args['suggest'];
		ob_start();
		foreach ( (array) $this->args['suggest'] as $key => $suggest ) {
			?>
			<span class="<?= esc_attr( $this->name ) ?>_suggest">
				<small>
					<strong>*<?= _ex( 'Suggested', 'custom headers' ) ?>: </strong>
				</small>
				<span class="<?= esc_attr( $this->name ) ?>_click_to_copy" data-text="<?= esc_attr( $suggest ); ?>">
					<?= esc_attr( $suggest ); ?>
				</span>
			</span>
			<?php
		}
		return ob_get_clean();
	}

	function get_note() {
		if ( !$this->args['note'] ) {
			return;
		}
		$this->args['note'] = (array) $this->args['note'];
		ob_start();
		foreach ( (array) $this->args['note'] as $key => $note ) {
			?>
			<small class="<?= esc_attr( $this->name ) ?>_note">
				<strong>*<?= __( 'Note' ) ?> 			<?= ( $key ) ? $key : ""; ?>:</strong>
				<?= wp_kses_post( $note ) ?>.
			</small>
			<?php
		}
		return ob_get_clean();
	}

	function get_label() {
		ob_start();
		if ( !$this->args['label'] ) return;
		?>
		<label for="<?= $this->id; ?>">
			<?php echo $this->args['label'] ?? "" ?>
		</label>
		<?php
		return ob_get_clean();
	}

	function get_options_term_select(){
		$options = [ '' => __( 'Select' ) ];
		$terms   = get_terms( [ 
			'taxonomy'   => $this->args['term_select']['taxonomy'],
			'hide_empty' => 'false',
		] );
		foreach ( $terms as $key => $term ) {

			$_key_   = $this->args['term_select']['option_value'] ?? 'term_id';
			$_value_ = $this->args['term_select']['option_value'] ?? 'name';

			$_key             = $term->{$_key_};
			$_value           = $term->{$_value_};
			$options[ $_key ] = $_value;
		}
		return $options;
	}

	function get_options_post_select(){
		$options = [ '' => __( 'Select' ) ];
		$args    = [ 
			'post_type'      => [ $this->args['post_select']['post_type'] ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'name',
			'order'          => 'asc',
		];

		$__the_query = new \WP_Query( $args );
		if ( $__the_query->have_posts() ) {
			while ( $__the_query->have_posts() ) :
				$__the_query->the_post();
				global $post;

				$_key_   = $this->args['post_select']['option_value'] ?? 'ID';
				$_value_ = $this->args['post_select']['option_value'] ?? 'post_title';

				$_key             = $post->{$_key_};
				$_value           = $post->{$_value_};
				$options[ $_key ] = $_value;
			endwhile;
			wp_reset_postdata();
		}
		return $options;
	}
}