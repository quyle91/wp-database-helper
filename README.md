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

## Usage to register meta fields
```php
$args = [ 
	'post_type'          => 'page',
	'metabox_label'      => 'Page Metabox',
	'meta_fields'        => [ 
        // input
		[ 
            'field' => 'input',
			'meta_key'  => 'xxx1',
			'label'     => 'checkbox 1',
			'attribute' => [ 
				'type' => 'checkbox', // text, tel, number, color, wp_media...
			],
			// 'admin_column' => false
		],
        // textarea
        [
			'field'    => 'textarea',
            'meta_key' => 'xxx2',
			'label'    => 'Textarea',
            'attribute' => [
                'type' => '',  // wp_editor
            ]
        ],
		// select
		[ 
			'field'    => 'select',
			'meta_key' => 'xxx3',
			'label'    => 'Select dropdown',
			'options'  => [ 
				1 => 1,
				2 => 2,
				3 => 3,
			],
            // 'post_select' => [ 'post_type' => 'product', ],
            // 'term_select' => [ 'taxonomy' => 'product_cat', ],
		],
	],
	'register_post_meta' => true,
	'admin_post_columns' => true,
	'admin_post_metabox' => true,
	'quick_edit_post'    => true,
];

$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init( $args );
$meta->init_meta();
```

## Usage to register table 
```php
$args  = [ 
	'table_name' => 'table_name',
	'menu_title' => 'table name',
	'fields'     => [ 
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
$table->init_table( $args );
```


## Usage to post type admin columns 
```php
$args = [ 
	'post_type'          => $post_type,
	'meta_fields'        => [ 
		[ 
			'meta_key'  => $meta_key,
			'label'     => ucwords( str_replace( '_', ' ', preg_replace( '/[^a-zA-Z0-9_]/', '', $meta_key ) ) ),
			'attribute' => [ 
				'type' => 'text',
			],
		],
	],
	'admin_post_columns' => true,
];
$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init( $args );
$meta->init_admin_columns();
```

## Usage to term taxonomy admin columns 
```php
$args = [ 
	'taxonomy'             => $taxonomy,
	'taxonomy_meta_fields' => [ 
		[ 
			'meta_key'  => $meta_key,
			'label'     => ucwords( str_replace( '_', ' ', preg_replace( '/[^a-zA-Z0-9_]/', '', $meta_key ) ) ),
			'attribute' => [ 
				'type' => 'text',
			],
		],
	],
	'taxonomy_admin_post_columns'   => true,
];
$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init( $args );
$meta->init_admin_term_taxonomy_columns();
```

## Author
Name: quyle91
Email: quylv.dsth@gmail.com