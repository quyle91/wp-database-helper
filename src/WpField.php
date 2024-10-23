<?php
namespace WpDatabaseHelper;

class WpField {
	public $name = 'WpDatabaseHelper_field';
	private $version;
	public $id;
	public $args = [ 
		// adminz
		'object'         => '',

		// 
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
		'value'          => '', // meta_value here
		'suggest'        => '',
		'before'         => '<div class=___default_wrap>', // default is div to break line
		'after'          => '</div>',
		'wrap_class'     => [],
		'note'           => '',
		'label'          => '',
		'label_position' => 'before',
		'options'        => [
			// 1 => 1,
			// 2 => 2,
			// 3 => 3,
		],
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
		'selected'       => '', // meta_value, only for select dropdown
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
				'value' => ''
			]
		);
		$this->args = wp_parse_args(
			$args, 
			$this->args
		);
		$this->args['attribute'] = $_attribute;

		// id
		$this->id = $this->name . "_" . wp_rand();
		if ( $this->args['attribute']['id'] ?? "" ) {
			$this->id = $this->args['attribute']['id'];
		}
		$this->args['attribute']['id'] = $this->id;

		// value on attribute: check if wrong position of value param
		if ( !$this->args['attribute']['value'] and $this->args['value'] ) {
			$this->args['attribute']['value'] = $this->args['value'];
		}

		// options term_select
		if ( !empty( $this->args['term_select'] ) ) {
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
			$this->args['options'] = $options;
		}

		// option post_select
		if ( !empty( $this->args['post_select'] ) ) {
			$options = ['' => __('Select')];
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

					$_key_   = $this->args['term_select']['option_value'] ?? 'ID';
					$_value_ = $this->args['term_select']['option_value'] ?? 'post_title';

					$_key             = $post->{$_key_};
					$_value           = $post->{$_value_};
					$options[ $_key ] = $_value;
				endwhile;
				wp_reset_postdata();
			}
			$this->args['options'] = $options;
		}

		// textarea
		if ( $this->args['field'] == 'textarea' ) {
			$this->args['attribute']['rows'] = 5;
		}

		// only for select dropdown
		// nếu là select: chỉ rõ selected là gì
		if ( ( $this->args['field'] ?? '' ) == 'select' ) {
			if(!$this->args['selected']){
				$this->args['selected'] = $this->args['value'];
			}
		}

		if ( ( $this->args['attribute']['type'] ?? '' ) == 'radio' ) {
			$this->args['selected'] = $this->args['value'];
		}

		// only for checkbox
		// default value for checkbox = on
		if (  $this->args['attribute']['type'] == 'checkbox' )  {
			if ( !$this->args['attribute']['value'] ) {
				$this->args['attribute']['value'] = 'on';
			}
		}
		
		// nếu là checkbox: chỉ rõ có checked hay ko
		if ( $this->args['attribute']['type'] == 'checkbox') {
			if($this->args['value']){
				if($this->args['value'] == $this->args['attribute']['value']){
					$this->args['attribute']['checked'] = true;
				}
			}
		}

		// textarea
		if ( ( $this->args['field'] ?? '' ) == 'textarea' ) {
			$this->args['attribute']['rows'] = 3;
		}

		// label to after
		if ( $this->args['attribute']['type'] == 'checkbox') {
			$this->args['label_position'] = 'after';
		}

		// echo "<pre>"; print_r($this->args); echo "</pre>";
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

	function get_attribute() {
		ob_start();

		// for merge classes
		$this->args['attribute']['class'][] = $this->name;
		$this->args['attribute']['class'][] = 'regular-text';

		foreach ( $this->args['attribute'] as $key => $value ) {
			$value = implode( " ", (array) $value );
			echo esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		return ob_get_clean();
	}

	function input_radio(){
		ob_start();
		?>
		<div>
			<?php 
			foreach ((array)$this->args['options'] as $key => $value) {
				$id = wp_rand();
				?>
				<input 
					value="<?= esc_attr($key) ?>"
					id="<?= esc_attr($id) ?>" 
					type="radio" 
					name="<?= esc_attr($this->args['attribute']['name']); ?>"
					<?php if(($this->args['selected'] ?? '') == $key) echo 'checked'; ?>
					>
				<label for="<?= esc_attr( $id ) ?>" style="vertical-align: middle;">
					<?= esc_attr($value) ?>
				</label>
				<?php
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	function select() {
		ob_start();
		?>
		<select <?= $this->get_attribute(); ?>>
			<?php
			foreach ( $this->args['options'] as $key => $value ) {
				$selected = in_array( $key, (array) $this->args['selected'] ) ? 'selected' : "";
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

	function input_checkbox() {

		// remove attribute checked if false
		if ( isset( $this->args['attribute']['checked'] ) and !$this->args['attribute']['checked'] ) {
			unset( $this->args['attribute']['checked'] );
		}
		?>
		<input <?= $this->get_attribute(); ?>>
		<?php
	}

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
					style='max-width: 100px; display: <?php echo $image_url ? 'block' : 'none'; ?>' />
			</div>
			<button type='button' class='button hepperMeta-media-upload'><?= __( 'Add' ); ?>
			</button>
			<button type='button' class='button hepperMeta-media-remove'><?= __( 'Delete' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_copy() {

		// checkbox
		if ( in_array( $this->args['attribute']['type'] ?? '', [ 'checkbox', 'button', 'hidden' ] ) ) {
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
			<small class="<?= esc_attr( $this->name ) ?>_suggest">
				<strong>*<?= _ex( 'Suggested', 'custom headers' ) ?>: </strong>
				<span class="<?= esc_attr( $this->name ) ?>_click_to_copy" data-text="<?= esc_attr( $suggest ); ?>">
					<?= esc_attr( $suggest ); ?>
				</span>
			</small>
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
}