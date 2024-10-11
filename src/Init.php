<?php
namespace WpDatabaseHelper;
/**
*
*/
class Init{

    function __construct(){
        
    }

    static function WpDatabase(){
        return new \WpDatabaseHelper\WpDatabase();
    }

	static function WpMetaField() {
        return \WpDatabaseHelper\WpMetaField::get_instance();
	}
}