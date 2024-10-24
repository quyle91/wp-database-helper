<?php
namespace WpDatabaseHelper;

class WpMeta {
	private $version;
	private static $name = 'WpDatabaseHelper_meta';
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
			// Return early if the script is already enqueued
			if ( wp_script_is( 'wpdatabasehelper-meta-js', 'enqueued' ) ) {
				return;
			}

			wp_enqueue_style(
				'wpdatabasehelper-meta-css',
				$plugin_url . "/css/meta.css",
				[],
				$this->version,
				'all'
			);

			wp_enqueue_script(
				'wpdatabasehelper-meta-js',
				$plugin_url . "/js/meta.js",
				[],
				$this->version,
				true
			);

			wp_add_inline_script(
				'wpdatabasehelper-meta-js',
				'const wpdatabasehelper_meta_js = ' . json_encode(
					array(
						'ajax_url'     => admin_url( 'admin-ajax.php' ),
						'nonce'        => wp_create_nonce( 'wpdatabasehelper_meta_js' ),
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

	public $post_type;
	public $metabox_label;
	public $meta_fields;
	public $register_post_meta;
	public $admin_post_columns;
	public $admin_post_metabox;
	public $quick_edit_post;

	function init($args){

		// copy args to properties
		foreach ((array)$args as $key => $value) {
			$this->$key = $value;
		}
	}

	function init_meta() {
		add_action('init', [$this, 'register_post_meta']);
		add_action('admin_init', [$this, 'admin_post_columns']);
		add_action('admin_init', [$this, 'quick_edit_post']);
		add_action('admin_init', [$this, 'metabox']);
		add_action( "wp_ajax_wpmeta_edit__", [$this, 'wpmeta_edit__'] );
	}

	function wpmeta_edit__() {
		if ( !wp_verify_nonce( $_POST['nonce'], 'wpdatabasehelper_meta_js' ) ) exit;

		error_log(json_encode($_POST));

		update_post_meta(
			esc_attr($_POST['post_id']),
			esc_attr($_POST['meta_key']),
			esc_attr($_POST['meta_value']),
		);

		// find a field_data
		$field_data = [];
		foreach ((array)$this->meta_fields as $key => $value) {
			if($value['meta_key'] == $_POST['meta_key']){
				$field_data = $value;
				continue;
			}
		}

		wp_send_json_success( 
			$this->quick_edit_value(
				$field_data, 
				$_POST['post_id']
			)
		);
		wp_die();
	}

	function parse_args( $args ) {

		$default = [ 
			'meta_key'      => '',
			'label'         => '',
			'admin_column'  => true, // default = true
			'field_classes' => [], // ['full_width']
			'quick_edit'    => true,
			'field'         => 'input', // select, input, media
			'options'       => [], // [key=>value, key2=>value2]
			// 'callback'         => false, // can be function(){return 'x';}
			// 'post_type_select' => false, // post, page
			// 'user_select'      => false, // true
			'attribute'     => [],
		];

		$return = wp_parse_args( $args, $default );
		// echo "<pre>"; print_r($return); echo "</pre>";die;
		return $return;
	}

	function register_post_meta() {

		if(!$this->register_post_meta){
			return;
		}

		foreach ((array)$this->meta_fields as $key => $value) {
			register_post_meta( $this->post_type, $value['meta_key'], array(
				'show_in_rest'      => false,
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => false,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				}
			) );
		}
	}

	function admin_post_columns() {
		if ( !$this->admin_post_columns ) {
			return;
		}

		add_filter( 'manage_' . $this->post_type . '_posts_columns', function ($columns) {
			$insert = [];
			foreach ( (array) $this->meta_fields as $key => $value ) {
				$args = $this->parse_args($value);
				if($args['admin_column']){
					$insert[ $value['meta_key'] ] = esc_html( $args['label'] ?? $value['meta_key'] );
				}
			}
			$first_column = array_slice( $columns, 0, 2, true );
			$last_column  = array_slice( $columns, 2, null, true );
			$columns      = $first_column + $insert + $last_column;
			return $columns;
		} );

		add_action( 'manage_' . $this->post_type . '_posts_custom_column', function ($column, $post_id) {
			foreach ((array)$this->meta_fields as $key => $value) {
				if($value['meta_key'] == $column){
					echo $this->quick_edit_field( $value, $post_id);
				}
			}
		}, 10, 2 );
	}

	function quick_edit_value($field_data, $post_id){
		$args       = $this->parse_args( $field_data );
		$meta_value = get_post_meta( $post_id, $field_data['meta_key'], true );
		ob_start();
		if ( $meta_value ) {
			if ( $args['field'] == 'media' ) {
				echo wp_get_attachment_image(
					$meta_value,
					'thumbnail',
					false,
					[ 
						'style' => 'width: 50px; height: auto;',
					]
				);
			} elseif ( !empty( $args['post_select'] ) ) {
				echo $this->get_admin_column_post( $meta_value );
			} elseif ( !empty( $args['term_select'] ) ) {
				echo $this->get_admin_column_term( $meta_value, $args );
			} else {
				echo esc_attr( $meta_value );
			}
		} else {
			echo "--";
		}
		return ob_get_clean();
	}

	function quick_edit_field( $field_data, $post_id ){
		$args       = $this->parse_args( $field_data );
		$meta_value = get_post_meta( $post_id, $field_data['meta_key'], true );
		ob_start();
		?>
		<div 
			data-meta_key="<?= esc_attr( $field_data['meta_key']); ?>"
			data-post_id="<?= esc_attr($post_id) ?>"
			class="<?= esc_attr(self::$name)?>_quick_edit">
			<div class="quick_edit_value">
				<?php echo $this->quick_edit_value( $field_data, $post_id ); ?>
			</div>
			<div class="quick_edit_field hidden">
				<?php echo $this->init_field( $args, $meta_value ); ?>
			</div>
			<button class="quick_edit_icon button hidden" type="button">
				<?= __('Edit') ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	function quick_edit_post() {
		/* Bởi vì quick edit được load bằng js, nên wordpress ko cung cấp param $post_id, 
			  vì vậy trong quick_edit_custom_box truyền value = ''
			  value được lấy từ js trong add_inline_data
			  field media cũng ko cần update lại nếu ko thực sự quan trọng */

		/* add_action( 'quick_edit_custom_box', function ($column_name, $_post_type)  {
			foreach ( (array)$this->meta_fields as $key=>$value ) {
				$args = $this->parse_args( $value );
				if ( $args['quick_edit'] and $value['meta_key'] == $column_name and $_post_type == $this->post_type ) {
					?>
					<fieldset class="custom-fieldset inline-edit-col-left">
						<div class="inline-edit-col <?= esc_attr( self::$name ) ?>-meta-box-container">
							<label>
								<span class="title">
									<?= esc_attr( $args['label'] ) ?>
								</span>
								<span class="input-text-wrap">
									<?php
									if ( $args['label'] ?? '' ) {
										$args['label'] = '';
									}
									$args['wrap_class'] = 'full_width';
									echo $this->init_field( $args, false );
									?>
								</span>
							</label>
						</div>
					</fieldset>
					<?php
				}
			}
		}, 10, 2 );

		add_action( 'add_inline_data', function ($post){
			$this->enqueue(); // necessary for debug
			foreach ( (array)$this->meta_fields as $key=>$value ) {
				$args = $this->parse_args( $value );
				if ( $args['quick_edit'] ) {
					?>
					<?php // Keep it as 1 line ?>
					<div class="<?= esc_attr( $value['meta_key'] ) ?>"><?= esc_attr( get_post_meta( $post->ID, $value['meta_key'], true ) ) ?></div>
					<?php
				}
			}
		}, 10, 1 );

		add_action( 'save_post', function ($post_id){
			if ( $this->post_type != get_post_type( $post_id ) ) {
				return;
			}

			// only for quick edit
			if ( !isset( $_POST['_inline_edit'] ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( !current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// Khác biệt so với metabox là nó chỉ lưu lại một số field, ko phải toàn bộ
			// nên phải check isset $_POST
			foreach ( (array)$this->meta_fields as $key=>$value ) {
				if(isset($_POST[$value['meta_key']])){
					$args = $this->parse_args( $value );
					if ( $args['quick_edit'] ) {
						$_value = $_POST[ $value['meta_key'] ] ?? '';
						$new_value = sanitize_text_field( $_value );
						if ( $args['field'] == 'textarea' ) {
							$new_value = sanitize_textarea_field( $_value );
						}
						update_post_meta( $post_id, $value['meta_key'], $new_value );
					}
				}
			}

		}, 10, 2 ); */
	}

	function metabox( ) {

		add_action( 'add_meta_boxes', function (){
			add_meta_box(
				sanitize_title( $this->metabox_label ), // ID of the meta box
				$this->metabox_label, // Title of the meta box
				function ($post) {
					wp_nonce_field( 'save_information_metabox', 'information_metabox_nonce' );
					?>
					<div class="<?= esc_attr( self::$name ) ?>-meta-box-container">
						<div class="grid">
							<?php
								foreach ( $this->meta_fields as $value ) {
									$args  = $this->parse_args( $value );
									?>
									<div class="item <?= implode( " ", $args['field_classes'] ) ?>">
										<?php
										echo $this->init_field( 
											$args, 
											get_post_meta( $post->ID, $args['meta_key'], true )
										);
										?>
									</div>
									<?php 
								}
								?>
						</div>
						<div class="footer">
							<small>
								Version: <?= esc_attr( $this->version ) ?>
							</small>
						</div>
					</div>
				<?php
				},
				$this->post_type
			);
		} );

		add_action( 'save_post', function ($post_id) {			

			if ( !isset( $_POST['originalaction'] ) ) {
				return;
			}

			// verify nonce
			if ( !isset( $_POST['information_metabox_nonce'] ) || !wp_verify_nonce( $_POST['information_metabox_nonce'], 'save_information_metabox' ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( !current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			if ( $this->post_type != get_post_type( $post_id ) ) {
				return;
			}
			
			foreach ( $this->meta_fields as $value ) {
				$args   = $this->parse_args( $value );
				$meta_value = $_POST[ $value['meta_key'] ] ?? '';

				// sanitize
				if ( $args['field'] == 'textarea' ) {
					// becareful with santize before can be change value strings
					$meta_value = wp_unslash( $meta_value );
				}else{
					$meta_value = sanitize_text_field( $meta_value );
				}
				
				// error_log( "{$value['meta_key']}: $meta_value" );
				update_post_meta( $post_id, $value['meta_key'], $meta_value );
			}

		} );
	}

	function init_field( $args, $meta_value ) {
		// enqueue scripts
		$this->enqueue();

		// parse args
		$args = wp_parse_args($args, [
			'field' => 'input'
		]);

		// integration
		$args['attribute']['name'] = $args['meta_key'];
		$args['value'] = $meta_value;

		if($args['field'] == 'input'){
			if(!isset($args['attribute']['value'])){
				$args['attribute']['value'] = $meta_value;
			}
		}

		if($args['field'] == 'media'){
			if(!isset($args['attribute']['value'])){
				$args['attribute']['value'] = $meta_value;
			}
		}

		// init field
		// var_dump($args['field']['value']);
		$a = \WpDatabaseHelper\Init::WpField();
		$a->setup_args( $args );
		return $a->init_field();
	}

	function get_admin_column_post( $post_id ) {
		return "<a target=_blank href='" . get_edit_post_link( $post_id ) . "'>" . get_the_title( $post_id ) . "</a>";
	}

	function get_admin_column_term( $term_id, $args ) {
		$taxonomy = $args['term_select']['taxonomy'];
		$term = get_term($term_id, $taxonomy);
		if ( is_wp_error( $term ) ) {
			return '--';
		}
		$term_link = get_edit_term_link($term_id, $taxonomy);
		return "<a target=_blank href='".$term_link."'>".$term->name."</a>";
	}
}