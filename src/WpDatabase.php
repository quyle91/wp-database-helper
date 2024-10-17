<?php
namespace WpDatabaseHelper;

class WpDatabase {
	private $version;
	// private static $instance = null;
	// public static function get_instance() {
	// 	if ( is_null( self::$instance ) ) {
	// 		self::$instance = new self();
	// 	}
	// 	return self::$instance;
	// }

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
	private $query_args = [];
	private $sql;

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

	function enqueue(){
		$plugin_url = plugins_url( '', __DIR__ ) . "/assets";
		$enqueue_assets = function () use ($plugin_url) {
			// Check if the script is already enqueued to avoid adding it multiple times
			if ( wp_script_is( 'wpdatabasehelper-database-js', 'enqueued' ) ) {
				return;
			}

			wp_enqueue_style(
				'wpdatabasehelper-database-css',
				$plugin_url . "/css/database.css",
				[],
				$this->version,
				'all'
			);

			wp_enqueue_script(
				'wpdatabasehelper-database-js',
				$plugin_url . "/js/database.js",
				[],
				$this->version,
				true
			);

			wp_add_inline_script( 
				'wpdatabasehelper-database-js', 
				'const wpdatabasehelper_database = ' . json_encode( 
					array(
						'ajax_url'           => admin_url( 'admin-ajax.php' ),
						'nonce'              => wp_create_nonce( $this->table_name ),
						'update_action_name' => $this->table_name . "_update_data"
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
	
	function init_table( $args = [] ) {
		$this->init_table_data($args);
		$this->create_table_sql();
		$this->create_table_view();

		if($this->is_current_table_page()){
			$this->merge_query_args();
			$this->create_ajax();
			$this->process_actions();
		}
	}

	function init_table_data($args = []){
		global $wpdb;
		foreach ( (array) $args as $key => $value ) {
			$this->$key = $value;
		}
		$this->table_name = $wpdb->prefix . $this->table_name;
		$this->fields_sql   = implode( " ", (array) $this->fields );
		$this->fields_array = $this->get_fields_array();
		$this->menu_slug    = 'menu_' . $this->table_name;
		$this->wrap_id      = $this->table_name . rand();
	}

	function create_table_sql() {
		global $wpdb;
		$table_name = $this->table_name;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql             = "CREATE TABLE {$table_name} ({$this->fields_sql}) {$charset_collate};";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
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

					$this->create_table_sql();
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
		$args = $this->merge_query_args( $args );

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
		$this->sql = $sql;

		// for debug
		if ( $show_sql ) {
			echo "<div class=sql><code>$this->sql</code></div>";
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

	function create_table_view() {
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

	function merge_query_args($args = []){
		$defaults = [ 
			'where'          => [],
			'order'          => esc_attr( $_GET['order'] ?? 'DESC' ),
			'order_by'       => esc_attr( $_GET['order_by'] ?? 'id' ),
			'posts_per_page' => (int) ( $_GET['posts_per_page'] ?? 100 ),
			'paged'          => (int) ( $_GET['paged'] ?? 1 ),
		];

		// add fields on params
		foreach ((array) $this->fields_array as $key => $value) {
			$name = $value['name'] ?? '';
			if($name and isset($_GET[$name]) and $_GET[ $name ]){
				$defaults['where'][$name] = $_GET[$name];
			}
		}

		if ( !$defaults['posts_per_page'] ) {
			$defaults['posts_per_page'] = 100;
		}

		if ( !$defaults['paged'] ) {
			$defaults['paged'] = 1;
		}

		$this->query_args = wp_parse_args( $args, $defaults );
		return $this->query_args;
	}

	function html() {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( 'Can not manage this page' );
		}
		?>
		<div class="wpdatabasehelper_wrap wrap <?= esc_attr( $this->wrap_id ) ?>">
			<h2>
				<?php echo esc_attr( $this->menu_title ); ?>
				<small><?php echo esc_attr( $this->table_name ); ?></small>
			</h2>

			<!-- assets -->
			<?php echo $this->enqueue(); ?>

			<!-- html -->
			<div class="wrap_inner">

				<?php
					$args    = $this->query_args;
					$records = $this->read( $args );
				?>

				<!-- navigation -->
				<?php echo $this->get_navigation(); ?>

				<!-- add -->
				<?php echo $this->get_box_add_record(); ?>

				<!-- filter -->
				<?php echo $this->get_filters(); ?>

				<form action="" method="post">
					<input type="hidden" name="<?= $this->table_name ?>">
					<?php					
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
		$args       = $this->query_args;
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
		$args           = $this->query_args;
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

	function get_filters(){
		ob_start();
		$action = admin_url( "options-general.php" );
		?>
		<div class="filters hidden">
			<h4>Filters</h4>
			<form action="<?= esc_url( $action ) ?>" method="get">
				<input type="hidden" name="page" value="<?= esc_attr( $this->menu_slug ) ?>">
				<input type="hidden" name="filters_<?= $this->table_name ?>">

				<div class="form_wrap">
					<?php 
						foreach ((array)$this->fields_array as $key => $value) {
							?>
							<div class="per_page item">
								<?php $id = wp_rand(); ?>
								<div>
									<label> 
									<?= esc_attr( $value['name'] ) ?>
									</label>
								</div>
								<textarea 
									id="<?= esc_attr( $id ) ?>" 								
									name="<?= esc_attr( $value['name'] ) ?>"
									><?= $_GET[ $value['name'] ] ?? "" ?></textarea>
							</div>
							<?php
						}
					?>
					<div class="per_page item">
						<?php $id = wp_rand(); ?>
						<div>
							<label>
								<?= esc_attr( 'posts per page' ) ?>
							</label>
						</div>
						<textarea id="<?= esc_attr( $id ) ?>"
							name="<?= esc_attr( 'posts_per_page' ) ?>"><?= $_GET['posts_per_page'] ?? "100" ?></textarea>
					</div>
				</div>
				<button class="button">Submit</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	function get_navigation() {
		ob_start();
		?>
		<div class="navigation">
			<code>
				<?php echo $this->sql; ?>
			</code>
			<div class="actions">
				<button class="button box_show_filter">Filter</button>
				<button class="button button-primary box_add_record_button">Add record</button>
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

	function is_current_table_page(){
		return(($_GET['page'] ?? '') == $this->menu_slug);
	}
}



// $database = new \IctpAds\Helper\DatabaseTable(
// 	[ 
// 		'table_name' => $this->table_name,
// 		'fields'     => $this->fields,
// 	]
// );