<?php
/**
 * Plugin Name: A/B See
 * Plugin URI: http://scootah.com/
 * Description: Straightforward shortcodes for A/B testing with WordPress.
 * Version: 1.0
 * Author: Scott Grant
 * Author URI: http://scootah.com/
 */
class WP_AB_See {

	/**
	 * Store reference to singleton object.
	 */
	private static $instance = null;

	/**
	 * The domain for localization.
	 */
	const DOMAIN = 'wp-ab-see';

	/**
	 * Instantiate, if necessary, and add hooks.
	 */
	public function __construct() {
		global $wpdb;

		if ( isset( self::$instance ) ) {
			wp_die( esc_html__(
				'WP_AB_See is already instantiated!',
				self::DOMAIN ) );
		}

		self::$instance = $this;

		$this->table_name = $wpdb->prefix . 'ab_see';
		$this->table_tracking_name = $wpdb->prefix . 'ab_see_tracking';

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_shortcode( 'ab-see', array( $this, 'shortcode_absee' ) );
		add_shortcode( 'ab-convert', array( $this, 'shortcode_abconvert' ) );
	}

	public function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE " . $this->table_name . " (
			id VARCHAR(32) NOT NULL,
			description TEXT,
			created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			enabled BOOLEAN DEFAULT false,
			option_a TEXT,
			option_b TEXT,
			conversion_id TINYTEXT,
			UNIQUE KEY id (id)
		) $charset_collate;

		CREATE TABLE " . $this->table_tracking_name . " (
			id VARCHAR(32) NOT NULL,
			user_id VARCHAR(64) NOT NULL,
			user_group TINYINT,
			created datetime NOT NULL,
			converted datetime NOT NULL,
			UNIQUE KEY `id` (`id`,`user_id`,`user_group`)
		) $charset_collate";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Add a link to a settings page.
	 */
	public function admin_menu() {
		add_menu_page(
			'A/B See',
			'A/B See',
			'manage_options',
			self::DOMAIN . 'admin',
			array( $this, 'admin_page' )
		);
	}

