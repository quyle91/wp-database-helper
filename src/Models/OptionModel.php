<?php

namespace WpDatabaseHelperV2\Models;

class OptionModel {
    public static function get(string $key, $default = null) {
        return get_option($key, $default);
    }

    public static function save(string $key, $value) {
        return update_option($key, $value);
    }
}
