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
$args = [
	'post_type' => 'page',
	'metabox_label' => 'Page Metabox',
	'register_post_meta' => true,
	'admin_post_columns' => true,
	'admin_post_metabox' => true,
	'quick_edit_post' => true,
	'meta_fields' => [ 
		[ 
			'name'      => 'field_checkbox',
			'label'     => 'checkbox',
			'attribute' => [ 
				'type' => 'checkbox',
			],
		],
		[ 
			'name'      => 'field_radio',
			'label'     => 'radio',
			'attribute' => [ 
				'type' => 'radio',
			],
			'options'   => [ 
				1 => 1,
				2 => 2,
				3 => 3,
			],
		],
		[ 
			'name'      => 'field_color',
			'label'     => 'color',
			'attribute' => [ 
				'type' => 'color',
			],
		],
		[ 
			'name'      => 'field_date',
			'label'     => 'date',
			'attribute' => [ 
				'type' => 'date',
			],
		],
		[ 
			'name'      => 'field_datetime-local',
			'label'     => 'datetime-local',
			'attribute' => [ 
				'type' => 'datetime-local',
			],
		],
		[ 
			'name'      => 'field_email',
			'label'     => 'email',
			'attribute' => [ 
				'type' => 'email',
			],
		],
		[ 
			'name'      => 'field_file',
			'label'     => 'file',
			'attribute' => [ 
				'type' => 'file',
			],
		],
		[ 
			'name'      => 'field_hidden',
			'label'     => 'hidden',
			'attribute' => [ 
				'type' => 'hidden',
			],
		],
		[ 
			'name'      => 'field_month',
			'label'     => 'month',
			'attribute' => [ 
				'type' => 'month',
			],
		],
		[ 
			'name'      => 'field_number',
			'label'     => 'number',
			'attribute' => [ 
				'type' => 'number',
			],
		],
		[ 
			'name'      => 'field_password',
			'label'     => 'password',
			'attribute' => [ 
				'type' => 'password',
			],
		],
		[ 
			'name'      => 'field_range',
			'label'     => 'range',
			'attribute' => [ 
				'type' => 'range',
			],
		],
		[ 
			'name'      => 'field_search',
			'label'     => 'search',
			'attribute' => [ 
				'type' => 'search',
			],
		],
		[ 
			'name'      => 'field_tel',
			'label'     => 'tel',
			'attribute' => [ 
				'type' => 'tel',
			],
		],
		[ 
			'name'      => 'field_text',
			'label'     => 'text',
			'attribute' => [ 
				'type' => 'text',
			],
		],
		[ 
			'name'      => 'field_time',
			'label'     => 'time',
			'attribute' => [ 
				'type' => 'time',
			],
		],
		[ 
			'name'      => 'field_url',
			'label'     => 'url',
			'attribute' => [ 
				'type' => 'url',
			],
		],
		[ 
			'name'      => 'field_week',
			'label'     => 'week',
			'attribute' => [ 
				'type' => 'week',
			],
		],
		[ 
			'name'      => 'field_reset',
			'label'     => 'reset',
			'attribute' => [ 
				'type' => 'reset',
			],
		],
		[ 
			'name'      => 'field_button',
			'label'     => 'button',
			'attribute' => [ 
				'type' => 'button',
			],
		],
		[ 
			'name'      => 'field_image',
			'label'     => 'image',
			'attribute' => [ 
				'type' => 'image',
			],
		],
		[ 
			'name'      => 'field_submit',
			'label'     => 'submit',
			'attribute' => [ 
				'type' => 'submit',
			],
		],
		// textarea
		[ 
			'name'      => 'field_textarea',
			'label'     => 'Textarea',
			'field'     => 'textarea',
			'attribute' => [],
		],
		// select
		[ 
			'name'    => 'field_select',
			'label'   => 'Select dropdown',
			'field'   => 'select',
			'options' => [ 
				1 => 1,
				2 => 2,
				3 => 3,
			],
		],
		// media
		[ 
			'name'  => 'field_media',
			'label' => 'Media',
			'field' => 'media',
		],
	],	
];

$meta = \WpDatabaseHelper\Init::WpMeta();
$meta->init($args);
$meta->init_meta();
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