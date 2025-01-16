<?php
namespace WpDatabaseHelper;

class WpMeta {
	private $version;
	public $name = 'WpDatabaseHelper_meta';
	public $id;

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
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'wpdatabasehelper_meta_js' ),
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

	function init( $args ) {

		// copy args to properties
		foreach ( (array) $args as $key => $value ) {
			$this->$key = $value;
		}

		$this->id = $this->name . "_" . sanitize_title( $this->metabox_label );
	}

	function init_meta() {
		// Kiểm tra xem action 'init' đã chạy chưa
		if ( did_action( 'init' ) ) {
			$this->register_post_meta();
		} else {
			add_action( 'init', [ $this, 'register_post_meta' ] );
		}

		// Kiểm tra xem action 'admin_init' đã chạy chưa
		if ( did_action( 'admin_init' ) ) {
			$this->admin_post_columns();
			$this->metabox();
		} else {
			add_action( 'admin_init', [ $this, 'admin_post_columns' ] );
			add_action( 'admin_init', [ $this, 'metabox' ] );
		}

		// Kiểm tra xem action 'wp_ajax_wpmeta_edit__' đã chạy chưa
		if ( did_action( 'wp_ajax_wpmeta_edit__' ) ) {
			$this->wpmeta_edit__();
		} else {
			add_action( 'wp_ajax_wpmeta_edit__', [ $this, 'wpmeta_edit__' ] );
		}
	}

	function wpmeta_edit__() {
		if ( !wp_verify_nonce( $_POST['nonce'], 'wpdatabasehelper_meta_js' ) ) exit;

		// echo "<pre>"; var_dump($_POST); echo "</pre>"; 

		$meta_value = esc_attr( $_POST['meta_value'] );
		if ( $_POST['meta_value_is_json'] == 'true' ) {
			$meta_value = json_decode( stripslashes( $_POST['meta_value'] ) );
		}

		$post_id  = $_POST['post_id'];
		$meta_key = $_POST['meta_key'];

		update_post_meta(
			esc_attr( $post_id ),
			esc_attr( $meta_key ),
			$meta_value,
		);
		error_log( "update_post_meta_wpmeta_edit__: $post_id - $meta_key - $meta_value" );

		wp_send_json_success(
			$this->init_meta_value(
				json_decode( stripslashes( $_POST['args'] ), true ),
				$meta_value
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
		if ( !$this->register_post_meta ) {
			return;
		}

		foreach ( (array) $this->meta_fields as $key => $value ) {

			$type         = 'string';
			$meta_key     = $value['meta_key'];
			$show_in_rest = false;

			// checkbox
			if ( ( $value['attribute']['type'] ?? '' ) == 'checkbox' ) {
				if ( count( $value['options'] ?? [] ) > 1 ) {
					$type = 'array';
				}
			}

			register_post_meta( $this->post_type, $meta_key, array(
				'type'              => $type,
				'show_in_rest'      => $show_in_rest,
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

			// prepare array
			foreach ( (array) $this->meta_fields as $key => $value ) {
				$args = $this->parse_args( $value );
				if ( $args['admin_column'] ) {
					$insert[ $value['meta_key'] ] = esc_html( $args['label'] ?? $value['meta_key'] );
				}
			}

			// price for woocommerce
			$position = array_search( 'title', array_keys( $columns ), true );
			if ( $position === false ) {
				$position = array_search( 'price', array_keys( $columns ), true );
			}

			if ( $position !== false ) {
				$columns = array_slice( $columns, 0, $position + 1, true ) +
					$insert +
					array_slice( $columns, $position + 1, null, true );
			} else {
				$columns = $insert + $columns;
			}

			return $columns;
		}, 11, 1 ); // 11 for compatity with woocommerce



		add_action( 'manage_' . $this->post_type . '_posts_custom_column', function ($column, $post_id) {
			foreach ( (array) $this->meta_fields as $key => $field_args ) {
				if ( $field_args['meta_key'] == $column ) {
					echo $this->quick_edit_field( $field_args, $post_id );
				}
			}
		}, 10, 2 );
	}

	function quick_edit_field( $field_args, $post_id ) {
		$args       = $this->parse_args( $field_args );
		$meta_key   = $field_args['meta_key'];
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		ob_start();
		?>
		<form action="">
			<div data-meta_key="<?= esc_attr( $args['meta_key'] ); ?>" data-post_id="<?= esc_attr( $post_id ) ?>"
				data-args="<?= esc_attr( json_encode( $args, JSON_UNESCAPED_UNICODE ) ) ?>"
				class="<?= esc_attr( $this->name ) ?>_quick_edit">
				<div class="quick_edit_value">
					<?php echo $this->init_meta_value( $field_args, $meta_value ); ?>
				</div>
				<div class="quick_edit_field hidden">
					<?php echo $this->init_meta_field( $args, $meta_value ); ?>
				</div>
				<button class="quick_edit_icon button hidden" type="button">
					<?= __( 'Edit' ) ?>
				</button>
			</div>
		</form>
		<?php
		return ob_get_clean();
	}

	function metabox() {

		add_action( 'add_meta_boxes', function () {
			add_meta_box(
				sanitize_title( $this->metabox_label ), // ID of the meta box
				$this->metabox_label, // Title of the meta box
				function ($post) {
					wp_nonce_field( $this->id, "{$this->id}_nonce" );
					?>
				<div class="<?= esc_attr( $this->name ) ?>-meta-box-container">
					<div class="grid">
						<?php
							foreach ( $this->meta_fields as $value ) {
								$args       = $this->parse_args( $value );
								$meta_value = $value['meta_key'];
								?>
							<div class="item <?= implode( " ", $args['field_classes'] ) ?>">
								<?php
									echo $this->init_meta_field(
										$args,
										get_post_meta( $post->ID, $meta_value, true )
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
			if (
				!isset( $_POST[ "{$this->id}_nonce" ] ) ||
				!wp_verify_nonce( $_POST[ "{$this->id}_nonce" ], $this->id ) ) {
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
				$args       = $this->parse_args( $value );
				$meta_key   = $value['meta_key'];
				$meta_value = $_POST[ $meta_key ] ?? '';

				if ( !is_array( $meta_value ) ) {
					// sanitize
					if ( $args['field'] == 'textarea' ) {
						// becareful with santize before can be change value strings
						$meta_value = wp_unslash( $meta_value );
					} else {
						$meta_value = sanitize_text_field( $meta_value );
					}
				}

				update_post_meta(
					$post_id,
					$meta_key,
					$meta_value
				);
				error_log( "update_post_metametabox: $post_id - $meta_key - $meta_value" );
			}

		} );
	}

	function init_args( $args, $meta_value ) {
		// parse args
		$args = wp_parse_args( $args, [ 
			'field' => 'input',
		] );

		// integration
		$args['attribute']['name'] = $args['meta_key'];
		$args['value']             = $meta_value;

		if ( str_contains( $args['field'], 'input' ) ) {
			$args['attribute']['value'] = $meta_value;
		}
		
		return $args;
	}

	function init_meta_value( $args, $meta_value ) {
		$args = $this->init_args( $args, $meta_value );
		$a    = \WpDatabaseHelper\Init::WpField();
		$a->setup_args( $args );
		return $a->init_field_value();
	}

	function init_meta_field( $args, $meta_value ) {
		$this->enqueue();
		$args = $this->init_args( $args, $meta_value );
		$a    = \WpDatabaseHelper\Init::WpField();
		$a->setup_args( $args );
		return $a->init_field();
	}
}