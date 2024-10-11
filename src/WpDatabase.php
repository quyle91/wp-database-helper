<?php
namespace WpDatabaseHelper;

class WpDatabase {
	private $version;
	private static $instance = null;
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private $table_name;
	private $menu_slug;
	private $menu_title;
	private $wrap_id;
	private $fields = [
		// 'id INT(11) NOT NULL AUTO_INCREMENT,',
		// 'name VARCHAR(255) NOT NULL,',
		// 'endpoint_url VARCHAR(255) NOT NULL,',
		// 'date DATETIME NOT NULL,',
		// 'status VARCHAR(255) NOT NULL,',
		// 'logs LONGTEXT NOT NULL,',
		// 'PRIMARY KEY (id)',
	];
	private $fields_sql;
	private $fields_array = [];

	function __construct() {
		$this->version = $this->getVersion();
	}
	
	function init_table( $args = [] ) {
		$this->update_fields( $args );
		$this->maybe_create_table();
		$this->create_view_pages();
		$this->create_ajax();
		$this->process_actions();
	}
    
	private function getVersion() {
		$composerFile = __DIR__ . '/../composer.json';
		if ( file_exists( $composerFile ) ) {
			$composerData = json_decode( file_get_contents( $composerFile ), true );
			return $composerData['version'] ?? '0.0.0';
		}
		return '0.0.0';
	}

	function update_fields( $args ) {

		// applies args from param
		foreach ( (array) $args as $key => $value ) {
			$this->$key = $value;
		}

		global $wpdb;
		$this->table_name   = $wpdb->prefix . $this->table_name;
		$this->fields_sql   = implode( " ", (array) $this->fields );
		$this->fields_array = $this->get_fields_array();
		$this->menu_slug    = 'menu_' . $this->table_name;
		$this->wrap_id      = $this->table_name . rand();
	}

	function get_fields_array() {
		$return = [];
		foreach ( (array) $this->fields as $key => $string ) {
			if (
				stripos( $string, 'UNIQUE' ) === 0 ||
				stripos( $string, 'PRIMARY' ) === 0 ||
				stripos( $string, 'INDEX' ) === 0 ||
				stripos( $string, 'FOREIGN' ) === 0 ) {
				continue;
			}

			$string   = trim( $string );
			$name     = explode( " ", $string )[0] ?? "";
			$type     = explode( " ", $string )[1] ?? "";
			$return[] = [ 
				'name' => $name,
				'type' => $type,
			];
		}
		return $return;
	}