	public function update_tracking( $test_id, $user_id, $user_group ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->table_tracking_name . '` WHERE id=%s AND user_id=%s',
				$test_id, $user_id
			), ARRAY_A
		);

		if ( FALSE == $result ) {
			$wpdb->insert(
				$this->table_tracking_name,
				array(
					'id' => $test_id,
					'user_id' => $user_id,
					'user_group' => $user_group,
					'created' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s' )
			);
		}
	}

	public function get_tests_with_conversion( $conversion_id ) {
		global $wpdb;

		$result_obj = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->table_name . '` WHERE conversion_id=%s',
				$conversion_id
			), ARRAY_A
		);

		return $result_obj;
	}

	public function update_conversion( $test_id, $user_id ) {
		global $wpdb;

		$wpdb->update(
			$this->table_tracking_name,
			array(
				'converted' => current_time( 'mysql' ),
			),
			array(
				'id' => $test_id,
				'user_id' => $user_id,
			),
			array( '%s', '%s' )
		);
	}

	public function create_test( $test_id ) {
		global $wpdb;

		$wpdb->insert(
			$this->table_name,
			array(
				'id' => $test_id,
				'created' => current_time( 'mysql' ),
			)
		);
	}

	public function get_all_tests() {
		global $wpdb;

		$result_obj = $wpdb->get_results(
			'SELECT * FROM `' . $this->table_name . '`',
			ARRAY_A
		);

		foreach ( array_keys( $result_obj ) as $k ) {
			$result_obj[ $k ][ 'description' ] = stripslashes( $result_obj[ $k ][ 'description' ] );
			$result_obj[ $k ][ 'option_a' ] = stripslashes( $result_obj[ $k ][ 'option_a' ] );
			$result_obj[ $k ][ 'option_b' ] = stripslashes( $result_obj[ $k ][ 'option_b' ] );
		}

		return $result_obj;
	}

	public function get_test( $test_id ) {
		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->table_name . '` WHERE id=%s',
				$test_id
			), ARRAY_A
		);

		$result[ 'description' ] = stripslashes( $result[ 'description' ] );
		$result[ 'option_a' ] = stripslashes( $result[ 'option_a' ] );
		$result[ 'option_b' ] = stripslashes( $result[ 'option_b' ] );

		return $result;
	}

	public function update_test( $args ) {
		global $wpdb;

		$required = array( 'id', 'description', 'option_a', 'option_b', 'conversion_id' );

		foreach ( $required as $x ) {
			if ( ! isset( $args[ $x ] ) ) {
				return FALSE;
			}
		}

		$wpdb->update(
			$this->table_name,
			array(
				'id' => $args[ 'id' ],
				'description' => $args[ 'description' ],
				'option_a' => $args[ 'option_a' ],
				'option_b' => $args[ 'option_b' ],
				'conversion_id' => $args[ 'conversion_id' ],
			),
			array(
				'id' => $args[ 'id' ],
			)
		);

		return TRUE;
	}

	public function toggle_test( $test_id ) {
		global $wpdb;

		$test = $this->get_test( $test_id );

		if ( FALSE == $test ) {
			return FALSE;
		}

		$enabled = $test[ 'enabled' ] == TRUE ? FALSE : TRUE;

		$wpdb->update(
			$this->table_name,
			array(
				'enabled' => $enabled,
			),
			array(
				'id' => $test_id,
			)
		);
	}

	public function show_edit_page( $id ) {
		$test = $this->get_test( $id );

		if ( $test == FALSE ) {
			return;
		}
?>
<form method="post" action="admin.php?page=<?php echo( self::DOMAIN . 'admin' ); ?>">
<h2>Edit Test</h2>
<table>
  <tr>
    <td>ID</td>
    <td><input type="text" name="id" value="<?php echo( $test[ 'id' ] ); ?>" \></td>
  </tr>
  <tr>
    <td>Description</td>
    <td><textarea cols="80" rows="10" name="description"><?php echo( $test[ 'description' ] ); ?></textarea></td>
  </tr>
  <tr>
    <td>Option A</td>
    <td><textarea cols="80" rows="10" name="option_a"><?php echo( $test[ 'option_a' ] ); ?></textarea></td>
  </tr>
  <tr>
    <td>Option B</td>
    <td><textarea cols="80" rows="10" name="option_b"><?php echo( $test[ 'option_b' ] ); ?></textarea></td>
  </tr>
  <tr>
    <td>Conversion ID</td>
    <td><input type="text" name="conversion_id" value="<?php echo( $test[ 'conversion_id' ] ); ?>" \></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td><input type="submit" name="update" value="Update Test" /></td>
  </tr>
</table>
</form>
<?
	}

	public function get_tracking( $id ) {
		global $wpdb;

		$result_obj = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . $this->table_tracking_name . '` WHERE id=%s',
				$id
			),
			ARRAY_A
		);

		return $result_obj;
	}

	public function get_conversion_rate( $yes, $no ) {
		$total = $yes + $no;
		if ( $total > 0 ) {
			return 100 * $yes / ( $yes + $no );
		} else {
			return 0;
		}
	}

	public function show_view_page( $id ) {
		$test = $this->get_test( $id );

		if ( $test == FALSE ) {
			return;
		}

		$tracking_obj = $this->get_tracking( $id );

		$group_obj = array(
			1 => array( 'yes' => array(), 'no' => array() ),
			2 => array( 'yes' => array(), 'no' => array() ),
		);

		foreach ( $tracking_obj as $track ) {
			if ( strtotime( $track[ 'converted' ] ) > 0 ) {
				array_push( $group_obj[ $track[ 'user_group' ] ][ 'yes' ], $track[ 'converted' ] );
			} else {
				array_push( $group_obj[ $track[ 'user_group' ] ][ 'no' ], $track[ 'converted' ] );
			}
		}

		$group_a = round( $this->get_conversion_rate(
			count( $group_obj[ 1 ][ 'yes' ] ),
			count( $group_obj[ 1 ][ 'no' ] ) ), 2 );
		
		$group_b = round( $this->get_conversion_rate(
			count( $group_obj[ 2 ][ 'yes' ] ),
			count( $group_obj[ 2 ][ 'no' ] ) ), 2 );

		echo( '<h2>Group 1 conversions: ' . $group_a . '% (' .
			count( $group_obj[ 1 ][ 'yes' ] ) . '/' .
			( count( $group_obj[ 1 ][ 'yes' ] ) + count( $group_obj[ 1 ][ 'no' ] ) ) .
			')</h2>' );

		echo( '<h2>Group 2 conversions: ' . $group_b . '% (' .
			count( $group_obj[ 2 ][ 'yes' ] ) . '/' .
			( count( $group_obj[ 2 ][ 'yes' ] ) + count( $group_obj[ 2 ][ 'no' ] ) ) .
			')</h2>' );
	}

	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', self::DOMAIN ) );
		}
