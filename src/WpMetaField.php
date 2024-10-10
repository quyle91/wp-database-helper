<?php
namespace WpDatabaseHelper;

class WpMetaField {
    private $version;
	private static $instance = null;
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private static $get_assets = null;
	private static $name = 'ads';

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

	function parse_args_metafield( $setup, $metafield ) {
		$default = [ 
			'label'            => $metafield,
			'admin_column'     => true,
			'field_classes'    => [], // ['full_width']
			'quick_edit'       => true,
			'meta_box'         => 'input', // select, input, media
			'options'          => [], // [key=>value, key2=>value2]
			'callback'         => false, // can be function(){return 'x';}
			'post_type_select' => false, // post, page
			'user_select'      => false, // true
			'attributes'       => [],
		];
		return wp_parse_args( $setup, $default );
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
						if ( $metafields[ $metafield ]['post_type_select'] ?? "" ) {
							echo $this->get_admin_column_post( $value );
						} elseif ( $metafields[ $metafield ]['user_select'] ?? "" ) {
							echo $this->get_admin_column_user( $value );
						} elseif ( ( $metafields[ $metafield ]['meta_box'] ?? "" ) == 'media' ) {
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
		add_action( 'quick_edit_custom_box', function ($column_name, $_post_type) use ($post_type, $metafields) {
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args_metafield( $setup, $metafield );
				if ( $setup['quick_edit'] and $metafield == $column_name and $_post_type == $post_type ) {
					?>
					<fieldset class="custom-fieldset inline-edit-col-left">
						<div class="inline-edit-col <?= esc_attr( self::$name ) ?>-meta-box-container">
							<label>
								<span class="title">
									<?php echo $this->field_title( $setup, $metafield ) ?>
								</span>
								<span class="input-text-wrap">
									<?php echo $this->form_field( $setup, $metafield, '' ); // blank field ?>
								</span>
							</label>
						</div>
					</fieldset>
					<?php
				}
			}
		}, 10, 2 );

		add_action( 'admin_footer', function () {
			echo self::get_assets();
		} );

		add_action( 'add_inline_data', function ($post) use ($post_type, $metafields) {
			foreach ( $metafields as $metafield => $setup ) {
				$setup = $this->parse_args_metafield( $setup, $metafield );
				if ( $setup['quick_edit'] ) {
					?>
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
					if ( $setup['meta_box'] == 'textarea' ) {
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
								if ( $count % 2 == 0 ) {
									if ( $count > 0 ) {
										echo '</tr>';
									}
									echo '<tr>';
								}
								?>
							<div class="item <?= implode( " ", $setup['field_classes'] ) ?>">
								<div class="title">
									<label for="<?= esc_attr( $metafield ) ?>">
										<?php echo $this->field_title( $setup, $metafield ) ?>
									</label>
								</div>
								<?php
									$value = get_post_meta( $post->ID, $metafield, true );
									if ( $setup['callback'] ) {
										$value = call_user_func( $setup['callback'], $metafield, $post->ID );
									}
									echo $this->form_field( $setup, $metafield, $value );
									?>
							</div>
							<?php
								$count++;
							}

							if ( $count % 2 != 0 ) {
								echo '<td class="item"></td></tr>'; // Close the row if there are an odd number of fields
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

			foreach ( $metafields as $metafield => $setup ) {
				$setup  = $this->parse_args_metafield( $setup, $metafield );
				$_value = $_POST[ $metafield ] ?? '';

				$new_value = sanitize_text_field( $_value );
				if ( $setup['meta_box'] == 'textarea' ) {
					$new_value = sanitize_textarea_field( $_value );
				}
				error_log( $new_value );
				update_post_meta( $post_id, $metafield, $new_value );
			}

		} );

		add_action( 'admin_footer', function () {
			echo self::get_assets();
		} );
	}

	function field_title( $setup, $metafield ) {
		ob_start();
		?>
		<?= esc_html( $setup['label'] ?? str_replace( "_", " ", $metafield ) ) ?>
		<?php
		return ob_get_clean();
	}

	function field_description( $setup, $metafield ) {
		ob_start();
		?>
		<code class="copy"> <?= esc_attr( $metafield ) ?> </code>
		<?php
		return ob_get_clean();
	}

	function form_field( $setup, $metafield, $value ) {
		$field_type    = $setup['meta_box'];
		$function_name = "form_field_$field_type";
		if ( method_exists( $this, $function_name ) ) {

			// debug
			echo call_user_func( [ $this, $function_name ], $metafield, $setup, $value );
		} else {
			echo 'function not exist';
		}
		echo $this->field_description( $setup, $metafield );
	}

	function form_field_custom( $name, $setup, $value ) {
		return $value;
	}

	function form_field_attributes( $name, $setup, $value ) {
		$attributes = wp_parse_args(
			$setup['attributes'],
			[ 
				'type'  => 'text',
				'name'  => $name,
				'id'    => $name . '_id',
				'class' => self::$name . '-field regular-text',
				'value' => $value,
			]
		);

		// textarea
		if ( $setup['meta_box'] == 'textarea' ) {
			$attributes['rows'] = 4;
		}

		// checkbox
		if ( $setup['meta_box'] == 'input' and $attributes['type'] == 'checkbox' ) {

			//default value
			if ( !$attributes['value'] ) {
				$attributes['value'] = 'on';
			}

			$checked = false;
			if ( $attributes['value'] == $value ) {
				$checked = true;
			}

			if ( $checked ) {
				$attributes['checked'] = 1;
			}
		}


		// echo "<pre>"; print_r($attributes); echo "</pre>";

		$html = [];
		foreach ( (array) $attributes as $key => $value ) {
			$html[] = "$key=\"$value\"";
		}

		return implode( " ", $html );
	}

	function form_field_textarea( $name, $setup, $value ) {
		ob_start();
		?>
		<textarea <?= $this->form_field_attributes( $name, $setup, $value ) ?>><?= esc_textarea( $value ) ?></textarea>
		<?php
		return ob_get_clean();
	}

	function form_field_input( $name, $setup, $value ) {
		ob_start();
		?>
		<input <?= $this->form_field_attributes( $name, $setup, $value ) ?> />
		<?php
		return ob_get_clean();
	}

	function form_field_media( $name, $setup, $value ) {
		ob_start();
		$id        = wp_rand();
		$image_url = $value ? wp_get_attachment_url( $value ) : '';
		?>
		<div class="form_field_media">
			<img id='image-preview<?php echo $id; ?>' src='<?php echo esc_url( $image_url ); ?>'
				style='max-width: 100px; display: <?php echo $image_url ? 'block' : 'none'; ?>' />
			<input type='hidden' name='<?php echo esc_attr( $name ); ?>' id='<?php echo esc_attr( $name ); ?>'
				value='<?php echo esc_attr( $value ); ?>' class='<?= esc_attr( self::$name ) ?>-field regular-text' />
			<button type='button' class='button hepperMeta-media-upload<?= $id ?>'><?= __( 'Upload' ); ?>
			</button>
			<button type='button' class='button hepperMeta-media-remove<?= $id ?>'><?= __( 'Remove' ); ?>
			</button>

			<script type='text/javascript'>
				jQuery(document).ready(function ($) {
					$('.hepperMeta-media-upload<?php echo $id; ?>').on('click', function (e) {
						e.preventDefault();
						var button = $(this);
						var input = button.closest(".form_field_media").find('input');
						var preview = $('#image-preview<?php echo $id; ?>');
						var frame = wp.media({
							title: '<?= __( 'Uploads' ) ?> ',
							button: {
								text: '<?= __( 'Use this media' ) ?>'
							},
							multiple: false
						});
						frame.on('select', function () {
							var attachment = frame.state().get('selection').first().toJSON();
							input.val(attachment.id);
							preview.attr('src', attachment.url).show();
						});
						frame.open();
					});
					$('.hepperMeta-media-remove<?php echo $id; ?>').on('click', function (e) {
						e.preventDefault();
						var button = $(this);
						var input = button.closest(".form_field_media").find('input');
						var preview = $('#image-preview<?php echo $id; ?>');
						input.val('');
						preview.attr('src', '').hide();
					});
				});
			</script>
		</div>
		<?php
		return ob_get_clean();
	}

	function form_field_select( $name, $setup, $value ) {
		ob_start();
		?>
		<select <?= $this->form_field_attributes( $name, $setup, $value ) ?>>
			<?php
			$options = $this->get_options( $setup );
			echo $this->get_options_tag( $options, $name, $value );
			?>
		</select>
		<?php
		return ob_get_clean();
	}

	function get_admin_column_post( $post_id ) {
		return "<a target=blank href='" . get_edit_post_link( $post_id ) . "'>" . get_the_title( $post_id ) . "</a>";
	}

	function get_admin_column_user( $user_id ) {
		return "<a target=blank href='" . get_edit_user_link( $user_id ) . "'>" . ( get_user_by( 'id', $user_id )->display_name ) . "</a>";
	}

	function form_field_organization_manage( $name, $setup, $value ) {
		ob_start();
		echo '<mark>Update later!!!</mark>';
		return ob_get_clean();
	}

	function get_options( $setup ) {
		$options = [ "" => 'Select' ];

		// options
		if ( $setup['options'] ) {
			foreach ( (array) $setup['options'] as $key => $value ) {
				$options[ $key ] = $value;
			}
		}

		// post_type_select
		if ( $setup['post_type_select'] ) {
			$options = $options + $this->get_options_post_type( $setup['post_type_select'] );
		}

		// user_select
		if ( $setup['user_select'] ) {
			$options = $options + $this->get_options_user( $setup['user_select'] );
		}
		return $options;
	}

	function get_options_tag( $options, $name, $value ) {
		ob_start();
		foreach ( $options as $key => $label ) {
			$selected = ( $key == $value ) ? "selected" : "";
			?>
			<option <?= esc_attr( $selected ) ?> value="<?= esc_attr( $key ) ?>">
				<?= esc_attr( $label ) ?>
			</option>
			<?php
		}
		return ob_get_clean();
	}

	function get_options_post_type( $post_type ) {
		$return = [];
		$args   = [ 
			'post_type'   => $post_type,
			'post_status' => 'publish',
			'numberposts' => -1,
		];

		$posts = get_posts( $args );
		foreach ( $posts as $post ) {
			$return[ $post->ID ] = get_the_title( $post->ID );
		}
		return $return;
	}

	function get_options_user( $role ) {
		$users = get_users( array(
			// 'role'   => $role,
			'fields' => array( 'ID', 'display_name' ),
		) );

		$staff_options = array();
		foreach ( $users as $user ) {
			$staff_options[ $user->ID ] = $user->display_name;
		}
		return $staff_options;
	}

	static function get_assets() {

		// make sure 1 time loaded
		if ( self::$get_assets ) {
			return;
		}

		ob_start();
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {

				// quick edit
				$('body').on('focus', '.ptitle', function (e) {
					const _ptitle = e.currentTarget;
					const _tr = $(_ptitle.closest(".inline-edit-row"));
					const _tr_id = _tr.attr('id');
					const _inline_id = _tr_id.replace("edit-", "inline_");
					const _inline = $("#" + _inline_id);
					if (_inline.length) {
						const _inline0 = _inline[0];
						// console.log(_inline0);
						_tr.find('.<?= esc_attr( self::$name ) ?>-field').each(function (index, item) {
							let _field_name = $(item).attr('name');
							let _field_searchs = $(_inline0).find("." + _field_name);
							if (_field_searchs.length) {
								_field_search = $(_field_searchs[0]);
								let _field_search_value = _field_search.text();
								// console.log($(item), _field_search_value); 

								if ($(item).is(':checkbox')) {
									$(item).prop('checked', _field_search_value === $(item).val());
								} else if ($(item).is(':radio')) {
									$(item).prop('checked', $(item).val() === _field_search_value);
								} else {
									$(item).val(_field_search_value);
								}

								// fix for input checked

								$(item).trigger('change');
							}
						});
					}
				});

				// click to copy
				$('body').on('click', '.<?= self::$name ?>-meta-box-container .copy', function (e) {
					e.preventDefault();
					var textToCopy = $(this).text().trim();
					var tempInput = document.createElement('input');
					document.body.appendChild(tempInput);
					tempInput.value = textToCopy;
					tempInput.focus();
					tempInput.setSelectionRange(0, tempInput.value.length);
					document.execCommand('copy');
					document.body.removeChild(tempInput);
					alert('Copied: ' + textToCopy);
				});
			});
		</script>
		<style type="text/css">
			.<?= self::$name ?>-field {
				width: 100%;
				max-width: unset !important;
				border-color: #2271b1 !important;
				border-width: 2px !important;
				box-sizing: border-box;
				/* background-color: #efefef !important; */
			}

			.<?= self::$name ?>-meta-box-container .grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 10px;
				margin-bottom: 10px;
			}

			/** bù phần còn thiếu của td */
			.<?= self::$name ?>-meta-box-container .item {
				box-sizing: border-box;
			}

			.<?= self::$name ?>-meta-box-container .item.full_width {
				grid-column: 1 / -1;
			}

			.<?= self::$name ?>-meta-box-container .title {
				margin-bottom: 5px;
			}

			.<?= self::$name ?>-meta-box-container .copy {
				cursor: pointer;
				opacity: 0.5;
				border-radius: 3px;

			}

			.<?= self::$name ?>-meta-box-container .copy:hover {
				opacity: 1;
				background-color: lightgray;
				color: black;
			}

			.<?= self::$name ?>-meta-box-container .footer {
				opacity: 0.5;
				text-align: right;
			}

			/* debug js quick edit */
			/* .column-title .hidden{
										display: block !important;
										border: 1px solid dashed;
										background: lightgray;
									} */
		</style>
		<?php
		self::$get_assets = true;
		return ob_get_clean();
	}
}