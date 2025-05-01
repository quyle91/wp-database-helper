<?php

namespace WpDatabaseHelper;

class WpField {
    public $name = 'WpDatabaseHelper_field';
    private $version;
    public $id;
    public $args = [];

    function __construct() {
        $this->id      = $this->name . "_" . wp_rand();
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
        $plugin_url = plugins_url('', __DIR__);

        // Check if the script is already enqueued to avoid adding it multiple times
        if (wp_script_is('wpdatabasehelper-field-js', 'enqueued')) {
            return;
        }
        wp_enqueue_style(
            'wpdatabasehelper-field-css',
            $plugin_url . "/assets/css/field.css",
            [],
            $this->version,
            'all'
        );

        wp_enqueue_script(
            'wpdatabasehelper-field-js',
            $plugin_url . "/assets/js/field.js",
            [],
            $this->version,
            true
        );

        // Add inline script only once
        wp_add_inline_script(
            'wpdatabasehelper-field-js',
            'const wpdatabasehelper_field_js = ' . json_encode(
                array(
                    'ajax_url'     => admin_url('admin-ajax.php'),
                    'nonce'        => wp_create_nonce('wpdatabasehelper_field_js'),
                    'script_debug' => (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG),
                    'text'         => [
                        'upload'         => __('Upload'),
                        'use_this_media' => __('Choose image'),
                    ],
                )
            ),
            'before'
        );

        // select2
        if ($this->args['is_select2']) {
            wp_enqueue_style(
                'adminz_admin_select2_css',
                $plugin_url . "/assets/vendor/select2/select2.min.css",
                [],
                $this->version,
                'all'
            );

            wp_enqueue_script(
                'adminz_admin_select2_js',
                $plugin_url . "/assets/vendor/select2/select2.min.js",
                [],
                $this->version,
                true,
            );
        }
    }

    function setup_args($args) {
        // echo "<pre>"; print_r($args); echo "</pre>";
        // parse args
        $this->args = wp_parse_args(
            $args,
            [
                'field'          => 'input',
                'value'          => '', // current field
                'attribute'      => [], // see $default_attribute below ..
                'suggest'        => '',
                'before'         => '<div class=___default_wrap>',
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
                'post_select'    => [],
                'term_select'    => [],
                'user_select'    => [],
                'is_select2'      => true,
                'show_copy'      => true,
                'show_copy_key'  => false,
            ]
        );

        // parse args attribute for input
        $default_attribute = [
            'id'    => $this->name . "_" . wp_rand(),
            'class' => [],
            'value' => '',
        ];
        if ($this->args['field'] == 'input') {
            $default_attribute = [
                'id'    => $this->name . "_" . wp_rand(),
                'class' => [],
                'type'  => 'text',
                'value' => '',
            ];
        }
        $this->args['attribute'] = wp_parse_args($args['attribute'], $default_attribute);

        // classes
        $this->args['attribute']['class']   = (array) $this->args['attribute']['class'];
        $this->args['attribute']['class'][] = $this->name;
        if (($this->args['field'] ?? '') == 'input') {
            if ($this->args['attribute']['type'] != 'button') {
                $this->args['attribute']['class'][] = 'regular-text';
            }
        }

        // options term_select
        if (!empty($this->args['term_select'])) {
            $default_term_select       = [
                'taxonomy'       => 'category',
                'option_value'   => 'term_id',
                'option_display' => 'name',
            ];
            $this->args['term_select'] = wp_parse_args($args['term_select'], $default_term_select);
            $options                   = $this->get_options_term_select();
            $this->args['options']     = $options;
        }

        // option post_select
        if (!empty($this->args['post_select'])) {
            $default_post_select       = [
                'post_type'      => 'post',
                'option_value'   => 'ID',
                'option_display' => 'post_title',
            ];
            $this->args['post_select'] = wp_parse_args($args['post_select'], $default_post_select);
            $options                   = $this->get_options_post_select();
            $this->args['options']     = $options;
        }

        // option user_select
        if (!empty($this->args['user_select'])) {
            $default_user_select       = [
                'orderby'        => 'display_name',
                'order'          => 'ASC',
                'number'         => -1,
            ];
            $this->args['user_select'] = wp_parse_args($args['user_select'], $default_user_select);
            $options                   = $this->get_options_user_select();
            $this->args['options']     = $options;
        }

        // textarea
        if ($this->args['field'] == 'textarea') {
            if (!isset($this->args['attribute']['cols'])) {
                $this->args['attribute']['cols'] = 65;
            }
            if (!isset($this->args['attribute']['rows'])) {
                $this->args['attribute']['rows'] = 8;
            }
        }

        // input
        if ($this->args['field'] == 'input') {
            // echo "<pre>";
            // print_r($this);
            // echo "</pre>";
            // die;
            if (!$this->args['attribute']['value']) {
                $this->args['attribute']['value'] = $this->args['value'];
            }

            // checkbox 
            if ($this->args['attribute']['type'] == 'checkbox') {

                // make sure at leat 1 options
                if (empty($this->args['options'])) {
                    $default = ['on' => 'on'];
                    // override default if has attribute[value]
                    if ($this->args['attribute']['value']) {
                        $default = ['on' => $this->args['attribute']['value']];
                    }
                    $this->args['options'] = $default;
                }

                // nếu nhiều hơn 2 giá trị thì cho nó là 1 mảng.
                if (count($this->args['options']) > 1) {
                    $this->args['attribute']['name'] .= '[]';
                }
            }
        }

        // tabs
        if (in_array($this->args['field'], ['tab', 'tab_end', 'tab_nav'])) {
            $this->args['before'] = '';
            $this->args['after'] = '';
        }

        // show_copy_key
        if (
            in_array($this->args['attribute']['type'] ?? '', ['button', 'file', 'checkbox', 'radio', 'file', 'hidden', 'wp_media', 'color'])
        ) {
            $this->args['show_copy'] = false;
        }
    }

