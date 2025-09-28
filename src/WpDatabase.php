<?php

namespace WpDatabaseHelper;

class WpDatabase {
    private $version;
    private $wp_parent_slug = 'tools.php';
    private $wp_user_role = 'administrator';

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
    public $records = [];
    public $records_count;

    function __construct() {
        $this->version = $this->getVersion();
    }

    private function getVersion() {
        $composerFile = __DIR__ . '/../composer.json';
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            return $composerData['version'] ?? '0.0.0';
        }
        return '0.0.0';
    }

    function enqueue() {
        $plugin_url = plugins_url('', __DIR__) . "/assets";

        // Check if the script is already enqueued to avoid adding it multiple times
        // if (wp_script_is('wpdatabasehelper-database-js', 'enqueued')) {
        //     return;
        // }

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

        static $inline_added = false;
        if (!$inline_added) {
            wp_add_inline_script(
                'wpdatabasehelper-database-js',
                'const wpdatabasehelper_database = ' . json_encode(
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce($this->table_name),
                        'update_action_name' => $this->table_name . "_update_data"
                    )
                ),
                'before'
            );
            $inline_added = true;
        }
    }

    function init_table($args = []) {

        // if ( did_action( 'init' ) ) {
        // 	exit( 'Do not run after init' );
        // }

        // default
        $this->init_table_data($args);
        add_action('init', [$this, 'create_table_sql']);

        if ($this->is_current_table_page()) {
            // đưa vào init để tương thích với plugin được đặt trong mu-plugins
            add_action('init', [$this, 'init_table_actions']);
            add_action('init', [$this, 'init_query_args']);
            add_action('init', [$this, 'init_records']);
        }

        // ajax
        add_action('admin_menu', [$this, 'create_table_view']);
        add_action('wp_ajax_' . $this->table_name . '_update_data', [$this, 'create_ajax']);
    }

    function init_table_data($args = []) {
        global $wpdb;
        foreach ((array) $args as $key => $value) {
            $this->$key = $value;
        }
        $this->table_name = $wpdb->prefix . $this->table_name;
        $this->fields_sql = implode(" ", (array) $this->fields);
        $this->fields_array = $this->get_fields_array();
        $this->menu_slug = 'menu_' . $this->table_name;
        $this->wrap_id = $this->table_name . rand();
    }

    function create_table_sql() {
        global $wpdb;

        $table_name = esc_sql($this->table_name);
        $query = $wpdb->prepare("SHOW TABLES LIKE %s", $table_name);
        $found_table_name = $wpdb->get_var($query);

        if ($found_table_name != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $fields_sql = $this->fields_sql;
            $sql = "CREATE TABLE {$table_name} ({$fields_sql}) {$charset_collate};";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    function delete_table_sql() {
        global $wpdb;
        require_once(ABSPATH . 'wp-includes/pluggable.php');
        $table_name_safe = esc_sql($this->table_name);
        $sql = "DROP TABLE IF EXISTS `{$table_name_safe}`";
        $wpdb->query($sql);
    }

    function create_table_view() {
        add_submenu_page(
            $this->wp_parent_slug,
            $this->menu_title,
            "[DB] $this->menu_title",
            $this->wp_user_role,
            $this->menu_slug,
            [$this, 'html']
        );
    }

    function create_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], $this->table_name)) exit;
        $return = false;

        ob_start();

        // code here
        $data = [];
        $post = $_POST;

        if (isset($post['field_id'])) {
            $data['id'] = $post['field_id'];
        }

        // value
        if (isset($post['field_id']) and isset($post['field_value'])) {
            $_value = $post['field_value'];
            $_key = $post['field_name'];
            $data[$_key] = $_value;

            if ($this->update($data)) {
                echo apply_filters("{$this->table_name}_{$_key}", $_value);
            }
        }

        $return = ob_get_clean();
        if (!$return) {
            wp_send_json_error('Error');
            wp_die();
        }

        wp_send_json_success($return);
        wp_die();
    }

    function init_table_actions() {
        global $wpdb;
        if (current_user_can($this->wp_user_role)) {

            // reset table
            if (isset($_GET['reset_' . $this->table_name])) {
                $this->delete_table_sql();
                $this->create_table_sql();
                wp_redirect($this->get_page_url()); // reset link
            }

            // add new
            if (isset($_POST['add_record_' . $this->table_name])) {
                if (wp_verify_nonce($_POST['nonce'], $this->table_name)) {
                    $_post = array_filter($_POST);
                    $this->insert($_post);
                    wp_redirect($this->get_page_url()); // reset link
                    exit;
                }
            }

            // search
            // if (isset($_GET['search_' . $this->table_name])) {
            // $this->query_args['where_conditions'] = 'like';
            // }

            // delete
            if (($_POST['action'] ?? "") == 'delete') {
                if (isset($_POST[$this->table_name])) {
                    if (wp_verify_nonce($_POST['nonce'], $this->table_name)) {
                        if ($_POST['ids'] ?? "") {
                            $this->delete($_POST['ids']);
                        }
                    }
                }
            }
        }
    }

    // parse with get params
    function init_query_args($args = []) {

        // default from get params
        $defaults = [
            'where' => [],
            'order' => esc_attr($_GET['order'] ?? 'DESC'),
            'order_by' => esc_attr($_GET['order_by'] ?? 'id'),
            'posts_per_page' => (int) ($_GET['posts_per_page'] ?? 100),
            'paged' => (int) ($_GET['paged'] ?? 1),
            'where_conditions' => $_GET['where_conditions'] ?? '='
        ];

        // add fields on params
        foreach ((array) $this->fields_array as $key => $value) {
            $name = $value['name'] ?? '';
            if ($name and isset($_GET[$name]) and $_GET[$name]) {
                $defaults['where'][$name] = $_GET[$name];
            }
        }

        if (!$defaults['posts_per_page']) {
            $defaults['posts_per_page'] = 100;
        }

        if (!$defaults['paged']) {
            $defaults['paged'] = 1;
        }

        // parse with $args
        $this->query_args = wp_parse_args($args, $defaults);
        // echo "<pre>"; print_r($this->query_args); echo "</pre>";
        // die;
        return $this->query_args;
    }

    function get_fields_array() {
        $return = [];
        foreach ((array) $this->fields as $key => $string) {
            if (
                stripos($string, 'UNIQUE') === 0 ||
                stripos($string, 'PRIMARY') === 0 ||
                stripos($string, 'INDEX') === 0 ||
                stripos($string, 'FOREIGN') === 0
            ) {
                continue;
            }

            $string = trim($string);
            $name = explode(" ", $string)[0] ?? "";
            $type = explode(" ", $string)[1] ?? "";
            $return[] = [
                'name' => $name,
                'type' => $type,
            ];
        }
        return $return;
    }

    function init_records() {
        $this->records = $this->read($this->query_args);
        $this->records_count = $this->read_count($this->query_args);
    }

    // CRUD
    function create($data) {
        return $this->insert($data);
    }

    function get($args, $show_sql = false) {
        return $this->read($args, $show_sql);
    }

    function set_sql($_args) {
        global $wpdb;
        $args = $this->query_args;

        // only for call as direct
        if (empty($args)) {
            $args = $this->init_query_args($_args);
        }

        $sql = "SELECT * FROM $this->table_name WHERE 1=1";

        // fields in $args['where]
        if (!empty($args['where'])) {

            // OLD - simple
            // $args = [ 'where' => [ 'a' => 1, 'b' => 2, ] ];
            if (!isset($args['where'][0])) {
                $where = [];
                foreach ($args['where'] as $field => $value) {
                    switch (($args['where_conditions'] ?? '')) {
                        case 'like':
                            $tmp = "$field like '%$value%'";
                            break;

                        case '=':
                            $tmp = "$field = '$value'";
                            break;

                        default:
                            $tmp = "$field = '$value'";
                            break;
                    }
                    // echo "<pre>"; print_r($tmp); echo "</pre>"; die;
                    $where[] = $tmp;
                }
                $where_sql = implode(" AND ", $where);
                $sql .= " AND ($where_sql)";
            }

            // New - like wp query meta_query
            // $args = [ 'where' => [ 'relation' => 'AND', [ 'key' => 'xxx', 'value' => 'yyy', 'type' => 'CHAR', 'compare' => '=' ] ] ];
            if (isset($args['where'][0])) {
                $relation = $args['where']['relation'] ?? '';
                $where = [];
                foreach ($args['where'] as $key => $value) {
                    if ($key == 'relation') {
                        continue;
                    } else {

                        // data type
                        if (in_array($value['type'], ['CHAR', 'VARCHAR', 'TEXT', 'DATETIME', 'DATE', 'TIME'])) {
                            $value_type = '%s';
                        } elseif (in_array($value['type'], ['INT', 'BIGINT', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'YEAR'])) {
                            $value_type = '%d';
                        } elseif (in_array($value['type'], ['DECIMAL', 'FLOAT', 'DOUBLE'])) {
                            $value_type = '%f';
                        } elseif (in_array($value['type'], ['BINARY', 'VARBINARY'])) {
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
                $where_sql = implode(" AND ", $where);
                $sql .= " $relation ($where_sql)";
            }
        }

        // fields in $args
        foreach ((array) $this->fields_array as $key => $value) {
            if (array_key_exists(($value['name'] ?? ''), $args)) {
                $_name = $value['name'];
                $_value = $args[$value['name']];
                $sql .= " AND ($_name = '$_value')";
            }
        }

        // order by and order
        $sql .= " ORDER BY " . esc_sql($args['order_by']) . " " . esc_sql($args['order']);

        // paged and post_per_page
        if ($args['posts_per_page'] > 0) {
            $offset = ($args['paged'] - 1) * $args['posts_per_page'];
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['posts_per_page'], $offset);
        }

        // echo "<pre>"; print_r($sql); echo "</pre>";die;
        return $sql;
    }

    function read_count($args) {

        // Kiểm tra và lấy SQL
        if (!$this->sql) {
            $this->sql = $this->set_sql($args);
        }

        // count(*)
        $sql_count = preg_replace('/^SELECT .*? FROM/', 'SELECT COUNT(*) FROM', $this->sql);

        // order by , limit, offset
        $sql_count = preg_replace('/ORDER BY .*/', '', $sql_count);
        $sql_count = preg_replace('/LIMIT .*/', '', $sql_count);
        $sql_count = preg_replace('/OFFSET .*/', '', $sql_count);

        global $wpdb;
        return $wpdb->get_var($sql_count);
    }

    function read($args, $show_sql = false) {
        // get sql
        $this->sql = $this->set_sql($args);

        // for debug
        if ($show_sql) {
            echo "<div class=sql><code>$this->sql</code></div>";
        }

        global $wpdb;
        return $wpdb->get_results($this->sql, ARRAY_A);
    }

    function update($data, $modified_date_column = false) {
        global $wpdb;
        $_data = $data;

        if ($modified_date_column) {
            $_data = array_merge($data, array($modified_date_column => current_time('mysql')));
        }

        $result = $wpdb->update(
            $this->table_name,
            $_data,
            [
                'id' => $data['id'],
            ],
        );

        if (is_wp_error($result)) {
            var_dump($result);
            return $result;
        }

        return $data['id'];
    }

    function delete($ids) {
        global $wpdb;
        $ids = (array) $ids;

        foreach ((array) $ids as $key => $id) {
            $wpdb->delete(
                $this->table_name,
                array('id' => $id),
                array('%d')
            );
        }
    }

    function insert($data, $created_date_column = false) {
        global $wpdb;

        // get list columns to make sure on listed columns sql as insert
        $columns = [];
        foreach ((array) $this->fields_array as $key => $value) {
            $columns[] = $value['name'];
        }

        // prepare $data
        $_data = [];
        foreach ((array) $data as $key => $value) {
            if (in_array($key, $columns)) {
                $_data[$key] = $value;
            }
        }

        if ($created_date_column) {
            $_data[$created_date_column] = current_time('mysql');
        }

        // override id 
        $_data['id'] = '';

        // run
        $inserted = $wpdb->insert(
            $this->table_name,
            $_data,
        );

        if (!$inserted) {
            die($wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    function upsert($data, $unique_column) {
        global $wpdb;
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE $unique_column = %s",
                $data[$unique_column]
            )
        );

        if ($existing_id) {
            $data['id'] = $existing_id;
            return $this->update($data);
        } else {
            return $this->insert($data);
        }
    }

    function get_by_id($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $this->table_name WHERE id = %d", $id),
            ARRAY_A
        );
    }

    function get_last_run() {
        global $wpdb;
        $sql = $wpdb->prepare(
            "
			SELECT DISTINCT(date)
			FROM $this->table_name 
			order by date desc
			limit 1
			"
        );
        $result = $wpdb->get_var($sql);
        return $result;
    }

    function html() {
        if (!current_user_can($this->wp_user_role)) {
            wp_die(__('You do not have permission to manage this page.', 'text-domain'));
        }

        $this->enqueue();

        $class = $this->wrap_id;
        $menu_title = $this->menu_title;
        $table_name = $this->table_name;
        $nonce = wp_create_nonce($this->table_name);

        echo <<<HTML
<div class="wpdatabasehelper_wrap wrap $class">
<h2>
$menu_title
<small>$table_name</small>
</h2>

<!-- html -->
<div class="wrap_inner">

<!-- navigation -->
{$this->get_navigation()}

<!-- add -->
{$this->get_box_add_record()}

<!-- filter -->
{$this->get_search_form()}

<!-- search results -->
{$this->get_search_count()}

<form action="" method="post">
<input type="hidden" name="{$this->table_name}">
<input type="hidden" name="nonce" value="{$nonce}">
{$this->get_table_items()}
<div class="section bot">
{$this->get_bulk_edit()}
{$this->get_pagination()}
</div>
{$this->get_note()}
</form>
</div>
</div>
HTML;
        return;
    }

    function get_table_items() {
        $table_name = esc_attr($this->table_name);

        $thead = '';
        foreach ($this->fields_array as $field) {
            $field_name = esc_html($field['name']);
            $field_type = esc_html($field['type']);
            $thead .= <<<HTML
<th>
{$field_name}
<small>{$field_type}</small>
</th>
HTML;
        }

        $tbody = '';
        foreach ((array) $this->records as $record) {
            $row = "<td><input type='checkbox' name='ids[]' value='" . esc_attr($record['id']) . "'></td>";
            foreach ((array) $record as $key => $value) {
                $escaped_value = esc_html($value ?? '');
                $textarea_value = esc_textarea($value ?? '');
                $filtered_value = apply_filters("{$table_name}_{$key}", $escaped_value);
                $row .= <<<HTML
<td>
<span class="span">{$filtered_value}</span>
<textarea class="textarea hidden" name="{$key}">{$textarea_value}</textarea>
</td>
HTML;
            }
            $tbody .= "<tr>{$row}</tr>";
        }

        return <<<HTML
<div class="section records">
<table class="widefat striped" data-table-name="{$table_name}">
<tr>
<th></th>
{$thead}
</tr>
{$tbody}
</table>
</div>
HTML;
    }

    function get_bulk_edit() {
        return <<<HTML
<div class="bulk">
<label for="ac">
<input id="ac" type="checkbox" class="check_all">
Check All
</label>
<select name="action">
<option value="">-- select --</option>
<option value="delete">Delete</option>
</select>
<button type="submit" class="button">Submit</button>
</div>
HTML;
    }

    function get_pagination() {
        $args = $this->query_args;
        $table_name = esc_attr($this->menu_slug);
        $count = esc_html($this->records_count);

        $pagination_links = $this->get_pagination_links($count, $table_name, $args);

        return <<<HTML
<div class="pagination">
{$pagination_links}
<span class="button">{$count} record(s)</span>
</div>
HTML;
    }

    function get_pagination_links($count_all, $page, $args) {
        $args = $this->query_args;
        $posts_per_page = $args['posts_per_page'];
        $paged = $args['paged'];
        $total_pages = ceil($count_all / $posts_per_page);

        if ($total_pages <= 1) {
            return '';
        }

        $pagination_args = array(
            'base' => add_query_arg(array('paged' => '%#%', 'posts_per_page' => $posts_per_page)),
            'format' => '?paged=%#%',
            'current' => max(1, $paged),
            'total' => $total_pages,
            'prev_text' => '<span class="button item">' . __('Previous') . '</span>',
            'next_text' => '<span class="button item">' . __('Next') . '</span>',
            'type' => 'array',
            'end_size' => 2,
            'mid_size' => 3,
            'before_page_number' => '<span class="button item">',
            'after_page_number' => '</span>',
        );

        $pagination_links = paginate_links($pagination_args);

        if ($pagination_links) {
            return implode(' ', $pagination_links);
        }

        return '';
    }

    function get_search_form() {
        $action = esc_url(admin_url($this->wp_parent_slug));
        $menu_slug = esc_attr($this->menu_slug);
        $search_key = "search_" . esc_attr($this->table_name);

        $classes = ['section', 'filters'];
        if (!isset($_GET[$search_key])) {
            $classes[] = 'hidden';
        }
        $class_attr = esc_attr(implode(" ", $classes));

        // Input fields
        $fields_html = "";
        foreach ((array) $this->fields_array as $key => $value) {
            $id = wp_rand();
            $field_name = esc_attr($value['name']);
            $field_value = esc_textarea(stripslashes($_GET[$field_name] ?? ""));
            $fields_html .= <<<HTML
<div class="per_page item">
<div>
<label for="{$id}">{$field_name}</label>
</div>
<textarea id="{$id}" name="{$field_name}">{$field_value}</textarea>
</div>
HTML;
        }

        // Posts per page field
        $id = wp_rand();
        $posts_per_page = esc_textarea($_GET['posts_per_page'] ?? "100");
        $posts_per_page_html = <<<HTML
<div class="per_page item">
<div>
<label for="{$id}">Posts per page</label>
</div>
<textarea id="{$id}" name="posts_per_page">{$posts_per_page}</textarea>
</div>
HTML;

        return <<<HTML
<div class="{$class_attr}">
<h4>Filters</h4>
<form action="{$action}" method="get">
<input type="hidden" name="page" value="{$menu_slug}">
<input type="hidden" name="{$search_key}">
<input type="hidden" name="where_conditions" value="like">
<div class="form_wrap">
{$fields_html}
{$posts_per_page_html}
</div>
<button class="button">Submit</button>
</form>
</div>
HTML;
    }

    function get_search_count() {
        if (!isset($_GET['search_' . $this->table_name])) {
            return;
        }

        $records_count = intval($this->records_count);
        $found_text = sprintf(esc_html__("Found %d record(s)", "text-domain"), $records_count);

        return <<<HTML
<div class="section search_count">
{$found_text}
</div>
HTML;
    }

    function get_navigation() {
        $sql = esc_html($this->sql);
        $search_text = esc_html__('Search', 'text-domain');
        $add_text = esc_html__('Add', 'text-domain');

        return <<<HTML
<div class="section navigation">
<code>{$sql}</code>
<div class="actions">
<button class="button box_show_filter">{$search_text}</button>
<button class="button button-primary box_add_record_button">{$add_text}</button>
</div>
</div>
HTML;
    }

    function get_page_url($args = []) {

        $default = [
            'page' => "menu_$this->table_name",
        ];

        if (!empty($args)) {
            $default = array_merge($default, $args);
        }

        return add_query_arg(
            $default,
            admin_url($this->wp_parent_slug)
        );
    }

    function get_box_add_record() {

        $classes = ['section', 'box_add_record'];
        if (!isset($_GET["add_record_{$this->table_name}"])) {
            $classes[] = 'hidden';
        }

        $form_action = esc_url($this->get_page_url());
        $menu_slug = esc_attr($this->menu_slug);
        $nonce = esc_attr(wp_create_nonce($this->table_name));
        $class_list = esc_attr(implode(" ", $classes));

        $return = '';
        $return .= <<<HTML
<div class="$class_list">
<h4>Add new xxxxx</h4>
<form action="$form_action" method="post">
<input type="hidden" name="page" value="$menu_slug">
<input type="hidden" name="add_record_{$this->table_name}">
<input type="hidden" name="nonce" value="$nonce">

<div class="form_wrap">
HTML;

        foreach ((array) $this->fields_array as $field) {
            $field_name = esc_attr($field['name']);
            $field_value = esc_attr($_POST[$field['name']] ?? "");
            $id = esc_attr('wp_' . wp_rand());
            $disabled = ($field['name'] === 'id') ? 'disabled' : '';

            $return .= <<<HTML
<div class="item">
<div>
<label for="$id">$field_name</label>
</div>
<textarea id="$id" name="$field_name" $disabled>$field_value</textarea>
</div>
HTML;
        }

        $return .= <<<HTML
</div>
<button class="button">Submit</button>
</form>
</div>
HTML;

        return $return;
    }

    function get_note() {
        // Chỉ hiển thị trên localhost
        if ($_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
            return;
        }

        $reset_link = esc_url($this->get_page_url(["reset_{$this->table_name}" => 1]));
        $version = esc_attr($this->version);
        return <<<HTML
<div class="note">
<small>
Link to reset table: <a href="$reset_link">Submit</a>
</small>
<small>
Version: $version
</small>
</div>
HTML;
    }

    function is_current_table_page() {
        if (!is_admin()) {
            return;
        }
        return (($_GET['page'] ?? '') == $this->menu_slug);
    }
}
