<?php

namespace WpDatabaseHelper;

class WpRepeater {
    public $name = 'WpDatabaseHelper_repeater';
    private $version;
    public $current; // current array values
    public $prefix; // name 
    public $field_configs; // 1 mảng khai báo kiểu của cac form field
    public $label;
    public $current_field_name = [];

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

    static function repeater_default_value($type = 1, $count_items = 1, ...$params) {
        if (empty($params)) {
            $params = ['key', 'value'];
        }

        // ['','',''] with count = $count_items
        $default_values = [];
        for ($i = 0; $i < $count_items; $i++) {
            $default_values[] = '';
        }

        $return = [];
        switch ($type) {
            case 1:
                $return = $default_values;
                break;

            case 2:
                $return = [$default_values];
                break;

            case 3:
                $return = [];
                foreach ((array) $params as $value) {
                    $return[0][$value] = '';
                }
                break;
            case 4:
                $return = [
                    [
                        $params[0] => '',
                        $params[1] => [$default_values],
                    ],
                ];
                break;
        }
        return $return;
    }

    function enqueue() {
        $plugin_url = plugins_url('', __DIR__) . "/assets";

        // Return early if the script is already enqueued
        // if (wp_script_is('wpdatabasehelper-repeater-js', 'enqueued')) {
        //     return;
        // }

        wp_enqueue_style(
            'wpdatabasehelper-repeater-css',
            $plugin_url . "/css/repeater.css",
            [],
            $this->version,
            'all'
        );

        wp_enqueue_script(
            'wpdatabasehelper-repeater-js',
            $plugin_url . "/js/repeater.js",
            [],
            $this->version,
            true
        );
    }

    function init_repeater() {
        $this->enqueue();
        ob_start();
?>
        <div class="<?= esc_attr($this->name) ?> <?= esc_attr($this->name) ?>_list_items"
            prefix="<?= esc_attr($this->prefix) ?>">
            <?php
            if ($this->repeater_is_empty($this->current)) {
                echo '<code class="is_empty"><small>' . __('Empty') . '</small></code>';
            }

            //
            echo $this->repeater_label();

            //
            // echo "<pre>"; print_r($this->current); echo "</pre>";
            echo $this->repeater(
                $this->current,
                $this->prefix,
                $this->field_configs,
                0,
                null
            );
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    function repeater($current, $prefix, $field_config, $level, $parent_key) {
        ob_start();
        foreach ((array)$current as $key => $value) {

            // check level 0 and init key -> wrong format
            if ($level == 0 and !is_int($key)) {
                continue;
            }

            // đối với metavalue là 1 mảng, luôn được trình bày dưới dạng repeater.
            // default là tạo ra 1 field từ wp field

            if (is_array($value)) {
        ?>
                <fieldset
                    class="<?php if (is_int($parent_key)) echo $this->name . '_list_items'; ?>"
                    prefix="<?= esc_attr($prefix . "[$key]"); ?>"
                    level="<?= esc_attr($level); ?>">
                    <?php
                    // trường hợp đặc biệt: 'field_settings' thì ko cần repeater
                    if ($key == 'field_settings') {
                        echo $this->repeater_init_field($prefix . "[$key]", $value);
                    } else {
                        echo $this->repeater($value, $prefix . "[$key]", $field_config, $level + 1, $key);
                    }
                    echo $this->repeater_button_controls($level, $key);
                    ?>
                </fieldset>
            <?php
            } else {
            ?>
                <div
                    class="repeater_field"
                    suffix="<?= esc_attr($key); ?>"
                    level="<?= esc_attr($level); ?>">
                    <?php
                    echo $this->repeater_init_field($prefix . "[$key]", $value);
                    echo $this->repeater_button_controls($level, $key);
                    ?>
                </div>
<?php
            }
        }
        echo $this->repeater_button_addnew($prefix, $current, $level);
        return ob_get_clean();
    }

    function repeater_init_field($field_name, $value) {
        $field_config = [
            'field' => 'input',
            'attribute' => [
                'type' => 'text',
                'value' => '',
            ],
            'value' => '',
        ];

        // match key with str_ends_with and override field_config 
        if (!empty($this->field_configs)) {
            foreach ((array) $this->field_configs as $_key => $_field_config) {
                if (str_ends_with($field_name, $_key)) {
                    $field_config = $_field_config;
                    break;
                }
            }
        }

        // remove label if exist
        // if (isset($field_config['label'])) {
        // unset($field_config['label']);
        // }

        // intergration 
        $field_config['attribute']['name'] = $field_name;
        $field_config['value'] = $value;
        if ($field_config['field'] == 'input') {
            $field_config['attribute']['value'] = $value;
        }


        $a = \WpDatabaseHelper\Init::WpField();
        $a->setup_args($field_config);
        return $a->init_field();
    }

    function repeater_is_empty($value) {
        return !$this->repeater_sum_value($value);
    }

    function repeater_sum_value($value) {
        $sum = 0;
        foreach ((array)$value as $sub_value) {
            if (is_array($sub_value)) {
                $sum += $this->repeater_sum_value($sub_value);
            } else if (is_string($sub_value) && trim($sub_value) != '') {
                $sum++;
            }
        }
        return $sum;
    }

    function repeater_button_addnew($prefix, $current, $level) {

        // là 1 mảng với key là int
        // bởi vì nếu key là string thì ko thể repeater được.
        // [0 => ['type' => 'text', 'value' => 'xx']] => work 
        // ['type' => 'text', 'value' => 'yy'] => not work
        if (!$this->repeater_array_with_int_keys($current)) {
            return;
        }

        // don't allow for level > 0 or is not last level
        if ($level > 0 and $level == $this->repeater_array_last_level($this->current)) {
            return;
        }

        $text = '<span class="dashicons dashicons-plus"></span>';
        return <<<HTML
        <button type="button" class="button addnew button-small"> $text </button>
        HTML;
    }

    function repeater_button_controls($level, $key) {

        if (!is_int($key)) {
            return;
        }

        // don't allow for level > 0 or is not last level
        if ($level > 0 and $level == $this->repeater_array_last_level($this->current)) {
            return;
        }

        ob_start();

        echo '<div class="controls">';
        echo $this->repeater_button_control_move($level, $key);
        echo $this->repeater_button_control_delete($level, $key);
        echo '</div>';

        return ob_get_clean();
    }

    function repeater_button_control_move($level, $key) {
        $text = '<span class="dashicons dashicons-arrow-up-alt"></span>';
        return <<<HTML
        <button type="button" class="button move_up_one button-small">$text</button>
        HTML;
    }

    function repeater_button_control_delete($level, $key) {
        $text = '<span class="dashicons dashicons-trash"></span>';
        return <<<HTML
        <button type="button" class="button delete button-small">$text</button>
        HTML;
    }

    function repeater_array_with_int_keys($current) {
        $return = true;
        foreach ((array) $current as $key => $value) {
            if (!is_int($key)) {
                $return = false;
                break;
            }
        }

        return $return;
    }

    function repeater_array_last_level($current, $level = 0) {
        if (is_array($current) && !empty($current)) {
            $maxLevel = $level;
            foreach ((array)$current as $value) {
                if (is_array($value)) {
                    $maxLevel = max($maxLevel, $this->repeater_array_last_level($value, $level + 1));
                }
            }
            return $maxLevel;
        } else {
            return $level;
        }
    }

    function repeater_label() {
        if (empty($this->label)) {
            return '';
        }

        $label = esc_html($this->label);

        return "<label class='form_field_label'>{$label}</label>";
    }
}
