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

## Release new stable version
```bash
composer show quyle91/wp-database-helper --all
git tag 1.0.0
git push origin 1.0.0
composer remove quyle91/wp-database-helper:dev-main
composer require quyle91/wp-database-helper:dev-main
composer update quyle91/wp-database-helper --prefer-source --no-cache
```

## Usage to register meta fields
```php
$meta_fields = [
    'field1'  => [ 
        'label' => 'field1',
        'meta_box' => 'media'
    ],
    'field2'  => [ 
        'label' => 'field2',
        'attributes' => [
            'placeholder' => '400px'
        ]
    ],
    'field3' => [ 
        'label' => 'field3',
    ],
    'field4' => [ 
        'label' => 'field4',
        'meta_box'=> 'select',
        'options' => [
            1=>1,
            2=>2,
            3=>3
        ]
    ],
];

$post_type = 'page';
$meta_box_label = 'Page metabox';
$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->register_post_meta($post_type, $meta_fields);
$meta->setup_admin_post_columns($post_type, $meta_fields);
$meta->setup_admin_post_metabox($post_type, $meta_fields, $meta_box_label);
$meta->setup_quick_edit_post($post_type, $meta_fields);
```

## Screenshots
![xxx](https://quyle91.net/wp-content/uploads/2024/10/Screenshot_1-1.png)
![xxx](https://quyle91.net/wp-content/uploads/2024/10/Screenshot_2.png)

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
$table = \WpDatabaseHelper\Init::WpDatabase();
$table = new \WpDatabaseHelper\WpDatabase();
$table->init_table($args);
```

## Screenshots
![xxx](https://quyle91.net/wp-content/uploads/2024/10/Screenshot_4.png)

## Author
Name: quyle91
Email: quylv.dsth@gmail.com