<?php 
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

// echo "<pre>"; print_r($table); echo "</pre>";die;




add_action('init', function(){
	if(is_admin()) return;
	global $table;

	// test insert
    // for ($i=0; $i < 100; $i++) {
	// 	$table->insert(
	// 		[ 
	// 			'post_id' => wp_rand(),
	// 			'column1' => wp_rand(),
	// 			'column2' => wp_rand(),
	// 		]
	// 	);
    // }
	

	// test get
	// $ex1 = $table->read(
	// 	[ 
	// 		'column1' => '1627957101',
	// 		'column2' => '799527458',
	// 	], true
	// );
	// echo "<pre>"; print_r( $ex1 ); echo "</pre>"; die;

    // $ex2 = $table->read(
	// 	[ 
    //         'where' => [ 
    //             'column1' => '1627957101',
    //             'column2' => '799527458',
    //         ]
	// 	], true
	// );
	// echo "<pre>"; print_r( $ex2 ); echo "</pre>"; die;

});
