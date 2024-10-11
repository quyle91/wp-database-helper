# WP MetaField

**WordPress Meta Field UI** for managing custom meta fields easily.

## Introduction

The WpDatabaseHelper library provides an easy way to create and manage meta fields in WordPress. This library is designed to help developers enhance their WordPress plugins by adding custom meta fields efficiently.

## License

This project is licensed under the GPL (General Public License).

## Installation

You can install the library via Composer. Run the following command in your terminal:

```bash
composer require quyle91/wp-database-helper
composer require quyle91/wp-database-helper:dev-main
```


## Usage to register meta fields
```php
$meta_fields = [
    'adminz_banner'  => [ 
        'label' => 'Banner image',
        'meta_box' => 'media'
    ],
    'banner_height'  => [ 
        'label' => 'Banner height',
        'attributes' => [
            'placeholder' => '400px'
        ]
    ],
    'breadcrumb_shortcode' => [ 
        'label' => 'Breadcrumb shortcode override',
    ],
    'adminz_title' => [ 
        'label' => 'Banner title override',
    ],
    'adminz_acf_banner_shortcode' => [ 
        'label' => 'Banner shortcode After',
    ],
];

$post_type = 'post';
$meta_box_label = 'Post metabox';

$meta = \WpDatabaseHelper\WpMetaField::get_instance();
$meta->register_post_meta($post_type, $meta_fields);
$meta->setup_admin_post_columns($post_type, $meta_fields);
$meta->setup_admin_post_metabox($post_type, $meta_fields, $meta_box_label);
$meta->setup_quick_edit_post($post_type, $meta_fields);
```

## Usage to register table 
```php
$args = [
    'table_name' => 'table_name',
    'menu_title'  => 'table name',
    'fields' => [ 
        'id INT(11) NOT NULL AUTO_INCREMENT,',
        'post_id INT(11) NOT NULL,',
        'column1 INT(11) NOT NULL,',
        'column2 VARCHAR(255) NOT NULL,',
        'column3 INT(11) NOT NULL,',
        'column4 VARCHAR(255) NOT NULL,',
        'created_at DATETIME NOT NULL,',
        'updated_at DATETIME NOT NULL,',
        'PRIMARY KEY (id)'
    ],
];
$table = \WpDatabaseHelper\WpDatabase::get_instance();
$table->init_table($args);
```

## Author
Name: quyle91
Email: quylv.dsth@gmail.com


## Release new stable version
```bash
composer show quyle91/wp-database-helper --all
git tag 1.0.0
git push origin 1.0.0
composer update quyle91/wp-database-helper --prefer-source --no-cache
```