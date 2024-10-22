<?php
namespace WpDatabaseHelper;

class WpMeta {
	private $version;
	private static $instance = null;
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

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

	function parse_args_metafield( $setup, $metafield ) {
		$default = [ 
			'label'            => $metafield,
			'admin_column'     => true,
			'field_classes'    => [], // ['full_width']
			'quick_edit'       => true,
			'field'            => 'input', // select, input, media
			'options'          => [], // [key=>value, key2=>value2]
			'callback'         => false, // can be function(){return 'x';}
			'post_type_select' => false, // post, page
			'user_select'      => false, // true
			'attribute'        => [],
		];
		$return = wp_parse_args( $setup, $default );
		// echo "<pre>"; print_r($return); echo "</pre>";die;
		return $return;
	}

	function register_post_meta( $post_type, $metafields ) {
		foreach ( $metafields as $metafield => $setup ) {
			$setup = $this->parse_args_metafield( $setup, $metafield );
			if ( !( $setup['callback'] ?? false ) ) {
				register_post_meta( $post_type, $metafield, array(
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
	}

	function setup_admin_post_columns( $post_type, $metafields ) {
		add_filter( 'manage_' . $post_type . '_posts_columns', function ($columns) use ($metafields) {
			$insert = [];
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args_metafield( $setup, $metafield );
				if ( $setup['admin_column'] ) {
					$column_name          = str_replace( [ "_", 'id' ], [ " ", '' ], $metafield );
					$insert[ $metafield ] = esc_html( $setup['label'] ?? $column_name );
				}
			}
			$first_column = array_slice( $columns, 0, 2, true );
			$last_column  = array_slice( $columns, 2, null, true );
			$columns      = $first_column + $insert + $last_column;
			return $columns;
		} );

		add_action( 'manage_' . $post_type . '_posts_custom_column', function ($column, $post_id) use ($metafields) {
			if ( array_key_exists( $column, $metafields ) ) {
				$setup     = $metafields[ $column ];
				$metafield = $column;
				$setup     = $this->parse_args_metafield( $setup, $metafield );
				// for custom callback
				if ( $setup['callback'] ) {
					echo call_user_func( $setup['callback'], $metafield, $post_id );
				} else {
					$value = get_post_meta( $post_id, $metafield, true );
					if ( $value !== '' ) {
						if ( $metafields[ $metafield ]['post_select'] ?? "" ) {
							echo $this->get_admin_column_post( $value );
						} elseif ( ( $metafields[ $metafield ]['field'] ?? "" ) == 'media' ) {
							echo wp_get_attachment_image( $value, 'thumbnail', false, [ 'style' => 'width: 50px; height: auto;' ] );
						} else {
							echo esc_attr( $value );
						}
					} else {
						echo "--";
					}
				}
			}
		}, 10, 2 );
	}

	function setup_quick_edit_post( $post_type, $metafields ) {
		/* Bởi vì quick edit được load bằng js, nên wordpress ko cung cấp param $post_id, 
			  vì vậy trong quick_edit_custom_box truyền value = ''
			  value được lấy từ js trong add_inline_data
			  field media cũng ko cần update lại nếu ko thực sự quan trọng */

		add_action( 'quick_edit_custom_box', function ($column_name, $_post_type) use ($post_type, $metafields) {
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args_metafield( $setup, $metafield );
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
									echo $this->init_field( $setup, $metafield, '' );
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
				$setup = $this->parse_args_metafield( $setup, $metafield );
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
				$setup = $this->parse_args_metafield( $setup, $metafield );
				if ( $setup['quick_edit'] ) {
					$_value = $_POST[ $metafield ] ?? '';

					$new_value = sanitize_text_field( $_value );
					if ( $setup['field'] == 'textarea' ) {
						$new_value = sanitize_textarea_field( $_value );
					}
					update_post_meta( $post_id, $metafield, $new_value );
				}
			}

		}, 10, 2 );
	}

	function setup_admin_post_metabox( $post_type, $metafields, $metaboxlabel ) {

		add_action( 'add_meta_boxes', function () use ($post_type, $metafields, $metaboxlabel) {
			add_meta_box(
				sanitize_title( $metaboxlabel ), // ID of the meta box
				$metaboxlabel, // Title of the meta box
				function ($post) use ($metafields) {
					wp_nonce_field( 'save_information_metabox', 'information_metabox_nonce' );
					?>
				<div class="<?= esc_attr( self::$name ) ?>-meta-box-container">
					<div class="grid">
						<?php
							$count = 0;
							foreach ( $metafields as $metafield => $setup ) {
								$setup = $this->parse_args_metafield( $setup, $metafield );
								?>
							<div class="item <?= implode( " ", $setup['field_classes'] ) ?>">
								<?php
									$value = get_post_meta( $post->ID, $metafield, true );
									if ( $setup['callback'] ) {
										$value = call_user_func( $setup['callback'], $metafield, $post->ID );
									}
									echo $this->init_field( $setup, $metafield, $value );
									?>
							</div>
							<?php
								$count++;
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
				$post_type // The post type to which this meta box should be added
			);
		} );

		add_action( 'save_post', function ($post_id) use ($post_type, $metafields) {
			if ( $post_type != get_post_type( $post_id ) ) {
				return;
			}

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
			// echo "<pre>"; print_r($_POST); echo "</pre>"; die;
			foreach ( $metafields as $metafield => $setup ) {
				$setup  = $this->parse_args_metafield( $setup, $metafield );
				$_value = $_POST[ $metafield ] ?? '';

				$new_value = sanitize_text_field( $_value );
				if ( $setup['field'] == 'textarea' ) {
					// becareful with santize before can be change value strings
					$new_value = wp_unslash( $_value );
				}
				error_log( "$metafield: $new_value" );
				update_post_meta( $post_id, $metafield, $new_value );
			}

		} );
	}

	function init_field( $setup, $metafield, $meta_value ) {
		$this->enqueue();
		// integration
		$args = $setup;
		$args['show_copy_key'] = true;
		$args['attribute']['type'] = $setup['attribute']['type'] ?? 'text';
		$args['attribute']['name'] = $metafield;
		$args['attribute']['value'] = $meta_value; // be careful for input checkbox

		// textarea
		if ( $args['field'] == 'textarea' ) {
			$args['value'] = $meta_value;
		}

		// checkbox - radio
		if ( in_array( $args['attribute']['type'] ?? '',['checkbox', 'radio']) ) {
			//restore value of dom to attritube['value'] 
			$args['attribute']['value'] = $setup['attribute']['value'];
			if( $args['attribute']['value'] == $meta_value){
				$args['attribute']['checked'] = 'checked';
			}
		}

		// init field
		$a = \WpDatabaseHelper\Init::WpField();
		$a->setup_args( $args );
		return $a->init_field();
	}

	function get_admin_column_post( $post_id ) {
		return "<a target=blank href='" . get_edit_post_link( $post_id ) . "'>" . get_the_title( $post_id ) . "</a>";
	}
}