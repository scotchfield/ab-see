<?php

class Test_AB_See extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$this->class = new WP_AB_See();
		$this->class->install();

		$this->wp_die = false;
		add_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ), 1, 1 );
	}

	public function tearDown() {
		global $wpdb;

		$wpdb->query( 'DELETE FROM ' . $this->class->table_name );
		$wpdb->query( 'DELETE FROM ' . $this->class->table_tracking_name );

		remove_filter( 'wp_die_handler', array( $this, 'get_wp_die_handler' ) );
		unset( $this->wp_die );

		unset( $this->class );

		parent::tearDown();
	}

	public function get_wp_die_handler( $handler ) {
		return array( $this, 'wp_die_handler' );
	}

	public function wp_die_handler( $message ) {
		$this->wp_die = true;

		throw new WPDieException( $message );
	}

	/**
	 * @covers WP_AB_See::__construct
	 */
	public function test_construct() {
		$this->assertNotNull( $this->class );
	}

	/**
	 * @covers WP_AB_See::init
	 */
	public function test_init() {
		$this->class->init();

		$this->assertTrue( shortcode_exists( 'ab-see' ) );
		$this->assertTrue( shortcode_exists( 'ab-convert' ) );
	}

	/**
	 * @covers WP_AB_See::install
	 */
	public function test_install() {
		global $wpdb;

		ob_start();
		$this->class->install();
		ob_end_clean();

		$this->assertEquals(
			$this->class->table_name,
			$wpdb->get_var( 'SHOW TABLES LIKE "' . $this->class->table_name . '"')
		);

		$this->assertEquals(
			$this->class->table_tracking_name,
			$wpdb->get_var( 'SHOW TABLES LIKE "' . $this->class->table_tracking_name . '"')
		);
	}

	/**
	 * @covers WP_AB_See::add_action_links
	 */
	public function test_add_action_links() {
		$result = $this->class->add_action_links( array() );

		$this->assertCount( 1, $result );
		$this->assertContains( 'page=' . WP_AB_See::DOMAIN, $result[ 0 ] );
	}

	/**
	 * @covers WP_AB_See::admin_menu
	 */
	public function test_admin_menu() {
		$this->class->admin_menu();

		$this->assertArrayHasKey( WP_AB_See::DOMAIN . 'admin', $GLOBALS[ 'admin_page_hooks' ] );
	}

	/**
	 * @covers WP_AB_See::admin_page
	 */
	public function test_admin_page_die() {
		try {
			$this->class->admin_page();
		} catch ( WPDieException $e ) {}

		$this->assertTrue( $this->wp_die );
	}

	/**
	 * @covers WP_AB_See::admin_page
	 */
	/*public function test_admin_page_create() {
		$user = new WP_User( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$old_user_id = get_current_user_id();
		wp_set_current_user( $user->ID );

		$test_id = 'test_create';
		$_POST[ 'create_id' ] = $test_id;

		ob_start();
		$this->class->admin_page();
		$content = ob_get_clean();

		$this->assertContains( $test_id, $content );

		wp_set_current_user( $old_user_id );
	}*/

	/**
	 * @covers WP_AB_See::shortcode_absee
	 */
	public function test_shortcode_absee_empty() {
		$this->assertEmpty( $this->class->shortcode_absee( array() ) );
	}

	/**
	 * @covers WP_AB_See::shortcode_absee
	 */
	public function test_shortcode_absee_test_does_not_exist() {
		$this->assertEmpty( $this->class->shortcode_absee( array( 'id' => 'test_dne' ) ) );
	}

}
