<?php

class Test_AB_See extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		$this->class = WP_AB_See::get_instance();
	}

	public function tearDown() {
		unset( $this->class );

		parent::tearDown();
	}

	/**
	 * @covers WP_AB_See::get_instance
	 */
	public function test_get_instance() {
		$class = WP_AB_See::get_instance();

		$this->assertNotNull( $class );
	}

	/**
	 * @covers WP_AB_See::init
	 * @covers WP_AB_See::reset
	 */
	public function test_init() {
		$this->class->init();

		$this->assertTrue( shortcode_exists( 'ab-see' ) );
		$this->assertTrue( shortcode_exists( 'ab-convert' ) );
	}

}