?>
<h1>A/B See</h1>
<?php
		if ( isset( $_POST[ 'create_id' ] ) ) {
			$this->create_test( $_POST[ 'create_id' ] );
		} else if ( isset( $_POST[ 'update' ] ) ) {
			$this->update_test( $_POST );
		} else if ( isset( $_GET[ 'toggle' ] ) ) {
			$this->toggle_test( $_GET[ 'toggle' ] );
		} else if ( isset( $_GET[ 'edit_id' ] ) ) {
			$this->show_edit_page( $_GET[ 'edit_id' ] );
		} else if ( isset( $_GET[ 'view_id' ] ) ) {
			$this->show_view_page( $_GET[ 'view_id' ] );
		}

		$test_obj = $this->get_all_tests();

?>
<h2>Active Tests</h2>
<table width="100%">
  <tr align="center">
    <th>ID</th><th>Description</th><th>Created</th><th>Enabled</th>
  </tr>
<?php
		foreach ( $test_obj as $test ) {
?>
  <tr align="center">
    <td><a href="admin.php?page=<?php echo( self::DOMAIN . 'admin' ); ?>&view_id=<?php echo( $test[ 'id' ] ); ?>"><?php echo( $test[ 'id' ] ); ?></a><br>(<a href="admin.php?page=<?php echo( self::DOMAIN . 'admin' ); ?>&edit_id=<?php echo( $test[ 'id' ] ); ?>">edit</a>)</td>
    <td><?php echo( $test[ 'description' ] ); ?></td>
    <td><?php echo( $test[ 'created' ] ); ?></td>
    <td><a href="admin.php?page=<?php echo( self::DOMAIN . 'admin' ); ?>&toggle=<?php echo( $test[ 'id' ] ); ?>"><?php echo( $test[ 'enabled' ] ); ?></a></td>
  </tr>
<?php
		}
?>
</table>
<h2>Create a New Test</h2>
<form method="post" action="admin.php?page=<?php echo( self::DOMAIN . 'admin' ); ?>">
<p>
  <b>Test ID (unique, no spaces!)</b>: <input type="text" name="create_id">
</p>
</form>
<?php
	}

	public function get_group( $test_id, $user_id, $group_count ) {
		mt_srand( crc32( strval( $test_id ) . strval( $user_id ) ) );

		return mt_rand( 1, $group_count );
	}

	public function get_user_id() {
		$user_id = strval( get_current_user_id() );

		if ( $user_id == '0' ) {
			$user_id = $_SERVER[ 'REMOTE_ADDR' ];
		}

		return $user_id;
	}

	public function shortcode_absee( $args ) {
		if ( ! isset( $args[ 'id' ] ) ) {
			return '';
		}

		$test_id = $args[ 'id' ];

		$test = $this->get_test( $test_id );

		if ( FALSE == $test || ! $test[ 'enabled' ] ) {
			return '';
		}

		$user_id = $this->get_user_id();
		$group = $this->get_group( $test_id, $user_id, 2 );

		$result = '';

		if ( $group == 1 ) {
			$result = $test[ 'option_a' ];
		} else if ( $group == 2 ) {
			$result = $test[ 'option_b' ];
		}

		$this->update_tracking( $test_id, $user_id, $group );

		return $result;
	}

	public function shortcode_abconvert( $args ) {
		if ( ! isset( $args[ 'id' ] ) ) {
			return '';
		}

		$conversion_id = $args[ 'id' ];
		$user_id = $this->get_user_id();

		$test_obj = $this->get_tests_with_conversion( $conversion_id );

		foreach ( $test_obj as $test ) {
			if ( ! $test[ 'enabled' ] ) {
				continue;
			}

			$this->update_conversion( $test[ 'id' ], $user_id );
		}

		return '';
	}

}

$wp_ab_see = new WP_AB_See();