	function process_actions() {
		add_action( 'admin_init', function () {

			// reset table
			global $wpdb;
			if ( is_user_logged_in() && isset( $_GET[ 'reset_' . $this->table_name ] ) ) {
				if ( current_user_can( 'manage_options' ) ) {
					$table_name = $this->table_name;

					// delete table
					if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
						$sql = "DROP TABLE IF EXISTS $table_name;";
						$wpdb->query( $sql );
					}

					$this->maybe_create_table();
					wp_redirect( $this->get_page_url() ); // reset link
				}
			}

			// add new
			if ( isset( $_POST[ 'add_record_' . $this->table_name ] ) ) {
				if ( !wp_verify_nonce( $_POST['nonce'], $this->table_name ) ) exit;
				$this->insert( $_POST );
				wp_redirect( $this->get_page_url() ); // reset link
			}

			// delete
			if ( isset( $_POST[ $this->table_name ] ) ) {
				if ( ( $_POST['action'] ?? "" ) == 'delete' ) {
					if ( $_POST['ids'] ?? "" ) {
						$this->delete( $_POST['ids'] );
					}
				}
			}
		} );
	}

	function maybe_create_table() {
		global $wpdb;
		$table_name = $this->table_name;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE {$table_name} ({$this->fields_sql}) {$charset_collate};";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	// CRUD
	function create( $data ) {
		return $this->insert( $data );
	}

	function get( $args, $show_sql = false ) {
		return $this->read( $args, $show_sql );
	}

	function read( $args, $show_sql = false ) {
		global $wpdb;

		// get parse args from input
		$args = $this->merge_get_args( $args );

		$sql = "SELECT * FROM $this->table_name WHERE 1=1";

		// where sql
		if ( !empty( $args['where'] ) ) {

			// OLD - simple
			// $args = [ 'where' => [ 'a' => 1, 'b' => 2, ] ];
			if ( !isset( $args['where'][0] ) ) {
				$where = [];
				foreach ( $args['where'] as $field => $value ) {
					$where[] = $wpdb->prepare( "$field = %s", $value );
				}
				$where_sql = implode( " AND ", $where );
				$sql .= " AND ($where_sql)";
			}

			// New - like wp query meta_query
			// $args = [ 'where' => [ 'relation' => 'AND', [ 'key' => 'xxx', 'value' => 'yyy', 'type' => 'CHAR', 'compare' => '=' ] ] ];
			if ( isset( $args['where'][0] ) ) {
				$relation = $args['where']['relation'] ?? '';
				$where    = [];
				foreach ( $args['where'] as $key => $value ) {
					if ( $key == 'relation' ) {
						continue;
					} else {

						// data type
						if ( in_array( $value['type'], [ 'CHAR', 'VARCHAR', 'TEXT', 'DATETIME', 'DATE', 'TIME' ] ) ) {
							$value_type = '%s';
						} elseif ( in_array( $value['type'], [ 'INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'YEAR' ] ) ) {
							$value_type = '%d';
						} elseif ( in_array( $value['type'], [ 'DECIMAL', 'FLOAT', 'DOUBLE' ] ) ) {
							$value_type = '%f';
						} elseif ( in_array( $value['type'], [ 'BINARY', 'VARBINARY' ] ) ) {
							$value_type = '%b';
						} else {
							$value_type = '%s';
						}

						// prepare
						$where[] = $wpdb->prepare(
							"{$value['key']} {$value['compare']} $value_type",
							$value['value']
						);
					}
				}
				$where_sql = implode( " AND ", $where );
				$sql .= " $relation ($where_sql)";
			}

		}

		// order by and order
		$sql .= " ORDER BY " . esc_sql( $args['order_by'] ) . " " . esc_sql( $args['order'] );

		// paged and post_per_page
		if ( $args['posts_per_page'] > 0 ) {
			$offset = ( $args['paged'] - 1 ) * $args['posts_per_page'];
			$sql .= $wpdb->prepare( " LIMIT %d OFFSET %d", $args['posts_per_page'], $offset );
		}

		// for debug
		if ( $show_sql ) {
			echo "<div class=sql>";
			echo '<code>' . ( $sql ) . '</code>';
			echo "</div>";
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	function update( $data, $modified_date_column = false ) {
		global $wpdb;
		$_data = $data;

		if ( $modified_date_column ) {
			$_data = array_merge( $data, array( $modified_date_column => current_time( 'mysql' ) ) );
		}

		$result = $wpdb->update(
			$this->table_name,
			$_data,
			[ 
				'id' => $data['id'],
			],
		);

		if ( is_wp_error( $result ) ) {
			var_dump( $result );
			return $result;
		}

		return $data['id'];
	}

	function delete( $ids ) {
		global $wpdb;
		$ids = (array) $ids;

		foreach ( (array) $ids as $key => $id ) {
			$wpdb->delete(
				$this->table_name,
				array( 'id' => $id ),
				array( '%d' )
			);
		}
	}

	function insert( $data, $created_date_column = false ) {
		global $wpdb;

		// get list columns to make sure on listed columns sql as insert
		$columns = [];
		foreach ( (array) $this->fields_array as $key => $value ) {
			$columns[] = $value['name'];
		}

		// prepare $data
		$_data = [];
		foreach ( (array) $data as $key => $value ) {
			if ( in_array( $key, $columns ) ) {
				$_data[ $key ] = $value;
			}
		}

		if ( $created_date_column ) {
			$_data[ $created_date_column ] = current_time( 'mysql' );
		}

		// override id 
		$_data['id'] = '';

		// run
		$inserted = $wpdb->insert(
			$this->table_name,
			$_data,
		);

		if ( !$inserted ) {
			echo "<pre>";
			print_r( $_data );
			echo "</pre>";
			echo "<pre>";
			print_r( $wpdb->last_error );
			echo "</pre>";
			die;
		}
		return $wpdb->insert_id;
	}

	function upsert( $data, $unique_column ) {
		global $wpdb;
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name} WHERE $unique_column = %s",
				$data[ $unique_column ]
			)
		);

		if ( $existing_id ) {
			$data['id'] = $existing_id;
			return $this->update( $data );
		} else {
			return $this->insert( $data );
		}
	}

	function get_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d", $id ),
			ARRAY_A
		);
	}

	function get_last_run() {
		global $wpdb;
		$sql    = $wpdb->prepare( "
			SELECT DISTINCT(date)
			FROM $this->table_name 
			order by date desc
			limit 1
			"
		);
		$result = $wpdb->get_var( $sql );
		return $result;
	}

	function get_log_count() {
		global $wpdb;
		$count = $wpdb->get_var( "SELECT count(id) FROM $this->table_name" );
		return $count;
	}

	function create_view_pages() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				"options-general.php",
				$this->menu_title,
				$this->menu_title,
				'manage_options',
				$this->menu_slug,
				[ $this, 'html' ]
			);
		} );
	}

	function create_ajax() {
		add_action( 'wp_ajax_' . $this->table_name . '_update_data', function () {
			if ( !wp_verify_nonce( $_POST['nonce'], $this->table_name ) ) exit;
			$return = false;

			ob_start();

			// code here
			$data = [];
			$post = $_POST;

			if ( isset( $post['field_id'] ) ) {
				$data['id'] = $post['field_id'];
			}

			// value
			if ( isset( $post['field_id'] ) and isset( $post['field_value'] ) ) {
				$_value        = $post['field_value'];
				$_key          = $post['field_name'];
				$data[ $_key ] = $_value;

				if ( $this->update( $data ) ) {
					echo apply_filters( "{$this->table_name}_{$_key}", $_value );
				}
			}

			$return = ob_get_clean();
			if ( !$return ) {
				wp_send_json_error( 'Error' );
				wp_die();
			}

			wp_send_json_success( $return );
			wp_die();
		} );

	}

	function merge_get_args( $args = [] ) {
		$defaults = [ 
			'where'          => [],
			'order'          => esc_attr( $_GET['order'] ?? 'DESC' ),
			'order_by'       => esc_attr( $_GET['order_by'] ?? 'id' ),
			'posts_per_page' => (int) ( $_GET['posts_per_page'] ?? 100 ),
			'paged'          => (int) ( $_GET['paged'] ?? 1 ),
		];

		if ( !$defaults['posts_per_page'] ) {
			$defaults['posts_per_page'] = 100;
		}

		if ( !$defaults['paged'] ) {
			$defaults['paged'] = 1;
		}

		return wp_parse_args( $args, $defaults );
	}

	function html() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( 'Can not manage this page' );
		}
		?>
		<div class="wrap <?= esc_attr( $this->wrap_id ) ?>">
			<h2>
				<?php echo esc_attr( $this->menu_title ); ?>
				<small><?php echo esc_attr( $this->table_name ); ?></small>
			</h2>

			<!-- assets -->
			<?php echo $this->get_assets(); ?>

			<!-- html -->
			<div class="wrap_inner">
				<!-- filters -->
				<?php echo $this->get_filters(); ?>
				<!-- add -->
				<?php echo $this->get_box_add_record(); ?>
				<form action="" method="post">
					<input type="hidden" name="<?= $this->table_name ?>">
					<?php
					$args    = $this->merge_get_args();
					$records = $this->read( $args, true );
					echo $this->get_records( $records );

					echo '<div class="bot">';
					echo $this->get_bulk_edit();
					echo $this->get_pagination();
					echo '</div>';

					echo $this->get_note();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	function get_records( $records ) {
		ob_start();
		?>
		<div class="records">
			<table data-table-name="<?= $this->table_name ?>">
				<tr>
					<th></th>
					<?php
					foreach ( $this->fields_array as $key => $value ) {
						?>
						<th>
							<?= esc_attr( $value['name'] ); ?>
							<small>
								<?= esc_attr( $value['type'] ); ?>
							</small>
						</th>
						<?php
					}
					?>
				</tr>
				<?php
				foreach ( (array) $records as $key => $record ) {
					?>
					<tr>
						<td> <input type="checkbox" name="ids[]" value="<?= $record['id'] ?>"> </td>
						<?php
						foreach ( (array) $record as $_key => $_value ) {
							// "{$wpdb->prefix}{$this->table_name}_xxx"
							?>
							<td>
								<span class="span"><?= apply_filters( "{$this->table_name}_{$_key}", esc_attr( $_value ) ) ?></span>
								<textarea class="textarea hidden" name="<?= $_key ?>" id=""><?= esc_attr( $_value ) ?></textarea>
							</td>
							<?php
						}
						?>
					</tr>
					<?php
				}
				?>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_bulk_edit() {
		ob_start();
		?>
		<div class="bulk">
			<label for="ac">
				<input id="ac" type="checkbox" class="check_all">
				Check All
			</label>
			<select name="action" id="">
				<option value="">-- select --</option>
				<option value="delete">Delete</option>
			</select>
			<button type="submit" class="button">Submit</button>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_pagination() {
		$args       = $this->merge_get_args();
		$table_name = $this->menu_slug;
		$count_all  = $this->get_log_count();
		?>
		<div class="pagination">
			<?php
			echo $this->get_pagination_links( $count_all, $table_name, $args );
			echo "<span class='button'> $count_all items </span>";
			?>
		</div>
		<?php
	}

	function get_pagination_links( $count_all, $page, $args ) {
		$args           = $this->merge_get_args();
		$posts_per_page = $args['posts_per_page'];
		$paged          = $args['paged'];
		$total_pages    = ceil( $count_all / $posts_per_page );

		if ( $total_pages <= 1 ) {
			return '';
		}

		$pagination_args = array(
			'base'               => add_query_arg( array( 'paged' => '%#%', 'posts_per_page' => $posts_per_page ) ),
			'format'             => '?paged=%#%',
			'current'            => max( 1, $paged ),
			'total'              => $total_pages,
			'prev_text'          => '<span class="button item">' . __( 'Previous' ) . '</span>',
			'next_text'          => '<span class="button item">' . __( 'Next' ) . '</span>',
			'type'               => 'array',
			'end_size'           => 2,
			'mid_size'           => 3,
			'before_page_number' => '<span class="button item">',
			'after_page_number'  => '</span>',
		);

		$pagination_links = paginate_links( $pagination_args );

		if ( $pagination_links ) {
			return implode( ' ', $pagination_links );
		}

		return '';
	}

	function get_filters() {
		ob_start();
		$action = admin_url( "options-general.php" );
		?>
		<div class="filters">
			<form action="<?= esc_url( $action ) ?>" method="get">
				<input type="hidden" name="page" value="<?= esc_attr( $this->menu_slug ) ?>">
				<input type="hidden" name="filters_<?= $this->table_name ?>">
				<span>
					Per page:
				</span>
				<input type="text" name="posts_per_page" placeholder="100" value="<?= $_GET['posts_per_page'] ?? "100" ?>">

				<button class="button">Submit</button>
			</form>
			<div class="actions">
				<button class="button box_add_record_button">Add</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_page_url( $args = [] ) {

		$default = [ 
			'page' => "menu_$this->table_name",
		];

		if ( !empty( $args ) ) {
			$default = array_merge( $default, $args );
		}

		return add_query_arg(
			$default,
			admin_url( "options-general.php" )
		);
	}

	function get_box_add_record() {
		$action = $this->get_page_url();
		?>
		<div class="box_add_record hidden">
			<h4>Add new </h4>
			<form action="<?= esc_url( $action ) ?>" method="post">
				<input type="hidden" name="page" value="<?= esc_attr( $this->menu_slug ) ?>">
				<input type="hidden" name="add_record_<?= $this->table_name ?>">
				<input type="hidden" name="nonce" value="<?= wp_create_nonce( $this->table_name ) ?>">
				<div class="form_wrap">
					<?php
					foreach ( (array) $this->fields_array as $key => $field ) {
						?>
						<div class="item">
							<?php $id = 'wp_' . wp_rand(); ?>
							<div>
								<label for="<?= esc_attr( $id ) ?>">
									<?= esc_attr( $field['name'] ) ?>
								</label>
							</div>
							<textarea id="<?= esc_attr( $id ) ?>" <?php if ( $field['name'] == 'id' ) echo 'disabled'; ?>
								name="<?= esc_attr( $field['name'] ) ?>"><?= $_POST[ $field['name'] ] ?? "" ?></textarea>
						</div>
						<?php
					}
					?>
				</div>
				<button class="button">Submit</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_assets() {
		ob_start();
		?>
		<style type="text/css">
			.<?= esc_attr( $this->wrap_id ) ?> h2 small {
				background-color: #ededed;
				font-size: 11px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .filters,
			.<?= esc_attr( $this->wrap_id ) ?> .box_add_record,
			.<?= esc_attr( $this->wrap_id ) ?> .records,
			.<?= esc_attr( $this->wrap_id ) ?> .bulk,
			.<?= esc_attr( $this->wrap_id ) ?> .sql,
			.<?= esc_attr( $this->wrap_id ) ?> .pagination,
			.<?= esc_attr( $this->wrap_id ) ?> .note {
				background-color: white;
				margin-bottom: 20px;
				padding: 10px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .hidden {
				display: none !important;
				visibility: hidden !important;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .filters {
				display: flex;
				gap: 10px;
				justify-content: space-between;
			}

			.<?= esc_attr( $this->wrap_id ) ?> th small {
				display: block;
				font-weight: normal;
				font-size: 0.8;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .bulk,
			.pagination {
				width: 50%;
				display: inline-block;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .pagination {
				text-align: right;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .pagination .page-numbers {
				margin-right: 1px;
				box-shadow: unset !important;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .pagination .page-numbers.current .button {
				background-color: #2271b1;
				color: white;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records {
				overflow: auto;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table {
				border-collapse: collapse;
				width: 100%;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr {}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr th {
				border: 1px solid lightgray;
				padding: 5px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr td {
				border: 1px solid lightgray;
				padding: 5px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr td span {
				border: 2px solid transparent;
				width: 100%;
				display: block;
				box-sizing: border-box;
				border-radius: 3px;
				word-break: break-all;
				max-height: 100px;
				overflow: hidden;
				min-width: 170px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr td span:hover {
				border-color: #2271b1;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .records table tr td textarea {
				width: 100%;
				min-height: 46px;
				border: 2px solid #2271b1;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .bot {
				display: flex;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .status200 {
				padding: 3px;
				border-radius: 3px;
				background-color: #ededed;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .status500 {
				padding: 3px;
				border-radius: 3px;
				background-color: red;
				color: white;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .note {
				display: flex;
				gap: 10px;
				justify-content: space-between;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .box_add_record .form_wrap {
				display: flex;
				flex-wrap: wrap;
				flex-direction: row;
				gap: 10px;
				margin-bottom: 10px;
			}

			.<?= esc_attr( $this->wrap_id ) ?> .box_add_record .form_wrap .item {}

			.<?= esc_attr( $this->wrap_id ) ?> .box_add_record .form_wrap .item textarea {
				width: 100%;
			}
		</style>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.<?= esc_attr( $this->wrap_id ) ?>').forEach(wrap => {
					const check_all = wrap.querySelector(".check_all");
					if (check_all) {
						const all_other_checks = document.querySelectorAll('[name="ids[]"]');
						check_all.addEventListener('change', function () {
							const isChecked = check_all.checked;
							all_other_checks.forEach(function (checkbox) {
								checkbox.checked = isChecked;
							});
						});
					}

					const spans = wrap.querySelectorAll(".span").forEach((span) => {
						span.addEventListener("click", function () {
							span.classList.add("hidden");
							const textarea = span.closest("td").querySelector(".textarea");
							textarea.classList.remove('hidden');
							textarea.focus();
						});
					});

					wrap.querySelectorAll(".textarea").forEach((textarea) => {
						// click event
						textarea.addEventListener('change', function () {
							const table_name = textarea.closest('table').getAttribute('data-table-name');
							const field_name = textarea.getAttribute('name');
							const field_id = textarea.closest('tr').querySelector('[name="id"]').value;
							const field_value = textarea.value;

							// Fetch 
							(async () => {
								try {
									const url = '<?= admin_url( 'admin-ajax.php' ) ?>';
									const formData = new FormData();
									formData.append('action', "<?= $this->table_name ?>_update_data");
									formData.append('table_name', table_name);
									formData.append('field_id', field_id);
									formData.append('field_name', field_name);
									formData.append('field_value', field_value);
									formData.append('nonce', '<?= wp_create_nonce( $this->table_name ) ?>');
									// console.log('Before Fetch:', formData.get('data');

									const response = await fetch(url, {
										method: 'POST',
										body: formData,
									});

									if (!response.ok) {
										throw new Error('Network response was not ok');
									}

									const data = await response.json(); // reponse.text()
									console.log(data);
									if (data.success) {
										textarea.classList.add('hidden');
										textarea.closest('td').querySelector('span').classList.remove('hidden');
										textarea.closest('td').querySelector('span').textContent = data.data;
									} else {
									}
								} catch (error) {
									console.error('Fetch error:', error);
								}
							})();
						});
						// blur event
						textarea.addEventListener('blur', function () {
							textarea.classList.add('hidden');
							textarea.closest('td').querySelector('span').classList.remove('hidden');
						});
					});

					wrap.querySelector('.box_add_record_button').addEventListener('click', function () {
						wrap.querySelector('.box_add_record').classList.toggle('hidden');
					});
				});
			});
		</script>
		<?php
		return ob_get_clean();
	}

	function get_note() {
		ob_start();
		?>
		<div class="note">
			<small>
				<?php
				$reset_link = $this->get_page_url(
					[ 
						'reset_' . $this->table_name => 1,
					]
				);
				?>
				Link to reset table: <a href="<?= ( $reset_link ) ?>">Link</a>
			</small>
			<small>
				Version: <?= esc_attr( $this->version ) ?>
			</small>
		</div>
		<?php
		return ob_get_clean();
	}
}



// $database = new \IctpAds\Helper\DatabaseTable(
// 	[ 
// 		'table_name' => $this->table_name,
// 		'fields'     => $this->fields,
// 	]
// );