    function init_field_value() {
        $html_items          = [];
        $this->args['value'] = (array) $this->args['value'];
        
        // repeater
        if(($this->args['field'] ?? '') == 'repeater'){
            return 'Repeater';
        }

        // post select
        if (!empty($this->args['post_select']['post_type'])) {
            foreach ((array) $this->args['value'] as $key => $value) {
                if ($value) {
                    $html_items[] = "<a target='_blank' href='" . get_edit_post_link($value) . "'>" . get_the_title($value) . "</a>";
                }
            }
        }

        // term select
        elseif (!empty($this->args['term_select']['taxonomy'])) {
            $taxonomy = $this->args['term_select']['taxonomy'];
            foreach ((array) $this->args['value'] as $key => $value) {
                if ($value) {
                    $html_items[] = "<a target='_blank' href='" . get_edit_term_link($value, $taxonomy) . "'>" . get_term($value, $taxonomy)->name . "</a>";
                }
            }
        } elseif (!empty($this->args['options'])) {
            foreach ((array) $this->args['value'] as $key => $value) {
                if ($value) {
                    if (array_key_exists($value, $this->args['options'])) {
                        $html_items[] = $this->args['options'][$value];
                    }
                }
            }
        } elseif ($this->args['field'] == 'input' and ($this->args['attribute']['type'] ?? '') == 'wp_media') {
            foreach ((array) $this->args['value'] as $key => $value) {
                if ($value) {
                    $html_items[] = wp_get_attachment_image(
                        $value,
                        'thumbnail',
                        false,
                        [
                            'style' => 'max-width: 100%; width: 50px; height: auto;  border-radius: 4px; border: 1px solid lightgray;',
                        ]
                    );
                }
            }
        }

        // default
        else {
            foreach ((array) $this->args['value'] as $key => $value) {
                if ($value) {
                    $html_items[] = $value;
                }
            }
        }

        if (!empty($html_items)) {
            return implode(", ", $html_items);
        }

        return '--';
    }

    public function init_field() {
        $this->enqueue();

        $field = $this->args['field'];

        // skip on logics
        if (in_array($field, ['tab', 'tab_end', 'tab_nav'])) {
            return $this->$field();
        }

        $type  = $this->args['attribute']['type'] ?? '';
        $wrap_class = esc_attr(implode(' ', array_filter(array_merge((array) $this->args['wrap_class'], [
            "{$this->name}_wrap",
            "type-$type",
            "field_$field",
            $field != 'repeater' ? 'single_field' : '',
        ]))));
        $label_before = $this->args['label_position'] == 'before' ? $this->get_label() : '';
        $label_after  = $this->args['label_position'] == 'after' ? $this->get_label() : '';
        $field_output = method_exists($this, $field) ? $this->{$field}() : "<mark>{$field} method does not exist</mark>";

        return <<<HTML
        {$this->args['before']}
        <div class="{$wrap_class}">
            {$label_before}
            <div>
                {$field_output}
                {$this->get_copy()}
            </div>
            {$label_after}
        </div>
        {$this->get_note()}
        {$this->get_suggest()}
        {$this->args['after']}
        HTML;
    }

