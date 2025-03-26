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
        if (file_exists($composerFile)) {
            $composerData = json_decode(file_get_contents($composerFile), true);
            return $composerData['version'] ?? '0.0.0';
        }
        return '0.0.0';
    }

    function enqueue() {
        $plugin_url = plugins_url('', __DIR__) . "/assets";

        // Return early if the script is already enqueued
        if (wp_script_is('wpdatabasehelper-meta-js', 'enqueued')) {
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
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('wpdatabasehelper_meta_js'),
                )
            ),
            'before'
        );
    }

    // post_type
    public $post_type;
    public $metabox_label;
    public $metabox_description;
    public $meta_fields;
    public $register_post_meta;
    public $admin_post_columns;
    public $admin_post_metabox;
    public $quick_edit_post;

    // term taxonomy
    public $taxonomy;
    public $taxonomy_admin_post_columns;
    public $taxonomy_meta_fields;
    public $register_term_meta;

    function init($args) {

        // copy args to properties
        foreach ((array) $args as $key => $value) {
            $this->$key = $value;
        }

        $this->id = $this->name . "_" . sanitize_title($this->metabox_label ?? '');
    }

    // init all options
    function init_meta() {
        $this->init_register_post_meta();
        $this->init_admin_columns();
        $this->init_metabox();
    }

    // init all options
    function init_meta_term_taxonomy() {
        $this->init_register_term_meta();
        $this->init_admin_term_taxonomy_columns();
        $this->init_term_taxonomy_metabox();
    }

    function init_register_post_meta() {
        add_action('init', [$this, 'register_post_meta']);
    }

    function init_register_term_meta() {
        add_action('init', [$this, 'register_term_meta']);
    }

    function init_admin_columns() {
        add_action('wp_ajax_wpmeta_edit__', [$this, 'wpmeta_edit__']);
        add_action('admin_init', [$this, 'make_admin_columns']);
    }

    function init_admin_term_taxonomy_columns() {
        add_action('wp_ajax_wpmeta_edit_term_taxonomy__', [$this, 'wpmeta_edit_term_taxonomy__']);
        add_action('admin_init', [$this, 'make_admin_term_taxonomy_columns']);
    }

    function init_metabox() {
        add_action('admin_init', [$this, 'make_metabox']);
    }

    function init_term_taxonomy_metabox() {
        add_action('admin_init', [$this, 'make_term_taxonomy_metabox']);
    }

    function wpmeta_edit__() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpdatabasehelper_meta_js')) exit;

        // echo "<pre>"; var_dump($_POST); echo "</pre>"; 

        $meta_value = esc_attr($_POST['meta_value']);
        if ($_POST['meta_value_is_json'] == 'true') {
            $meta_value = json_decode(stripslashes($_POST['meta_value']));
        }

        $object_id = $_POST['object_id'];
        $meta_key  = $_POST['meta_key'];

        update_post_meta(
            esc_attr($object_id),
            esc_attr($meta_key),
            $meta_value,
        );
        error_log(__FUNCTION__ . ": $object_id | $meta_key | $meta_value");

        wp_send_json_success(
            $this->init_meta_value(
                json_decode(stripslashes($_POST['args']), true),
                $meta_value
            )
        );

        wp_die();
    }

    function wpmeta_edit_term_taxonomy__() {
        if (!wp_verify_nonce($_POST['nonce'], 'wpdatabasehelper_meta_js')) exit;

        $meta_value = esc_attr($_POST['meta_value']);
        if ($_POST['meta_value_is_json'] == 'true') {
            $meta_value = json_decode(stripslashes($_POST['meta_value']));
        }

        $object_id = $_POST['object_id'];
        $meta_key  = $_POST['meta_key'];

        update_term_meta(
            esc_attr($object_id),
            esc_attr($meta_key),
            $meta_value,
        );
        error_log(__FUNCTION__ . ": $object_id | $meta_key | $meta_value");

        wp_send_json_success(
            $this->init_meta_value(
                json_decode(stripslashes($_POST['args']), true),
                $meta_value
            )
        );

        wp_die();
    }

    function parse_args($args) {
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

        $return = wp_parse_args($args, $default);
        // echo "<pre>"; print_r($return); echo "</pre>";die;
        return $return;
    }

    function register_term_meta() {
        if (!$this->register_term_meta) {
            return;
        }

        // nothing
    }

    function register_post_meta() {
        if (!$this->register_post_meta) {
            return;
        }

        foreach ((array) $this->meta_fields as $key => $value) {

            $type         = 'string';
            $meta_key     = $value['meta_key'];
            $show_in_rest = false;

            // checkbox
            if (($value['attribute']['type'] ?? '') == 'checkbox') {
                if (count($value['options'] ?? []) > 1) {
                    $type = 'array';
                }
            }

            register_post_meta($this->post_type, $meta_key, array(
                'type'              => $type,
                'show_in_rest'      => $show_in_rest,
                'single'            => true,
                'sanitize_callback' => false,
                'auth_callback'     => function () {
                    return current_user_can('edit_posts');
                }
            ));
        }
    }

    function make_admin_columns() {
        if (!$this->admin_post_columns) {
            return;
        }

        add_filter('manage_' . $this->post_type . '_posts_columns', function ($columns) {
            $insert = [];

            // prepare array
            foreach ((array) $this->meta_fields as $key => $value) {
                $args = $this->parse_args($value);
                if ($args['admin_column']) {
                    $insert[$value['meta_key']] = esc_html($args['label'] ?? $value['meta_key']);
                }
            }

            // position
            $position = array_search('title', array_keys($columns), true);
            if ($position === false) {
                $position = array_search('price', array_keys($columns), true);
            }

            //
            if ($position !== false) {
                $columns = array_slice($columns, 0, $position + 1, true) +
                    $insert +
                    array_slice($columns, $position + 1, null, true);
            } else {
                $columns = $insert + $columns;
            }

            return $columns;
        }, 11, 1); // 11 for compatity with woocommerce



        add_action('manage_' . $this->post_type . '_posts_custom_column', function ($column, $post_id) {
            foreach ((array) $this->meta_fields as $key => $field_args) {
                if ($field_args['meta_key'] == $column) {
                    echo $this->quick_edit_field($field_args, $post_id);
                }
            }
        }, 10, 2);
    }

    function make_admin_term_taxonomy_columns() {
        if (!$this->taxonomy_admin_post_columns) {
            return;
        }

        add_filter('manage_edit-' . $this->taxonomy . '_columns', function ($columns) {
            $insert = [];

            // prepare array
            foreach ((array) $this->taxonomy_meta_fields as $key => $value) {
                $args = $this->parse_args($value);
                if ($args['admin_column']) {
                    $insert[$value['meta_key']] = esc_html($args['label'] ?? $value['meta_key']);
                }
            }

            // position
            $position = array_search('name', array_keys($columns), true);

            //
            if ($position !== false) {
                $columns = array_slice($columns, 0, $position + 1, true) +
                    $insert +
                    array_slice($columns, $position + 1, null, true);
            } else {
                $columns = $insert + $columns;
            }

            return $columns;
        }, 11, 1); // 11 for compatity with woocommerce

        add_action('manage_' . $this->taxonomy . '_custom_column', function ($content, $column, $term_id) {
            foreach ((array) $this->taxonomy_meta_fields as $key => $field_args) {
                if ($field_args['meta_key'] == $column) {
                    echo $this->quick_edit_field_term_taxonomy($field_args, $term_id);
                }
            }
        }, 10, 3);
    }

    function quick_edit_field($field_args, $post_id) {
        $args       = $this->parse_args($field_args);
        $meta_key   = $field_args['meta_key'];
        $meta_value = get_post_meta($post_id, $meta_key, true);
        ob_start();
?>
        <form action="">
            <div data-action="wpmeta_edit__" data-meta_key="<?= esc_attr($args['meta_key']); ?>"
                data-object_id="<?= esc_attr($post_id) ?>"
                data-args="<?= esc_attr(json_encode($args, JSON_UNESCAPED_UNICODE)) ?>"
                class="<?= esc_attr($this->name) ?>_quick_edit">
                <div class="quick_edit_value">
                    <?php echo $this->init_meta_value($field_args, $meta_value); ?>
                </div>
                <div class="quick_edit_field">
                    <?php echo $this->init_meta_field($args, $meta_value); ?>
                </div>
                <button class="quick_edit_icon button" type="button">
                    <?= __('Edit') ?>
                </button>
            </div>
        </form>
    <?php
        return ob_get_clean();
    }

    function quick_edit_field_term_taxonomy($field_args, $term_id) {
        $args       = $this->parse_args($field_args);
        $meta_key   = $field_args['meta_key'];
        $meta_value = get_term_meta($term_id, $meta_key, true);
        ob_start();
    ?>
        <form action="">
            <div data-action="wpmeta_edit_term_taxonomy__" data-meta_key="<?= esc_attr($args['meta_key']); ?>"
                data-object_id="<?= esc_attr($term_id) ?>"
                data-args="<?= esc_attr(json_encode($args, JSON_UNESCAPED_UNICODE)) ?>"
                class="<?= esc_attr($this->name) ?>_quick_edit">
                <div class="quick_edit_value">
                    <?php echo $this->init_meta_value($field_args, $meta_value); ?>
                </div>
                <div class="quick_edit_field">
                    <?php echo $this->init_meta_field($args, $meta_value); ?>
                </div>
                <button class="quick_edit_icon button" type="button">
                    <?= __('Edit') ?>
                </button>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    function make_term_taxonomy_metabox() {

        $form = function ($term = false) {
            ob_start();
            wp_nonce_field($this->id, "{$this->id}_nonce");
        ?>
            <div class="<?= esc_attr($this->name) ?>-meta-box-container">
                <div class="grid">
                    <?php
                    foreach ($this->taxonomy_meta_fields as $value) {
                        $args       = $this->parse_args($value);
                        $meta_key = $value['meta_key'];
                    ?>
                        <div class="item <?= implode(" ", $args['field_classes']) ?>">
                            <?php
                            $value = '';
                            if (is_object($term) && isset($term->term_id)) {
                                $value = get_term_meta($term->term_id, $meta_key, true);
                            }
                            echo $this->init_meta_field(
                                $args,
                                $value
                            );
                            ?>
                        </div>
                    <?php
                    }
                    ?>
                </div>
                <div class="footer">
                    <small>
                        Version: <?= esc_attr($this->version) ?>
                    </small>
                </div>
            </div>

        <?php
            return ob_get_clean();
        };


        add_action($this->taxonomy . '_edit_form_fields', function ($term) use ($form) {
            ob_start(); ?>
            <tr class="form-field">
                <th scope="row">
                    <label for="parent">
                        <?php echo esc_attr($this->metabox_label) ?>
                        <?php
                        // description
                        if ($this->metabox_description) {
                            echo '<p><small>' . esc_attr($this->metabox_description) . '</small></p>';
                        }
                        ?>
                    </label>
                </th>
                <td>
                    <!-- form here -->
                    <?php echo $form($term) ?>
                </td>
            </tr>

        <?php
            echo ob_get_clean();
        });

        add_action($this->taxonomy . '_add_form_fields', function ($taxonomy) use ($form) {
            ob_start(); ?>
            <div class="form-field">
                <label for="extra_info">
                    <?php echo esc_attr($this->metabox_label) ?>
                    <?php
                    // description
                    if ($this->metabox_description) {
                        echo '<p><small>' . esc_attr($this->metabox_description) . '</small></p>';
                    }
                    ?>
                </label>
                <!-- form here -->
                <?php echo $form(false) ?>
            </div>

            <?php
            echo ob_get_clean();
        });

        $save_term_taxonomy_data_func = function ($term_id, $taxonomy, $args) {
            // verify nonce
            if (
                !isset($_POST["{$this->id}_nonce"]) ||
                !wp_verify_nonce($_POST["{$this->id}_nonce"], $this->id)
            ) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!current_user_can('edit_term', $term_id)) {
                return;
            }

            if ($this->taxonomy != ($args['taxonomy'] ?? '')) {
                return;
            }

            foreach ($this->taxonomy_meta_fields as $value) {
                $args       = $this->parse_args($value);
                $meta_key   = $value['meta_key'];
                $meta_value = $_POST[$meta_key] ?? '';

                if (!is_array($meta_value)) {
                    // sanitize
                    if ($args['field'] == 'textarea') {
                        // becareful with santize before can be change value strings
                        $meta_value = wp_unslash($meta_value);
                    } else {
                        $meta_value = sanitize_text_field($meta_value);
                    }
                }

                update_term_meta(
                    $term_id,
                    $meta_key,
                    $meta_value
                );
                error_log("update_term_meta: $term_id - $meta_key - $meta_value");
            }
        };
        add_action('edited_' . $this->taxonomy, $save_term_taxonomy_data_func, 10, 3);
        add_action('created_' . $this->taxonomy, $save_term_taxonomy_data_func, 10, 3);
    }

    function make_metabox() {
        add_action(
            'add_meta_boxes',
            function () {
                add_meta_box(
                    sanitize_title($this->metabox_label), // ID of the meta box
                    $this->metabox_label, // Title of the meta box
                    function ($post) {
                        // make sure correct post_id
                        if ($_REQUEST['post'] ?? '') {
                            $post = get_post($_REQUEST['post']);
                        }

                        // description
                        if ($this->metabox_description) {
                            echo '<p><small>' . esc_attr($this->metabox_description) . '</small></p>';
                        }

                        wp_nonce_field($this->id, "{$this->id}_nonce");
            ?>
                <div class="<?= esc_attr($this->name) ?>-meta-box-container">
                    <div class="grid">
                        <?php
                        foreach ($this->meta_fields as $value) {
                            $args       = $this->parse_args($value);
                            $meta_key = $value['meta_key'];
                        ?>
                            <div class="item <?= implode(" ", $args['field_classes']) ?>">
                                <?php
                                // echo "<pre>"; print_r($post->ID); echo "</pre>";
                                // echo "<pre>"; print_r($meta_key); echo "</pre>";
                                echo $this->init_meta_field(
                                    $args,
                                    get_post_meta($post->ID, $meta_key, true)
                                );
                                ?>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <div class="footer">
                        <small>
                            Version: <?= esc_attr($this->version) ?>
                        </small>
                    </div>
                </div>
<?php
                    },
                    $this->post_type
                );
            }
        );

        add_action('save_post', function ($post_id) {

            if (!isset($_POST['originalaction'])) {
                return;
            }

            // verify nonce
            if (
                !isset($_POST["{$this->id}_nonce"]) ||
                !wp_verify_nonce($_POST["{$this->id}_nonce"], $this->id)
            ) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            if ($this->post_type != get_post_type($post_id)) {
                return;
            }

            foreach ($this->meta_fields as $value) {
                $args       = $this->parse_args($value);
                $meta_key   = $value['meta_key'];
                $meta_value = $_POST[$meta_key] ?? '';

                if (!is_array($meta_value)) {
                    // sanitize
                    if ($args['field'] == 'textarea') {
                        // becareful with santize before can be change value strings
                        $meta_value = wp_unslash($meta_value);
                    } else {
                        $meta_value = sanitize_text_field($meta_value);
                    }
                }

                update_post_meta(
                    $post_id,
                    $meta_key,
                    $meta_value
                );
                error_log("update_post_meta: $post_id - $meta_key - $meta_value");
            }
        });
    }

    function init_args($args, $meta_value) {
        // parse args
        $args = wp_parse_args($args, [
            'field' => 'input',
        ]);

        // integration
        $args['attribute']['name'] = $args['meta_key'];
        $args['value']             = $meta_value;

        if (str_contains($args['field'], 'input')) {
            $args['attribute']['value'] = $meta_value;
        }

        return $args;
    }

    function init_meta_value($args, $meta_value) {
        $args = $this->init_args($args, $meta_value);
        $a    = \WpDatabaseHelper\Init::WpField();
        $a->setup_args($args);
        return $a->init_field_value();
    }

    function init_meta_field($args, $meta_value) {
        $this->enqueue();
        $args = $this->init_args($args, $meta_value);
        $a    = \WpDatabaseHelper\Init::WpField();
        $a->setup_args($args);
        return $a->init_field();
    }
}
