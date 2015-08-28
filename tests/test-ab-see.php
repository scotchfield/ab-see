<?php

class Test_AB_See extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );

		$this->class = new WP_AB_See();
	}

	public function tearDown() {
		unset( $this->class );

		parent::tearDown();
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
	 * @covers WP_AB_See::admin_menu
	 */
	public function test_admin_menu() {
		$this->class->admin_menu();

		$this->assertArrayHasKey( WP_AB_See::DOMAIN . 'admin', $GLOBALS[ 'admin_page_hooks' ] );
	}

}
