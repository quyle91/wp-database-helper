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
		[ 
			'meta_key'  => 'field_checkbox1',
			'label'     => 'checkbox 1',
			'attribute' => [ 
				'type' => 'checkbox',
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'  => 'field_checkbox2',
			'label'     => 'checkbox 2 - custom value',
			'attribute' => [ 
				'type' => 'checkbox',
			],
			'options'   => [ 
				'test' => 'TEST value',
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'  => 'field_checkbox3',
			'label'     => 'checkbox 2 - multiple values',
			'attribute' => [ 
				'type' => 'checkbox',
			],
			'options'   => [ 
				1 => 'option 1',
				2 => 'option 2',
				3 => 'option 3',
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'  => 'field_radio',
			'label'     => 'radio',
			'attribute' => [ 
				'type' => 'radio',
			],
			'options'   => [ 
				1  => "Label for option 01",
				2  => "Label for option 02",
				3  => "Label for option 03",
				11 => "Label for option 011",
				12 => "Label for option 012",
				13 => "Label for option 013",
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'     => 'field_color',
			'label'        => 'color',
			'attribute'    => [ 
				'type' => 'color',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_date',
			'label'        => 'date',
			'attribute'    => [ 
				'type' => 'date',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_datetime-local',
			'label'        => 'datetime-local',
			'attribute'    => [ 
				'type' => 'datetime-local',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_email',
			'label'        => 'email',
			'attribute'    => [ 
				'type' => 'email',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_hidden',
			'label'        => 'hidden',
			'attribute'    => [ 
				'type' => 'hidden',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_month',
			'label'        => 'month',
			'attribute'    => [ 
				'type' => 'month',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'  => 'field_number',
			'label'     => 'number',
			'attribute' => [ 
				'type' => 'number',
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'     => 'field_password',
			'label'        => 'password',
			'attribute'    => [ 
				'type' => 'password',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_range',
			'label'        => 'range',
			'attribute'    => [ 
				'type' => 'range',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_search',
			'label'        => 'search',
			'attribute'    => [ 
				'type' => 'search',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_tel',
			'label'        => 'tel',
			'attribute'    => [ 
				'type' => 'tel',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_text',
			'label'        => 'text',
			'attribute'    => [ 
				'type' => 'text',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_time',
			'label'        => 'time',
			'attribute'    => [ 
				'type' => 'time',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_url',
			'label'        => 'url',
			'attribute'    => [ 
				'type' => 'url',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'     => 'field_week',
			'label'        => 'week',
			'attribute'    => [ 
				'type' => 'week',
			],
			'admin_column' => false,
		],
		[ 
			'meta_key'  => 'field_media',
			'label'     => 'Media',
			'attribute' => [ 
				'type' => 'wp_media',
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'     => 'field_textarea',
			'label'        => 'Textarea',
			'field'        => 'textarea',
			'attribute'    => [],
			'admin_column' => false,
		],
		// select
		[ 
			'meta_key' => 'field_select',
			'label'    => 'Select dropdown',
			'field'    => 'select',
			'options'  => [ 
				1 => 1,
				2 => 2,
				3 => 3,
			],
			// 'admin_column' => false
		],
		[ 
			'meta_key'    => 'field_select2',
			'label'       => 'Select product',
			'field'       => 'select',
			'post_select' => [ 
				'post_type' => 'product',
			],
			// 'admin_column' => false		
		],
		[ 
			'meta_key'    => 'field_select3',
			'label'       => 'Term product',
			'field'       => 'select',
			'term_select' => [ 
				'taxonomy' => 'product_cat',
			],
			// 'admin_column' => false
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