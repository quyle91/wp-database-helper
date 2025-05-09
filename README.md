# WP MetaField

**WordPress Meta Field UI** for managing custom meta fields easily.

## Introduction

The WpDatabaseHelper library provides an easy way to create and manage meta fields in WordPress. This library is designed to help developers enhance their WordPress plugins by adding custom meta fields efficiently.

## License

This project is licensed under the GPL (General Public License).

## Installation

You can install the library via Composer. Run the following command in your terminal:

## Release new stable version
```bash
composer show quyle91/wp-database-helper --all
composer remove quyle91/wp-database-helper
composer require quyle91/wp-database-helper:dev-main
composer update quyle91/wp-database-helper --prefer-source --no-cache
```

## Usage to create a field
```php
$args = [
    'field' => 'input', // input, select, textarea
    'label' => 'XXx label',
    'meta_key' => 'xxx',
    'attribute' => [
        'type' => 'text', // wp_media, wp_editor, date, time, ...
        'placeholder' => 'xxx',
        'class' => '' // no_select2 for skip select2
    ],
    // 'options' => [1=>"1", 2=>"2"] // for select
    // 'options' => [ '' => "No", '1' => "Yes", ], // input:radio
    // 'post_select' => [ 'post_type' => 'product', ], // only for field: select
    // 'term_select' => [ 'taxonomy' => 'product_cat', ], // only for field: select
    // 'user_select' => ['role__in' => ['any']], // only for field: select
];
$args = $this->init_args($args, $meta_value);
$a = \WpDatabaseHelper\Init::WpField();
$a->setup_args($args);
echo $a->init_field();

```

## Usage to create a repeater
```php
$a = \WpDatabaseHelper\Init::WpRepeater();
$a->current = [['key' => '', 'value' => '',],]; // @see: \vendor\quyle91\wp-database-helper\src\WpRepeater.php
$a->prefix = 'prefix'; // also field name
$a->field_configs = [
    '[key]' => [
        'field' => 'select',
        'options' => [
            'xxx' => 'XXX label',
            'yyy' => 'YYY label',
        ]
    ],
    '[value]' => [
        'field' => 'input',
        'attribute' => [
            'placeholder' => 'value',
        ],
    ],
];
echo $a->init_repeater();
```

## Usage to register meta fields and metabox
```php
$args = [
    'post_type' => 'page',
    'metabox_label' => 'Page Metabox',
    'meta_fields' => [
        // input
        [
            'field' => 'input',
            'meta_key' => 'xxx1',
            'before' => '<div class="___default_wrap full_width">', // 100% width
            'label' => 'checkbox 1',
            'attribute' => [
                'type' => 'checkbox', // text, tel, number, color, wp_media...
            ],
            // 'admin_column' => false
        ],
        // textarea
        [
            'field' => 'textarea',
            'meta_key' => 'xxx2',
            'label' => 'Textarea',
            'attribute' => [
                'type' => '', // wp_editor
            ]
        ],
        // select
        [
            'field' => 'select',
            'meta_key' => 'xxx3',
            'label' => 'Select dropdown',
            'options' => [
                1 => 1,
                2 => 2,
                3 => 3,
            ],
        ],
        // tabs
        [
            'field' => 'tab_nav',
            'labels' => ['en_US', 'nl_BE', 'de_DE'],
            'attribute' => [
                'tab_group' => 'tab_group_1',
            ],
        ],
        [
            'field' => 'tab',
            'label' => 'en_US',
            'attribute' => [
                'tab_group' => 'tab_group_1',
            ],
        ],
        // fields inside tab here
        [
            'field' => 'tab_end',
        ],
        // repeater
        [
            'meta_key' => 'prefix',
            'label' => 'XXx Repeater',
            'field' => 'repeater',
            'default' => [
                [
                    'key' => '',
                    'value' => '',
                ],
            ],
            'field_configs' => [
                '[key]' => [
                    'field' => 'select',
                    'options' => [
                        'xxx' => 'XXX label',
                        'yyy' => 'YYY label',
                    ]
                ],
                '[value]' => [
                    'field' => 'input',
                    'attribute' => [
                        'placeholder' => 'value',
                    ],
                ],
            ]
        ],
    ],
    'register_post_meta' => false, // true: register post meta and show in rest
    'admin_post_columns' => true,
    'admin_post_metabox' => true,
    'quick_edit_post' => true,
];

$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init($args);
$meta->init_meta();
```

## Usage to post type admin columns 
```php
$args = [
    'post_type' => 'post',
    'metabox_label' => 'text',
    'meta_fields' => [
        [
            'meta_key' => 'zzz',
            'field' => 'input',
            'label' => 'ZZZ label',
            'attribute' => [
                'type' => 'text',
            ],
        ],
    ],
    'admin_post_columns' => true,
];
$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init($args);
$meta->init_admin_columns();
$meta->init_metabox();
// $meta->init_meta();
```

## Usage to term taxonomy admin columns 
```php
$args = [
    'taxonomy' => 'category',
    'metabox_label' => 'text',
    'taxonomy_meta_fields' => [
        [
            'meta_key' => 'xxx',
            'field' => 'input',
            'label' => 'XXX label',
            'attribute' => [
                'type' => 'text',
            ],
        ],
    ],
    'taxonomy_admin_columns' => true,
    'taxonomy_metabox' => true,
];
$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init($args);
$meta->init_meta_term_taxonomy();
```

## Usage to register table 
```php
$args = [
    'table_name' => 'table_name',
    'menu_title' => 'table name',
    'fields' => [
        'id INT(11) NOT NULL AUTO_INCREMENT,',
        'post_id INT(11) NOT NULL,',
        'column1 INT(11) NOT NULL,',
        'column2 VARCHAR(255) NOT NULL,',
        'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,',
        'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,',
        'PRIMARY KEY (id)',
    ],
];
$table = \WpDatabaseHelper\Init::WpDatabase();
$table->init_table($args);
```

## Author
Name: quyle91
Email: quylv.dsth@gmail.com