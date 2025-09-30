<?php

namespace WpDatabaseHelperV2;
use WpDatabaseHelperV2\Services\Assets;
use WpDatabaseHelperV2\Controllers\AdminController;

class Bootstrap {

    public static function run() {
        (new self())->init();
    }

    public function init() {

        // tạo assets service, đăng ký (register) sẵn
        $assets = new Assets();
        add_action('admin_init', [$assets, 'enqueue']);

        // register admin menu
        add_action('admin_menu', function () {
            add_menu_page(
                'WP DB Helper',
                'WP DB Helper',
                'manage_options',
                'wp-db-helper',
                [new AdminController(), 'optionsPage'],
                'dashicons-database',
                80
            );
        });

        // register metabox example (could be dynamic)
        add_action('add_meta_boxes', function () {
            \WpDatabaseHelperV2\Meta\WpMeta::make('page')
                ->metabox('Page Settings')
                ->fields([
                    \WpDatabaseHelperV2\Fields\WpField::make('text', 'custom_text')->label('Custom Text'),
                    \WpDatabaseHelperV2\Fields\WpRepeater::make('my_repeater')->label('Repeater')->fields([
                        \WpDatabaseHelperV2\Fields\WpField::make('text', 'title')->label('Title'),
                        \WpDatabaseHelperV2\Fields\WpField::make('text', 'value')->label('Value'),
                    ])
                ])
                ->register();
        });

        // save_post hook centralized
        add_action('save_post', [\WpDatabaseHelperV2\Meta\WpMeta::class, 'savePostMeta'], 10, 2);
    }
}
