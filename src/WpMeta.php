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
				$args = $this->parse_args( $value );
				if($value['meta_key'] == $column){
					$meta_value = get_post_meta( $post_id, $value['meta_key'], true );
					if ( $meta_value ) {
						if($args['field'] == 'media'){
							echo wp_get_attachment_image( 
								$meta_value, 
								'thumbnail', 
								false, 
								[ 
									'style' => 'width: 50px; height: auto;' 
								]
							);
						}elseif(!empty($args['post_select'])){
							echo $this->get_admin_column_post( $meta_value );
						}else{
							echo esc_attr( $meta_value );
						}
					}else{
						echo "--";
					}
				}
			}
		}, 10, 2 );
	}

	function quick_edit_post() {
		/* Bởi vì quick edit được load bằng js, nên wordpress ko cung cấp param $post_id, 
			  vì vậy trong quick_edit_custom_box truyền value = ''
			  value được lấy từ js trong add_inline_data
			  field media cũng ko cần update lại nếu ko thực sự quan trọng */

		/* add_action( 'quick_edit_custom_box', function ($column_name, $_post_type) use ($post_type, $metafields) {
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args( $setup, $metafield );
				if ( $setup['quick_edit'] and $metafield == $column_name and $_post_type == $post_type ) {
					?>
					<fieldset class="custom-fieldset inline-edit-col-left">
						<div class="inline-edit-col <?= esc_attr( self::$name ) ?>-meta-box-container">
							<label>
								<span class="title">
									<?= esc_attr( $setup['label'] ) ?>
								</span>
								<span class="input-text-wrap">
									<?php
									if ( $setup['label'] ?? '' ) {
										$setup['label'] = '';
									}
									$setup['wrap_class'] = 'full_width';
									echo $this->init_field( $setup, $metafield, false);
									?>
								</span>
							</label>
						</div>
					</fieldset>
					<?php
				}
			}
		}, 10, 2 );

		add_action( 'add_inline_data', function ($post) use ($post_type, $metafields) {
			$this->enqueue(); // necessary for debug
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args( $setup, $metafield );
				if ( $setup['quick_edit'] ) {
					?>
					<?php // Keep it as 1 line ?>
					<div class="<?= esc_attr( $metafield ) ?>"><?= esc_attr( get_post_meta( $post->ID, $metafield, true ) ) ?></div>
					<?php
				}
			}
		}, 10, 1 );

		add_action( 'save_post', function ($post_id) use ($post_type, $metafields) {
			if ( $post_type != get_post_type( $post_id ) ) {
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

			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args( $setup, $metafield );
				if ( $setup['quick_edit'] ) {
					$_value = $_POST[ $metafield ] ?? '';

					$new_value = sanitize_text_field( $_value );
					if ( $setup['field'] == 'textarea' ) {
						$new_value = sanitize_textarea_field( $_value );
					}
					update_post_meta( $post_id, $metafield, $new_value );
				}
			}

		}, 10, 2 ); */
	}

	function metabox( ) {

		$post_type = $this->post_type;
		$metafields = $this->meta_fields;
		$metaboxlabel = $this->metabox_label;
		
		add_action( 'add_meta_boxes', function () use ($post_type, $metafields, $metaboxlabel) {
			add_meta_box(
				sanitize_title( $metaboxlabel ), // ID of the meta box
				$metaboxlabel, // Title of the meta box
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
				$post_type
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

		// integration
		$args['attribute']['name'] = $args['meta_key'];
		$args['value'] = $meta_value;

		// init field
		$a = \WpDatabaseHelper\Init::WpField();
		$a->setup_args( $args );
		return $a->init_field();
	}

	function get_admin_column_post( $post_id ) {
		return "<a target=blank href='" . get_edit_post_link( $post_id ) . "'>" . get_the_title( $post_id ) . "</a>";
	}
}