    function tab_nav() {
        ob_start();
        $this->args['attribute']['class'][] = '___tab_nav';
        echo '<div ' . $this->get_attribute() . '>';
        $labels = $this->args['labels'] ?? [];
        foreach ((array)$labels as $key => $label) {
            echo '<button type="button" class="button button-large button-primary" data-id="' . sanitize_title($label) . '">';
            echo esc_attr($label);
            echo '</button>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    function tab() {
        ob_start();
        echo wp_kses_post($this->args['before']);
        $label = $this->args['label'] ?? '';
        $this->args['attribute']['class'][] = '___tab_content';
        $this->args['attribute']['class'][] = 'hidden';
        $this->args['attribute']['data-id'] = sanitize_title($label);
        echo '<div ' . $this->get_attribute() . '>';
        echo '<div class="inner">';
        return ob_get_clean();
    }

    function tab_end() {
        ob_start();
        echo '</div>'; // col inner
        echo '</div>';
        echo $this->get_note();
        echo $this->get_suggest();
        echo wp_kses_post($this->args['after']);
        return ob_get_clean();
    }

    function repeater() {
        $a = \WpDatabaseHelper\Init::WpRepeater();
        $default = $this->args['default'] ?? [];
        $value = $this->args['value'] ?? [];
        $a->current = !empty($value) ? $value : $default;
        $a->prefix = $this->args['meta_key'] ?? '';
        $a->field_configs = $this->args['field_configs'] ?? [];
        return $a->init_repeater();
    }

    function get_attribute($attr_override = false) {
        ob_start();

        $args = $this->args['attribute'] ?? [];
        if ($attr_override) {
            $args = $attr_override;
        }

        foreach ($args as $key => $value) {
            $value = implode(" ", (array) $value);
            echo esc_attr($key) . '="' . esc_attr($value) . '" ';
        }
        return ob_get_clean();
    }

    function select() {
        $attributes = $this->get_attribute();
        $options    = $this->args['options'] ?? [];
        $selected_values = (array) ($this->args['value'] ?? []);

        $options_html = '';
        foreach ($options as $key => $value) {
            $selected = in_array($key, $selected_values) ? 'selected' : '';
            $escaped_key = esc_attr($key);
            $escaped_value = esc_attr($value);

            $options_html .= <<<HTML
        <option value="{$escaped_key}" {$selected}>{$escaped_value}</option>
        HTML;
        }

        return <<<HTML
        <div class="form_field_select">
            <select {$attributes}>
                {$options_html}
            </select>
        </div>
        HTML;
    }

    function textarea() {
        $type = $this->args['attribute']['type'] ?? "text";

        if (method_exists($this, "textarea_" . $type)) {
            return $this->{"textarea_" . $type}();
        }

        $attributes = $this->get_attribute();
        $value = esc_textarea($this->args['value'] ?? '');

        return <<<HTML
        <div class="form_field_textarea">
            <textarea {$attributes}>{$value}</textarea>
        </div>
        HTML;
    }

    function input() {
        $type = $this->args['attribute']['type'] ?? "text";
        if (method_exists($this, "input_" . $type)) {
            return $this->{"input_" . $type}();
        }
        return $this->input_text();
    }

    function input_text() {
        $attributes = $this->get_attribute();
        return <<<HTML
        <input {$attributes}>
        HTML;
    }

    function input_button() {
        $attributes = $this->get_attribute();
        return <<<HTML
        <input {$attributes}>
        HTML;
    }

    function input_color() {
        // Đổi type thành text
        $this->args['attribute']['type'] = 'text';
        $attributes = $this->get_attribute();
        $color_value = $this->args['attribute']['value'] ?? '';

        return <<<HTML
        <div class="form_field_color">
            <input {$attributes}>
            <input type="color" class="colorControl" value="{$color_value}">
        </div>
        HTML;
    }

    function input_range() {
        $input_text = $this->input_text();
        $range_value = $this->args['attribute']['value'] ?? '';

        return <<<HTML
        <div class="form_field_range">
            <div class="input_range_field">{$input_text}</div>
            <div class="input_range_value">{$range_value}</div>
        </div>
        HTML;
    }

    function input_radio() {
        $options = (array) $this->args['options'];
        $selected_value = $this->args['value'] ?? '';
        $radio_buttons = '';

        foreach ($options as $key => $value) {
            $attr_override = $this->args['attribute'];
            $attr_override['value'] = $key;
            $attr_override['id'] .= "_{$key}";

            if ($selected_value == $key) {
                $attr_override['checked'] = 'checked';
            } else {
                unset($attr_override['checked']);
            }

            // echo "<pre>"; print_r($key); echo "</pre>";
            // echo "<pre>"; print_r($attr_override); echo "</pre>";
            $radio_buttons .= <<<HTML
            <div class="item">
                <input {$this->get_attribute($attr_override)}>
                <label class="form_field_label_item" for="{$attr_override['id']}" style="vertical-align: middle;">
                    {$value}
                </label>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="form_field_radio form_field_flex">
            {$radio_buttons}
        </div>
        HTML;
    }

    function input_checkbox() {
        $options = (array) $this->args['options'];
        $selected_values = (array) $this->args['value'];
        $checkbox_buttons = '';

        foreach ($options as $key => $value) {
            if (!$key) {
                continue;
            }

            $attribute = $this->args['attribute'];
            $attribute['value'] = $key;
            $attribute['id'] .= "_{$key}";

            if (in_array($key, $selected_values)) {
                $attribute['checked'] = 'checked';
            } else {
                unset($attribute['checked']);
            }

            $checkbox_buttons .= <<<HTML
            <div class="item">
                <input {$this->get_attribute($attribute)}>
                <label class="form_field_label_item" for="{$attribute['id']}" style="vertical-align: middle;">
                    {$value}
                </label>
            </div>
            HTML;
        }

        return <<<HTML
        <div class="form_field_checkbox form_field_flex">
            {$checkbox_buttons}
        </div>
        HTML;
    }

    function textarea_wp_editor() {
        ob_start();
        // Lấy giá trị cũ nếu có
        $name = $this->args['attribute']['name'] ?? '';
        $value = $this->args['value'] ?? '';
        $id = wp_rand() . $name;

        // Cấu hình TinyMCE
        $editor_settings = array(
            'textarea_name' => $name,
            'media_buttons' => false,
            'quicktags'     => false,
            'tinymce'       => array(
                'toolbar1' => 'bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link unlink',
                // 'toolbar2' => '',
            ),
            'editor_height' => 30,
            'editor_class'  => 'WpDatabaseHelper_field'
        );

        wp_editor($value, $id, $editor_settings);
        return ob_get_clean();
    }

    function input_wp_media_preview($post_id = false) {
        if (!$post_id) {
            return '<div class="inner no_value"> -- </div>';
        }

        $mime_type = get_post_mime_type($post_id);
        if (strpos($mime_type, 'image') === false) {
            $text = "(ID:$post_id)-" . get_the_title($post_id);
            return '<div class="inner has_value">' . $text . '</div>';
        }

        $image_preview = wp_get_attachment_image(
            $post_id,
            'full',
            false,
            ['class' => 'image-preview']
        );

        return '<div class="inner has_value">' . $image_preview . '</div>';
    }

    function input_wp_media() {
        wp_enqueue_media();

        $this->args['attribute']['type'] = 'hidden';
        $value = $this->args['attribute']['value'] ?? '';
        $input_field = $this->get_attribute();
        $image_preview = $this->input_wp_media_preview($value);

        return <<<HTML
        <div class="form_field_media form_field_flex_nowrap">
            <input {$input_field} />
            <div class="form_field_preview">
                $image_preview
            </div>
            <button type="button" class="button hepperMeta-media-upload">Add</button>
            <button type="button" class="button hepperMeta-media-remove">Delete</button>
        </div>
        HTML;
    }

    function get_copy() {
        if (empty($this->args['show_copy'])) {
            return;
        }

        $name = $this->args['attribute']['name'] ?? '';
        if (!$name) {
            return;
        }

        $classes = implode(" ", [
            "{$this->name}_click_to_copy",
            "{$this->name}_name",
            !empty($this->args['show_copy_key']) ? 'show_copy_key' : ''
        ]);

        $text = !empty($this->args['show_copy_key']) ? $name : __('Copy');

        return <<<HTML
        <span class="{$classes}" data-text="{$name}">
            {$text}
        </span>
        HTML;
    }

    function get_suggest() {
        if (empty($this->args['suggest'])) {
            return;
        }
        $output = '';

        $suggestions = (array) $this->args['suggest'];
        $class_name = esc_attr($this->name);
        $suggest_label = _x('Suggested', 'custom headers');
        $output .= '<small><strong>*' . $suggest_label . ': </strong></small>';

        $array = [];
        foreach ($suggestions as $suggest) {
            $suggest_esc = esc_attr($suggest);
            $array[] = <<<HTML
            <span class="{$class_name}_suggest">
                <span class="{$class_name}_click_to_copy" data-text="{$suggest_esc}">{$suggest_esc}</span>
            </span>
            HTML;
        }
        return $output . implode(', ', $array);
    }

    function get_note() {
        if (empty($this->args['note'])) {
            return;
        }

        $notes = (array) $this->args['note'];
        $class_name = esc_attr($this->name);
        $note_label = __('Notes');

        $output = '';

        foreach ($notes as $key => $note) {
            $key_label = $key ? esc_html($key) : '';
            $note_text = wp_kses_post($note);

            $output .= <<<HTML
            <small class="{$class_name}_note">
                <strong>*{$note_label} {$key_label}:</strong> {$note_text}.
            </small>
            HTML;
        }
        return $output;
    }

    function get_label() {
        if (empty($this->args['label'])) {
            return '';
        }

        $label = esc_html($this->args['label']);
        $id = esc_attr($this->id);

        return "<label class='form_field_label' for='{$id}'>{$label}</label>";
    }

    function get_options_term_select() {
        $options = ['' => __('Select')];
        $args    = wp_parse_args(
            $this->args['term_select'],
            [
                'taxonomy'   => 'category',
                'hide_empty' => 'false',
            ]
        );
        $terms   = get_terms($args);

        if (is_wp_error($terms)) {
            return $options;
        }
        foreach ($terms as $key => $term) {
            $_key_            = $this->args['term_select']['option_value'] ?? 'term_id';
            $_value_          = $this->args['term_select']['option_display'] ?? 'name';
            $_key             = $term->{$_key_};
            $_value           = $term->{$_value_};
            $options[$_key] = $_value;
        }
        return $options;
    }

    function get_options_post_select() {
        $options = ['' => __('Select')];
        $__args  = wp_parse_args(
            $this->args['post_select'],
            [
                'post_type'      => 'post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'orderby'        => 'name',
                'order'          => 'asc',
            ]
        );
        $__posts = get_posts($__args);

        if (!empty($__posts) and is_array($__posts)) {
            foreach ((array) $__posts as $key => $__post) {
                $_key_   = $this->args['post_select']['option_value'] ?? 'ID';
                $_value_ = $this->args['post_select']['option_display'] ?? 'post_title';
                $_key    = $__post->{$_key_};
                $display  = $__post->{$_value_};
                $display .= " (ID:" . $__post->ID . ")";
                if ($__post->post_status != 'publish') {
                    $display .= " — " . get_post_statuses()[$__post->post_status];
                }
                $options[$_key] = $display;
            }
        }
        return $options;
    }

    function get_options_user_select() {
        $options = ['' => __('Select')];
        $__args  = wp_parse_args(
            $this->args['user_select'] ?? [],
            [
                // 'role__in'       => ['subscriber', 'editor', 'administrator'],
                'orderby'        => 'display_name',
                'order'          => 'ASC',
                'number'         => -1,
            ]
        );

        // any
        if (in_array('any', $__args['role__in'])) {
            unset($__args['role__in']);
        }

        $users = get_users($__args);

        if (!empty($users) && is_array($users)) {
            foreach ($users as $user) {
                $_key_   = $this->args['user_select']['option_value'] ?? 'ID';
                $_value_ = $this->args['user_select']['option_display'] ?? 'display_name';
                $_key    = $user->{$_key_};
                $_value  = $user->{$_value_};

                $options[$_key] = $_value;
            }
        }

        return $options;
    }
}
