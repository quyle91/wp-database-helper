<?php
namespace WpDatabaseHelper;

class WpRepeater {
	public $name = 'WpDatabaseHelper_repeater';
	private $version;
	public $current; // current array values
	public $prefix; // name 
	public $field_configs; // 1 mảng khai báo kiểu của cac form field
	public $current_field_name = [];

	function __construct() {
		$this->version = $this->getVersion();
	}

	private function getVersion() {
		$composerFile = __DIR__ . '/../composer.json';
		if ( file_exists( $composerFile ) ) {
			$composerData = json_decode( file_get_contents( $composerFile ), true );
			return $composerData['version'] ?? '0.0.0';
		}
		return '0.0.0';
	}

	static function repeater_default_value( $type = 1, $count_items = 1, $key = 'key', $value = 'value' ) {

		// ['','',''] with count = $count_items
		$default_values = [];
		for ( $i = 0; $i < $count_items; $i++ ) {
			$default_values[] = '';
		}

		$return = [];
		switch ( $type ) {
			case 1:
				$return = $default_values;
				break;

			case 2:
				$return = [ $default_values ];
				break;

			case 3:
				$return = [ 
					[ 
						$key   => '',
						$value => '',
					],
				];
				break;
			case 4:
				$return = [ 
					[ 
						$key   => '',
						$value => [ $default_values ],
					],
				];
				break;
		}
		return $return;
	}

	function enqueue() {
		$plugin_url = plugins_url( '', __DIR__ ) . "/assets";

		$enqueue_assets = function () use ($plugin_url) {
			wp_enqueue_style(
				'wpdatabasehelper-wprepeater-css',
				$plugin_url . "/css/repeater.css",
				[],
				$this->version,
				'all'
			);

			wp_enqueue_script(
				'wpdatabasehelper-wprepeater-js',
				$plugin_url . "/js/repeater.js",
				[],
				$this->version,
				true
			);
		};

		if ( did_action( 'admin_enqueue_scripts' ) ) {
			$enqueue_assets();
		} else {
			add_action( 'admin_enqueue_scripts', $enqueue_assets );
		}
	}

	function init_repeater() {
		$this->enqueue();
		ob_start();
		?>
		<div class="<?= esc_attr($this->name) ?> <?= esc_attr( $this->name ) ?>_list_items" prefix="<?= esc_attr( $this->prefix ) ?>">
			<?php
			if ( $this->repeater_is_empty( $this->current ) ) {
				echo '<code class="'.$this->name.'_is_empty">' . __( 'Empty' ) . '</code>';
			}

			echo $this->repeater(
				$this->current,
				$this->prefix,
				$this->field_configs,
				0,
				null
			);
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	function repeater( $current, $prefix, $field_config, $level, $parent_key ) {
		ob_start();
		foreach ( $current as $key => $value ) {
			if ( is_array( $value ) ) {
				?>
				<fieldset class="<?php if ( is_int( $parent_key ) ) echo $this->name.'_list_items'; ?>"
					prefix="<?= esc_attr( $prefix . "[$key]" ); ?>">
					<?php
					echo $this->repeater( $value, $prefix . "[$key]", $field_config, $level + 1, $key );
					echo $this->repeater_button_controls( $level, $key );
					?>
				</fieldset>
				<?php
			} else {
				// echo $key;
				?>
				<label suffix="<?= esc_attr( $key ); ?>">
					<?php
					echo $this->repeater_init_field( $prefix . "[$key]", $value );
					echo $this->repeater_button_controls( $level, $key );
					?>
				</label>
				<?php
			}
		}
		echo $this->repeater_button_addnew( $prefix, $current, $level );
		return ob_get_clean();
	}

	function repeater_init_field( $field_name, $value ) {
		$field_config = [ 
			'field'     => 'input',
			'attribute' => [ 
				'type' => 'text',
				// 'name'  => $field_name,
				// 'value' => $value,
			],
		];

		// match key with str_ends_with and override field_config 
		if ( !empty( $this->field_configs ) ) {
			foreach ( (array) $this->field_configs as $_key => $_field_config ) {
				if ( str_ends_with( $field_name, $_key ) ) {
					$field_config = $_field_config;
					break;
				}
			}
		}

		// remove label if exist
		if ( isset( $field_config['label'] ) ) {
			unset( $field_config['label'] );
		}

		// override selected
		switch ( $field_config['field'] ) {
			case 'input':
				$field_config['attribute']['value'] = $value;
				break;

			case 'select':
				$field_config['selected'] = $value;
				break;

			default:
				# code...
				break;
		}

		// override name
		$field_config['attribute']['name'] = $field_name;

		$a = new \WpDatabaseHelper\WpField;
		$a->setup_args( $field_config );
		return $a->init_field();
	}

	function repeater_button_addnew( $prefix, $current, $level ) {

		// check array with int key
		if ( !$this->repeater_array_with_int_keys( $current ) ) {
			return;
		}

		// don't allow for level > 0 or is not last level
		if ( $level > 0 and $level == $this->repeater_array_last_level( $this->current ) ) {
			return;
		}

		ob_start();
		?>
		<button type="button" class="button addnew">
			<?= __( 'Add' ) ?>
		</button>
		<?php
		return ob_get_clean();
	}

	function repeater_button_controls( $level, $key ) {

		if ( !is_int( $key ) ) {
			return;
		}

		if ( $level > 0 and $level == $this->repeater_array_last_level( $this->current ) ) {
			return;
		}

		ob_start();

		echo '<div class="'.$this->name.'_control">';
		echo $this->repeater_button_move( $level, $key );
		echo $this->repeater_button_delete( $level, $key );
		echo '</div>';

		return ob_get_clean();
	}

	function repeater_is_empty( $value ) {
		return !$this->repeater_sum_value( $value );
	}

	function repeater_sum_value( $value ) {
		$value = (array) $value;

		$sum = 0;
		foreach ( $value as $sub_value ) {
			if ( is_array( $sub_value ) ) {
				$sum += $this->repeater_sum_value( $sub_value );
			} else if ( is_string( $sub_value ) && trim( $sub_value ) != '' ) {
				$sum++;
			}
		}
		return $sum;
	}

	function repeater_button_move( $level, $key ) {
		ob_start();
		?>
		<button type="button" class="button move_up_one"><?= __( 'Move up' ) ?></button>
		<?php
		return ob_get_clean();
	}

	function repeater_button_delete( $level, $key ) {
		ob_start();
		?>
		<button type="button" class="button delete"><?= __( 'Delete' ) ?></button>
		<?php
		return ob_get_clean();
	}

	function repeater_array_with_int_keys( $current ) {
		$return = true;

		foreach ( (array) $current as $key => $value ) {
			if ( !is_int( $key ) ) {
				$return = false;
				break;
			}
		}

		return $return;
	}

	function repeater_array_last_level( $current, $level = 0 ) {
		if ( is_array( $current ) && !empty( $current ) ) {
			$maxLevel = $level;
			foreach ( $current as $value ) {
				if ( is_array( $value ) ) {
					$maxLevel = max( $maxLevel, $this->repeater_array_last_level( $value, $level + 1 ) );
				}
			}
			return $maxLevel;
		} else {
			return $level;
		}
	}
}