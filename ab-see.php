<?php
/**
 * Plugin Name: A/B See
 * Plugin URI: http://scootah.com/
 * Description: Straightforward shortcodes for A/B testing with WordPress.
 * Version: 1.0.2
 * Author: Scott Grant
 * Author URI: http://scootah.com/
 */
class WP_AB_See {

	/**
	 * Custom tables for test tracking.
	 */
	public $table_name, $table_tracking_name;

	/**
	 * The domain for localization.
	 */
	const DOMAIN = 'wp-ab-see';

	/**
	 * Instantiate and add init hook.
	 */
	public function __construct() {
		global $wpdb;

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		add_action( 'init', array( $this, 'init' ) );

		$this->table_name = $wpdb->prefix . 'ab_see';
		$this->table_tracking_name = $wpdb->prefix . 'ab_see_tracking';
	}

	public function init() {
		add_filter(
			'plugin_action_links_' . plugin_basename(__FILE__),
			array( $this, 'add_action_links' )
		);

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_shortcode( 'ab-see', array( $this, 'shortcode_absee' ) );
		add_shortcode( 'ab-convert', array( $this, 'shortcode_abconvert' ) );

		wp_register_style( 'ab-see-style', plugins_url( '/css/ab-see.css', __FILE__ ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function install() {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = $wpdb->get_charset_collate();

		if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $this->table_name . '\'' ) != $this->table_name ) {
			$sql = "CREATE TABLE " . $this->table_name . " (
				id VARCHAR(32) NOT NULL,
				description TEXT,
				created datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				enabled BOOLEAN DEFAULT false,
				option_a TEXT,
				option_b TEXT,
				conversion_id TINYTEXT,
				UNIQUE KEY id (id)
			) $charset_collate;";

			dbDelta( $sql );
		}

		if ( $wpdb->get_var( 'SHOW TABLES LIKE \'' . $this->table_tracking_name . '\'' ) != $this->table_tracking_name ) {
			$sql = "CREATE TABLE " . $this->table_tracking_name . " (
				id VARCHAR(32) NOT NULL,
				user_id VARCHAR(64) NOT NULL,
				user_group TINYINT,
				created datetime NOT NULL,
				converted datetime NOT NULL,
				UNIQUE KEY `id` (`id`,`user_id`,`user_group`)
			) $charset_collate";

			dbDelta( $sql );
		}
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'ab-see-style' );
	}

	public function add_action_links( $links ) {
		$new_links = array(
			'<a href="' . admin_url( 'options-general.php?page=' . self::DOMAIN . 'admin' ) . '">' .
				esc_html__( 'Settings', self::DOMAIN ) . '</a>',
		);

		return array_merge( $links, $new_links );
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

		if ( empty( $test_id ) || empty( $user_id ) || empty( $user_group ) ) {
			return FALSE;
		}

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

		if ( $result == null ) {
			return array();
		}

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

	public function delete_test( $test_id ) {
		global $wpdb;

		$test = $this->get_test( $test_id );

		if ( FALSE == $test ) {
			return FALSE;
		}

		if ( ! isset( $_GET[ 'nonce' ] ) ) {
?>
<p class="ab-see-notify">
  Are you sure you want to delete the test <b><?php echo $test_id; ?></b>?
  (<a href="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>&amp;delete=<?php echo $test[ 'id' ]; ?>&amp;nonce=<?php echo wp_create_nonce( 'delete_' . $test_id ); ?>">Yes, really delete the test!</a>)
</p>
<?php
		} else if ( wp_verify_nonce( $_GET[ 'nonce' ], 'delete_' . $test_id ) ) {
			$wpdb->delete(
				$this->table_name,
				array(
					'id' => $test_id,
				)
			);

			$wpdb->delete(
				$this->table_tracking_name,
				array(
					'id' => $test_id,
				)
			);
?>
<p class="ab-see-notify">
  Test deleted.
</p>
<?php
			return TRUE;
		}

		return FALSE;
	}

	public function show_edit_page( $id ) {
		$test = $this->get_test( $id );

		if ( $test == FALSE ) {
			return;
		}
?>
<form method="post" action="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>">
<h2>Edit Test</h2>
<table>
  <tr valign="top">
    <td><?php echo __( 'ID', self::DOMAIN ); ?></td>
    <td><input type="text" name="id" value="<?php echo $test[ 'id' ]; ?>" \>
    <emph>[ab-see id=your_id]</emph></td>
  </tr>
  <tr valign="top">
    <td><?php echo __( 'Description', self::DOMAIN ); ?></td>
    <td><textarea cols="80" rows="10" name="description"><?php echo $test[ 'description' ]; ?></textarea></td>
  </tr>
  <tr valign="top">
    <td><?php echo __( 'Group 1', self::DOMAIN ); ?></td>
    <td><textarea cols="80" rows="10" name="option_a"><?php echo $test[ 'option_a' ]; ?></textarea></td>
  </tr>
  <tr valign="top">
    <td><?php echo __( 'Group 2', self::DOMAIN ); ?></td>
    <td><textarea cols="80" rows="10" name="option_b"><?php echo $test[ 'option_b' ]; ?></textarea></td>
  </tr>
  <tr valign="top">
    <td><?php echo __( 'Conversion ID', self::DOMAIN ); ?></td>
    <td><input type="text" name="conversion_id" value="<?php echo $test[ 'conversion_id' ]; ?>" \>
    <emph>[ab-convert id=your_conversion_id]</emph></td>
  </tr>
  <tr valign="top">
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

	public function sort_active_name( $a, $b ) {
		if ( $a['enabled'] != $b['enabled'] ) {
			return ( $a['enabled'] < $b['enabled'] ) ? 1 : -1;
		}

		return ( $a['id'] < $b['id'] ) ? -1 : 1;
	}

	public function render_test_table() {
		$test_obj = $this->get_all_tests();
		uasort( $test_obj, array( $this, 'sort_active_name' ) );

?>
<table class="wp-list-table widefat fixed striped">
  <tr>
    <th><?php echo __( 'ID', self::DOMAIN ); ?></th>
    <th><?php echo __( 'Description', self::DOMAIN ); ?></th>
    <th><?php echo __( 'Created', self::DOMAIN ); ?></th>
    <th><?php echo __( 'Edit', self::DOMAIN ); ?></th>
    <th><?php echo __( 'Enabled', self::DOMAIN ); ?></th>
    <th><?php echo __( 'Delete', self::DOMAIN ); ?></th>
  </tr>
<?php
		foreach ( $test_obj as $test ) {
?>
  <tr>
    <td><a href="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>&amp;view_id=<?php echo $test[ 'id' ]; ?>"><?php echo $test[ 'id' ]; ?></a></td>
    <td><?php echo $test[ 'description' ]; ?></td>
    <td><?php echo $test[ 'created' ]; ?></td>
    <td><a href="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>&amp;edit_id=<?php echo $test[ 'id' ]; ?>"><?php echo __( 'edit', self::DOMAIN ); ?></a></td>
    <td><a href="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>&amp;toggle=<?php echo $test[ 'id' ]; ?>"><?php echo $test[ 'enabled' ] ? 'On' : 'Off'; ?></a></td>
	<td><?php
			if ( ! $test['enabled'] ) {
?>
	<a href="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>&amp;delete=<?php echo $test[ 'id' ]; ?>"><?php echo __( 'Delete Test', self::DOMAIN ); ?></a>
<?php
			}
?></td>
  </tr>
<?php
		}
?>
</table>
<?php
	}

	public function show_view_page( $id ) {
		$test = $this->get_test( $id );

		if ( $test == FALSE ) {
			return;
		}
?>
<p>To use this test, add the following shortcode to the place you want to show your content:<br>
<i>[ab-see id=<?php echo $id; ?>]</i></p>

<p>To register a conversion, add the following shortcode to the final page:<br>
<i>[ab-convert id=<?php echo $test[ 'conversion_id' ]; ?>]</i></p>
<?php
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

?>
<h2>Group 1 conversions: <?php echo $group_a; ?>%
  (<?php echo count( $group_obj[ 1 ][ 'yes' ] ); ?>/<?php
         echo count( $group_obj[ 1 ][ 'yes' ] ) + count( $group_obj[ 1 ][ 'no' ] ); ?>)</h2>

<h2>Group 2 conversions: <?php echo $group_b; ?>%
  (<?php echo count( $group_obj[ 2 ][ 'yes' ] ); ?>/<?php
         echo count( $group_obj[ 2 ][ 'yes' ] ) + count( $group_obj[ 2 ][ 'no' ] ); ?>)</h2>
<?php
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
		} else if ( isset( $_GET[ 'delete' ] ) ) {
			$this->delete_test( $_GET[ 'delete' ] );
		}

		echo '<h2>All Tests</h2>';
		$this->render_test_table();

?>
<h2>Create a New Test</h2>
<form method="post" action="admin.php?page=<?php echo self::DOMAIN . 'admin'; ?>">
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

		$this->update_tracking( $test_id, $user_id, $group );

		if ( isset( $_GET[ 'group_override' ] ) ) {
			$group = intval( $_GET[ 'group_override' ] );
		}

		$result = '';

		if ( $group == 1 ) {
			$result = $test[ 'option_a' ];
		} else if ( $group == 2 ) {
			$result = $test[ 'option_b' ];
		}

		return do_shortcode( $result );
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
