<?php

namespace WpDatabaseHelper;

class Init {

    function __construct() {
        //
    }

    static function WpMeta() {
        return new \WpDatabaseHelper\WpMeta;
    }

    static function WpDatabase() {
        return new \WpDatabaseHelper\WpDatabase();
    }

    static function WpRepeater() {
        return new \WpDatabaseHelper\WpRepeater();
    }

    static function WpField() {
        return new \WpDatabaseHelper\WpField();
    }
}
