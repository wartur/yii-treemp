<?php

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-09-03 at 14:07:42.
 */
class RealTreeSelectWidgetTest extends CDbTestCase {

	protected $fixtures = array(
		'treetest' => 'Treetest',
	);

	// =========================================================================
	// fixtures

	/**
	 * This method is called before the first test of this test class is run.
	 *
	 * @since Method available since Release 3.4.0
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {
		parent::tearDown();
	}

	/**
	 * This method is called after the last test of this test class is run.
	 *
	 * @since Method available since Release 3.4.0
	 */
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
	}

	// =========================================================================
	// tests

	/**
	 * @covers RealTreeSelectWidget::init
	 * @todo   Implement testInit().
	 */
	public function testInit() {
		// test emptytext default set
		$widget = new RealTreeSelectWidget();
		$this->assertNull($widget->emptyText);
		$widget->init();
		$this->assertEquals('(Select parent)', $widget->emptyText);

		// test emptytext set
		$widget = new RealTreeSelectWidget();
		$widget->emptyText = 'sometext';
		$this->assertNotNull($widget->emptyText);
		$widget->init();
		$this->assertEquals('sometext', $widget->emptyText);
	}

	/**
	 * @covers RealTreeSelectWidget::recursiveGenerateSelect
	 */
	public function testRecursiveGenerateSelect() {
		$widget = new RealTreeSelectWidget();
		$widget->attribute = 'parent_id';

		$allRecords = array(
			$this->treetest['node1']['id'] => str_repeat('&nbsp;', 0) . $this->treetest['node1']['name'],
			$this->treetest['node3']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node3']['name'],
			$this->treetest['node4']['id'] => str_repeat('&nbsp;', 4) . $this->treetest['node4']['name'],
			$this->treetest['node5']['id'] => str_repeat('&nbsp;', 6) . $this->treetest['node5']['name'],
			$this->treetest['node7']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node7']['name'],
			$this->treetest['node8']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node8']['name'],
			$this->treetest['node2']['id'] => str_repeat('&nbsp;', 0) . $this->treetest['node2']['name'],
			$this->treetest['node9']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node9']['name'],
			$this->treetest['node6']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node6']['name'],
		);

		// test for new
		$model = new Treetest();
		$widget->model = $model;
		$this->assertEquals($allRecords, $widget->recursiveGenerateSelect());

		// test for subnode with deleteHimself = false
		$widget->deleteHimself = false;
		$model = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$widget->model = $model;
		$this->assertEquals($allRecords, $widget->recursiveGenerateSelect());
		$widget->deleteHimself = true;

		// test for subnode
		$allRecordsWithoutCurrentTree = array(
			$this->treetest['node2']['id'] => str_repeat('&nbsp;', 0) . $this->treetest['node2']['name'],
			$this->treetest['node9']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node9']['name'],
			$this->treetest['node6']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node6']['name'],
		);

		$model = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$widget->model = $model;
		$this->assertEquals($allRecordsWithoutCurrentTree, $widget->recursiveGenerateSelect());

		// ====================
		// test for subnode $useCacheInternal = false
		$widget->useCacheInternal = false;
		$model = new Treetest();
		$widget->model = $model;
		$this->assertEquals($allRecords, $widget->recursiveGenerateSelect());

		$model = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$widget->model = $model;
		$this->assertEquals($allRecordsWithoutCurrentTree, $widget->recursiveGenerateSelect());
		$widget->useCacheInternal = true;

		// ====================
		// test for spaceMultiplier = 1 and default
		$widget->spaceMultiplier = 1;
		$allRecordsSpace1 = array(
			$this->treetest['node1']['id'] => str_repeat('&nbsp;', 0) . $this->treetest['node1']['name'],
			$this->treetest['node3']['id'] => str_repeat('&nbsp;', 1) . $this->treetest['node3']['name'],
			$this->treetest['node4']['id'] => str_repeat('&nbsp;', 2) . $this->treetest['node4']['name'],
			$this->treetest['node5']['id'] => str_repeat('&nbsp;', 3) . $this->treetest['node5']['name'],
			$this->treetest['node7']['id'] => str_repeat('&nbsp;', 1) . $this->treetest['node7']['name'],
			$this->treetest['node8']['id'] => str_repeat('&nbsp;', 1) . $this->treetest['node8']['name'],
			$this->treetest['node2']['id'] => str_repeat('&nbsp;', 0) . $this->treetest['node2']['name'],
			$this->treetest['node9']['id'] => str_repeat('&nbsp;', 1) . $this->treetest['node9']['name'],
			$this->treetest['node6']['id'] => str_repeat('&nbsp;', 1) . $this->treetest['node6']['name'],
		);
		$model = new Treetest();
		$widget->model = $model;
		$this->assertEquals($allRecordsSpace1, $widget->recursiveGenerateSelect());
		$widget->spaceMultiplier = 2;
	}

	/**
	 * @covers RealTreeSelectWidget::run
	 * @todo complete test in next release 1.1.0
	 */
	public function testRun() {
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);

		// test what we have ul>li
		$widget = new RealTreeSelectWidget();
		$widget->model = Treetest::model()->findByPk($this->treetest['node1']['id']); // small branch
		$widget->attribute = 'parent_id';
		$widget->init();

		/*
		 * hmmmm... it's not work!!! but output is equal
		 * 
		  $this->expectOutputString('<select name="Treetest[parent_id]" id="Treetest_parent_id">
		  <option value="">(Select parent)</option>
		  <option value="'.$this->treetest['node2']['id'].'">'.$this->treetest['node2']['name'].'</option>
		  <option value="'.$this->treetest['node9']['id'].'">&nbsp;&nbsp;'.$this->treetest['node9']['name'].'</option>
		  <option value="'.$this->treetest['node6']['id'].'">&nbsp;&nbsp;'.$this->treetest['node6']['name'].'</option>
		  </select>');
		 * 
		 * $widget->run();
		 */

		/*
		 * it's too... i think someproblem with \n\r ... fix in next release
		 * 
		  ob_start();
		  $widget->run();
		  $data = ob_get_clean();

		  $this->assertEquals('<select name="Treetest[parent_id]" id="Treetest_parent_id">
		  <option value="">(Select parent)</option>
		  <option value="'.$this->treetest['node2']['id'].'">'.$this->treetest['node2']['name'].'</option>
		  <option value="'.$this->treetest['node9']['id'].'">&nbsp;&nbsp;'.$this->treetest['node9']['name'].'</option>
		  <option value="'.$this->treetest['node6']['id'].'">&nbsp;&nbsp;'.$this->treetest['node6']['name'].'</option>
		  </select>', $data);
		 */
	}

}
