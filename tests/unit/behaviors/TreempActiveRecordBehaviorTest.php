<?php

/**
 * TreempActiveRecordBehaviorTest class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-treemp
 * @license		New BSD license
 */

/**
 * TreempActiveRecordBehaviorTest
 */
class TreempActiveRecordBehaviorTest extends CTestCase {

	/**
	 * This method is called before the first test of this test class is run.
	 *
	 * @since Method available since Release 3.4.0
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		// loading the database schema
		$testdataPath = Yii::getPathOfAlias('treemp.tests.env.schema');
		$createTableSql = file_get_contents($testdataPath . '/treetest.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();

		Yii::import('treemp.tests.env.models.*');
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		parent::setUp();

		// loading the database schema
		$testdataPath = Yii::getPathOfAlias('treemp.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/treetest.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();
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
	// service

	/**
	 * fast extract id from CAcriveRecord array
	 * @param CAcriveRecord[] $param active records array
	 * @return array ids
	 */
	private static function extractIds(array $param) {
		$result = array();
		foreach ($param as $entry) {
			$result[] = $entry->id;
		}
		return $result;
	}

	// =========================================================================
	// tests

	/**
	 * @covers TreempActiveRecordBehavior::treempGetNodeName
	 */
	public function testTreempGetNodeName() {
		$object = new Treetest();

		$nodeName = 'someName';

		// setup name via model
		$object->name = $nodeName;

		// get name via behavior
		$this->assertEquals($nodeName, $object->treempGetNodeName());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetParent
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 */
	public function testTreempGetParent() {
		$parentNode4 = Treetest::model()->findByPk(4);

		// get without cache
		$parentNode3 = $parentNode4->treempGetParent(false);
		$this->assertEquals(3, $parentNode3->id);

		// get with cache
		$parentNode1 = $parentNode3->treempGetParent();
		$this->assertEquals(1, $parentNode1->id);

		// get cached node
		$parentNode1Cached = $parentNode3->treempGetParent();
		$this->assertEquals(1, $parentNode1Cached->id);

		// check cache equals
		$this->assertEquals($parentNode1, $parentNode1Cached);

		// get null
		$parentNodeNull = $parentNode1->treempGetParent();
		$this->assertNull($parentNodeNull);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetChildren
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 */
	public function testTreempGetChildren() {
		$rootNode = Treetest::model()->findByPk(1);

		// get without cache
		$withoutCacheChildren = $rootNode->treempGetChildren(false);
		$this->assertInternalType('array', $withoutCacheChildren);
		$this->assertEquals(array(3, 7, 8), self::extractIds($withoutCacheChildren));

		// get with cache
		$childrenCached = $rootNode->treempGetChildren();
		$this->assertInternalType('array', $childrenCached);
		$this->assertEquals(array(3, 7, 8), self::extractIds($childrenCached));

		// get cached
		$childrenFromCached = $rootNode->treempGetChildren();
		$this->assertInternalType('array', $childrenFromCached);
		$this->assertEquals(array(3, 7, 8), self::extractIds($childrenFromCached));

		// check cache equals
		$this->assertEquals($childrenCached, $childrenFromCached);

		// pk same as cache ids
		$this->assertEquals(8, $childrenFromCached[8]->id);

		// get empty
		$emptyChildren = $childrenFromCached[8]->treempGetChildren();
		$this->assertInternalType('array', $emptyChildren);
		$this->assertEmpty($emptyChildren);

		// check order without cache
		$rootNode2 = Treetest::model()->findByPk(2);
		$orderedChildren = $rootNode2->treempGetChildren(false); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check order with cache
		$orderedChildren = $rootNode2->treempGetChildren(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check order from cache
		$orderedChildren = $rootNode2->treempGetChildren(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check keys identity for useCache or not
		$this->assertEquals(array_keys($withoutCacheChildren), array_keys($childrenFromCached));
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetChildrenCount
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 */
	public function testTreempGetChildrenCount() {
		$rootNode = Treetest::model()->findByPk(1);

		$this->assertEquals(3, $rootNode->treempGetChildrenCount(false)); // without cache
		$this->assertEquals(3, $rootNode->treempGetChildrenCount());  // with cache
		$this->assertEquals(3, $rootNode->treempGetChildrenCount());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetChildExists
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 */
	public function testTreempGetChildExists() {
		// has nodes
		$rootNode = Treetest::model()->findByPk(1);
		$this->assertTrue($rootNode->treempGetChildExists(false)); // without cache
		$this->assertTrue($rootNode->treempGetChildExists());   // with cache
		$this->assertTrue($rootNode->treempGetChildExists());   // use cache
		// empty subnode
		$noSubnode = Treetest::model()->findByPk(5);
		$this->assertFalse($noSubnode->treempGetChildExists(false)); // without cache
		$this->assertFalse($noSubnode->treempGetChildExists());  // with cache
		$this->assertFalse($noSubnode->treempGetChildExists());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetParentExists
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 */
	public function testGetParentExists() {
		// has parent
		$hasParent = Treetest::model()->findByPk(3);
		$this->assertTrue($hasParent->treempGetParentExists(false)); // without cache
		$this->assertTrue($hasParent->treempGetParentExists());  // with cache
		$this->assertTrue($hasParent->treempGetParentExists());  // use cache
		// empty parent
		$noParent = Treetest::model()->findByPk(1);
		$this->assertFalse($noParent->treempGetParentExists(false)); // without cache
		$this->assertFalse($noParent->treempGetParentExists());  // with cache
		$this->assertFalse($noParent->treempGetParentExists());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetRootline
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 * @covers TreempActiveRecordBehavior::buildPath
	 * @covers TreempActiveRecordBehavior::afterFind
	 * @covers TreempActiveRecordBehavior::indexIt
	 */
	public function testTreempGetRootline() {
		$model = new Treetest();
		$rootline = $model->treempGetRootline();

		// get without cache
		$withoutCacheChildren = $model->treempGetRootline(false);
		$this->assertInternalType('array', $withoutCacheChildren);
		$this->assertEquals(array(1, 2), self::extractIds($withoutCacheChildren));

		// get with cache
		$childrenCached = $model->treempGetRootline();
		$this->assertInternalType('array', $childrenCached);
		$this->assertEquals(array(1, 2), self::extractIds($childrenCached));

		// get cached
		$childrenFromCached = $model->treempGetRootline();
		$this->assertInternalType('array', $childrenFromCached);
		$this->assertEquals(array(1, 2), self::extractIds($childrenFromCached));

		// check cache equals
		$this->assertEquals($childrenCached, $childrenFromCached);

		// pk same as cache ids
		$this->assertEquals(2, $childrenFromCached[2]->id);

		// prepeare to next tests ... delete 1, 2
		foreach ($childrenFromCached as $entry) {
			$entry->delete();
		}
		$model->treempCleanoutCache(); // after delete need clean cache... or we need active and recursive cleaner =(
		// get empty
		$emptyRootline = $model->treempGetRootline(false);
		$this->assertInternalType('array', $emptyRootline);
		$this->assertEmpty($emptyRootline);

		$emptyCached = $model->treempGetRootline();
		$this->assertInternalType('array', $emptyCached);
		$this->assertEmpty($emptyCached);

		// insert new 2 records
		$model1 = new Treetest();
		$model1->name = 'b';
		$this->assertTrue($model1->save());

		$model2 = new Treetest();
		$model2->name = 'a';
		$this->assertTrue($model2->save());

		$model->treempCleanoutCache(); // after query last rootline its was cached
		// check order
		$orderedChildren = $model->treempGetRootline(false); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check order with cache
		$orderedChildren = $model->treempGetRootline(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check order from cache
		$orderedChildren = $model->treempGetRootline(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check keys identity for useCache or not
		$this->assertEquals(array_keys($withoutCacheChildren), array_keys($childrenFromCached));
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetPathArray
	 */
	public function testTreempGetPathArray() {
		$record = Treetest::model()->findByPk(5);
		$this->assertEquals('1:3:4:5:', $record->path);
		$this->assertEquals(array(1, 3, 4, 5), $record->treempGetPathArray());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetNodeByPk
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 */
	public function testTreempGetNodeByPk() {
		$model = new Treetest();

		// has element
		// without cache
		$someNode = $model->treempGetNodeByPk(1, false);
		$this->assertNotEmpty($someNode);

		// cached
		$someNodeCache = $model->treempGetNodeByPk(1);
		$this->assertNotEmpty($someNode);

		// get cache
		$someNodeCacheSame = $model->treempGetNodeByPk(1);
		$this->assertNotEmpty($someNode);

		// check cache identity
		$this->assertEquals($someNodeCache, $someNodeCacheSame);

		// no element
		// without cache
		$someNode = $model->treempGetNodeByPk(100500, false);
		$this->assertNull($someNode);

		// cached
		$someNode = $model->treempGetNodeByPk(100500);
		$this->assertNull($someNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetChildById
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 */
	public function testTreempGetChildById() {
		$model = Treetest::model()->findByPk(1);

		// not need test useCache... implemetns in testTreempGetNodeByPk
		// ok
		$treeNode = $model->treempGetChildById(5);
		$this->assertNotNull($treeNode);

		// find in db but it's not supposed
		$treeNode = $model->treempGetChildById(9);
		$this->assertNull($treeNode);

		// not find in db
		$treeNode = $model->treempGetChildById(100500);
		$this->assertNull($treeNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetParentById
	 * @covers TreempActiveRecordBehavior::getCacheStoreActiveRecords
	 * @covers TreempActiveRecordBehavior::getCacheStoreIndexChildren
	 */
	public function testTreempGetParentById() {
		$model = Treetest::model()->findByPk(5);

		// not need test useCache... implemetns in testGetNodeByPk
		// parent via path
		$treeNode = $model->treempGetParentById(1);
		$this->assertNotNull($treeNode);

		// not parent via path
		$treeNode = $model->treempGetParentById(9);
		$this->assertNull($treeNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempIsAncestor
	 */
	public function testTreempIsAncestor() {
		$model = Treetest::model()->findByPk(1);

		// not need test useCache... implemetns in testTreempGetNodeByPk
		// null check
		$this->assertFalse($model->treempIsAncestor(null));

		// himselfCheck
		$this->assertTrue($model->treempIsAncestor(1));

		// searchincild
		$this->assertTrue($model->treempIsAncestor(5));

		// searchincild not that branch
		$this->assertFalse($model->treempIsAncestor(9));

		// searchincild not find
		$this->assertFalse($model->treempIsAncestor(100500));
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempIsDescendant
	 */
	public function testTreempIsDescendant() {
		$model = Treetest::model()->findByPk(5);

		// not need test useCache... implemetns in testTreempGetNodeByPk
		// null check
		$this->assertFalse($model->treempIsDescendant(null));

		// himselfCheck
		$this->assertTrue($model->treempIsDescendant(5));

		// searchincild
		$this->assertTrue($model->treempIsDescendant(1));

		// searchincild not that branch
		$this->assertFalse($model->treempIsDescendant(9));

		// searchincild not find
		$this->assertFalse($model->treempIsDescendant(100500));
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetRootParent
	 */
	public function testGetRootParent() {
		$model = Treetest::model()->findByPk(5);

		// get root parent
		$rootParent = $model->treempGetRootParent();
		$this->assertEquals(1, $rootParent->id);

		// himself
		$rootParentHimseld = $rootParent->treempGetRootParent();
		$this->assertEquals($rootParent, $rootParentHimseld);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::treempCleanoutCache
	 */
	public function testTreempCleanoutCache() {
		$model = new Treetest();

		// cache node
		$cachedNode = $model->treempGetNodeByPk(1);
		$cachedNode->name = 'ulala100500';

		// load from cache node
		$cachedNodeFromCache = $model->treempGetNodeByPk(1);
		$this->assertEquals($cachedNode, $cachedNodeFromCache);

		$model->treempCleanoutCache();
		$cachedNode3Time = $model->treempGetNodeByPk(1);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::treempLoadAllTreeCache
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranchPackageMode
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranchRecursive
	 */
	public function testTreempLoadAllTreeCache() {
		$model = new Treetest();

		$model->treempLoadAllTreeCache();
		$cachedNode = $model->treempGetNodeByPk(1);   // cache node
		$cachedNode->name = 'ulala100500';
		$cachedNodeFromCache = $model->treempGetNodeByPk(1); // load from cache node
		$this->assertEquals($cachedNode, $cachedNodeFromCache);

		$model->treempLoadAllTreeCache();
		$cachedNode3Time = $model->treempGetNodeByPk(1);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::treempLoadBranchCache
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranchPackageMode
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranchRecursive
	 */
	public function testTreempLoadBranchCache() {
		$model = Treetest::model()->findByPk(3);

		$model->treempLoadBranchCache();
		$cachedNode = $model->treempGetNodeByPk(4); // cache node
		$cachedNode->name = 'ulala100500';
		$cachedNodeFromCache = $model->treempGetNodeByPk(4); // load from cache node
		$this->assertEquals($cachedNode, $cachedNodeFromCache);

		$model->treempLoadBranchCache();
		$cachedNode3Time = $model->treempGetNodeByPk(4);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);

		$model = Treetest::model()->findByPk(1);
		$model->treempLoadBranchCache();
		$this->assertNull($model->parent_id);

		$newModel = new TreetestPackage();
		$newModel->parent_id = 1;
		$newModel->name = 'supername2';
		$this->assertTrue($newModel->save());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPath
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranchPackageMode
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranchRecursive
	 * @covers TreempActiveRecordBehavior::buildPath
	 */
	public function testTreempRebuildAllPath() {
		$node1Src = Treetest::model()->findByPk(1);
		$node3Src = Treetest::model()->findByPk(3);
		$node4Src = Treetest::model()->findByPk(4);

		// set corrupt (chang path some nodes)
		Treetest::model()->updateByPk(1, array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk(1);

		Treetest::model()->updateByPk(3, array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk(3);

		Treetest::model()->updateByPk(4, array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk(4);

		// rebuild
		$node1Err->treempRebuildAllPath();

		// check
		$node1Recover = Treetest::model()->findByPk(1);
		$node3Recover = Treetest::model()->findByPk(3);
		$node4Recover = Treetest::model()->findByPk(4);

		// test for cached
		$this->assertNotEquals($node1Src->path, $node1Err->path);
		$this->assertNotEquals($node3Src->path, $node3Err->path);
		$this->assertNotEquals($node4Src->path, $node4Err->path);

		// test for recover
		$this->assertEquals($node1Src->path, $node1Recover->path);
		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);

		// WITHOUT CACHE
		// set corrupt (chang path some nodes)
		Treetest::model()->updateByPk(1, array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk(1);

		Treetest::model()->updateByPk(3, array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk(3);

		Treetest::model()->updateByPk(4, array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk(4);

		// rebuild
		$node1Err->treempRebuildAllPath(false);

		// check
		$node1Recover = Treetest::model()->findByPk(1);
		$node3Recover = Treetest::model()->findByPk(3);
		$node4Recover = Treetest::model()->findByPk(4);

		// test for recover
		$this->assertEquals($node1Src->path, $node1Recover->path);
		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranch
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranchPackageMode
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranchRecursive
	 * @covers TreempActiveRecordBehavior::buildPath
	 */
	public function testTreempRebuildAllPathBranch() {
		$node1Src = Treetest::model()->findByPk(1);
		$node3Src = Treetest::model()->findByPk(3);
		$node4Src = Treetest::model()->findByPk(4);

		// set corrupt (chang path some nodes)
		Treetest::model()->updateByPk(1, array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk(1);

		Treetest::model()->updateByPk(3, array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk(3);

		Treetest::model()->updateByPk(4, array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk(4);

		// REBUILD 3 AND NEXT BRANCH
		$node3Src->treempRebuildAllPathBranch();

		// check
		$node1Recover = Treetest::model()->findByPk(1);
		$node3Recover = Treetest::model()->findByPk(3);
		$node4Recover = Treetest::model()->findByPk(4);

		// test for cached
		$this->assertNotEquals($node1Src->path, $node1Err->path);
		$this->assertNotEquals($node3Src->path, $node3Err->path);
		$this->assertNotEquals($node4Src->path, $node4Err->path);

		// test for recover
		$this->assertEquals($node1Err->path, $node1Recover->path); // node 1 not change
		$this->assertNotEquals($node3Err->path, $node3Recover->path); // $node3Err with $node3Recover !!!!
		$this->assertNotEquals($node4Err->path, $node4Recover->path);

		// =====================================================================
		// now rebuild all
		$node1Err->treempRebuildAllPathBranch();

		// node 1 not change now! for correct work..

		Treetest::model()->updateByPk(3, array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk(3);

		Treetest::model()->updateByPk(4, array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk(4);

		$node3Src->treempRebuildAllPathBranch();
		$node3Recover = Treetest::model()->findByPk(3);
		$node4Recover = Treetest::model()->findByPk(4);

		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);

		// =====================================================================
		// WITHOUT CACHE

		Treetest::model()->updateByPk(3, array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk(3);

		Treetest::model()->updateByPk(4, array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk(4);

		$node3Src->treempRebuildAllPathBranch(false);
		$node3Recover = Treetest::model()->findByPk(3);
		$node4Recover = Treetest::model()->findByPk(4);

		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetBranchLikeCondition
	 */
	public function testTreempGetBranchLikeCondition() {
		$node = Treetest::model()->findByPk(4);

		$this->assertEquals('1:3:4:%', $node->treempGetBranchLikeCondition());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempCheckForLoop
	 */
	public function testTreempCheckForLoop() {
		$node = Treetest::model()->findByPk(1);

		// check detect loop
		$node->parent_id = 3;

		$this->assertTrue($node->treempCheckForLoop());

		$node->parent_id = 9;

		$this->assertFalse($node->treempCheckForLoop());
	}

	/**
	 * @covers TreempActiveRecordBehavior::beforeValidate
	 */
	public function testBeforeValidate() {
		$node = Treetest::model()->findByPk(1);

		// check detect loop
		$node->parent_id = 3;

		$this->assertFalse($node->save()); // not save
		$this->assertNotEmpty($node->errors['parent_id']); // check has error
		$this->assertContains('detect loop for new parent_id(3)', $node->errors['parent_id']); // check error msg

		$node->parent_id = 9;
		$this->assertTrue($node->save()); // ok
	}

	/**
	 * @covers TreempActiveRecordBehavior::afterSave
	 */
	public function testAfterSave() {
		$node = Treetest::model()->findByPk(3);

		$node->parent_id = 9;
		$this->assertTrue($node->save());

		// get next tree and check path
		$this->assertEquals('2:9:3:4:', $node->treempGetChildById(4)->path);
		$this->assertEquals('2:9:3:4:5:', $node->treempGetChildById(5)->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::afterSave
	 * @covers TreempActiveRecordBehavior::treempRebuildAllPathBranch
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranchPackageMode
	 * @covers TreempActiveRecordBehavior::afterConstruct
	 * @covers TreempActiveRecordBehavior::buildPath
	 */
	public function testAfterSavePackageMode() {
		$node = TreetestPackage::model()->findByPk(3);
		$node->parent_id = 9;
		$this->assertTrue($node->save());

		// get next tree and check path
		$this->assertEquals('2:9:3:4:', $node->treempGetChildById(4)->path);
		$this->assertEquals('2:9:3:4:5:', $node->treempGetChildById(5)->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetPathModels
	 * @covers TreempActiveRecordBehavior::primaryKeyName
	 */
	public function testTreempGetPathModels() {
		$node = Treetest::model()->findByPk(4);

		// pure sql
		$models = $node->treempGetPathModels(false);
		list($node1, $node3, $node4) = array_values($models);
		$this->assertEquals(1, $node1->getPrimaryKey());
		$this->assertEquals(3, $node3->getPrimaryKey());
		$this->assertEquals(4, $node4->getPrimaryKey());

		// cached get
		$modelsWithCache = $node->treempGetPathModels(true);
		list($node1, $node3, $node4) = array_values($modelsWithCache);
		$this->assertEquals(1, $node1->getPrimaryKey());
		$this->assertEquals(3, $node3->getPrimaryKey());
		$this->assertEquals(4, $node4->getPrimaryKey());

		// get from cache
		$modelsFromCache = $node->treempGetPathModels(true);
		list($node1, $node3, $node4) = array_values($modelsFromCache);
		$this->assertEquals(1, $node1->getPrimaryKey());
		$this->assertEquals(3, $node3->getPrimaryKey());
		$this->assertEquals(4, $node4->getPrimaryKey());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempGetOwnerClassName
	 */
	public function testTreempGetOwnerClassName() {
		$nodeOne = Treetest::model()->findByPk(1);
		$this->assertEquals('Treetest', $nodeOne->treempGetOwnerClassName());

		$nodeTwo = TreetestPackage::model()->findByPk(1);
		$this->assertEquals('TreetestPackage', $nodeTwo->treempGetOwnerClassName());
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempAppendChild
	 */
	public function testTreempAppendChild() {
		// std append
		$node1 = Treetest::model()->findByPk(1);
		$newNode = new Treetest();
		$newNode->name = 'newName1';
		$this->assertTrue($node1->treempAppendChild($newNode));
		
		// only set
		$newNode2 = new Treetest();
		$newNode2->name = 'newName2';
		$this->assertFalse($node1->treempAppendChild($newNode2, false));
		$this->assertTrue($newNode2->isNewRecord);
		$this->assertTrue($newNode2->save());
		
		// test error when saving
		$newNode3 = new Treetest();
		$this->assertFalse($node1->treempAppendChild($newNode3));	// not set name for $newNode3
		
		// change parent
		$nodePack1 = TreetestPackage::model()->findByPk(1);
		$nodePack2 = TreetestPackage::model()->findByPk(2);
		$this->assertNull($nodePack2->parent_id);
		$this->assertTrue($nodePack1->treempAppendChild($nodePack2));
		$this->assertEquals(1, $nodePack2->parent_id);
	}

	/**
	 * Test isNewRecord exception
	 * @covers TreempActiveRecordBehavior::treempAppendChild
	 * @expectedException CException
	 * @expectedExceptionMessage The current model can not be installed parents pass, as the current model is new
	 */
	public function testTreempAppendChildException() {
		$parentNewNode = new Treetest();
		$newNode = new Treetest();
		$newNode->name = 'newName1';

		$parentNewNode->treempAppendChild($newNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::treempAppendTo
	 */
	public function testTreempAppendTo() {
		// std append
		$node1 = Treetest::model()->findByPk(1);
		
		$newNode = new Treetest();
		$newNode->name = 'newName1';
		$this->assertTrue($newNode->treempAppendTo($node1));
		$this->assertEquals(1, $newNode->parent_id);
		
		// append as root
		$this->assertTrue($newNode->treempAppendTo(null));
		$this->assertNull($newNode->parent_id);
		
		// only set
		$newNode2 = new Treetest();
		$newNode2->name = 'newName2';
		$this->assertFalse($newNode2->treempAppendTo($node1, false));
		$this->assertTrue($newNode2->isNewRecord);
		$this->assertTrue($newNode2->save());
		
		// test error when saving
		$newNode3 = new Treetest();
		$this->assertFalse($node1->treempAppendTo($node1));	// not set name for $newNode3
		
		// change parent
		$nodePack1 = TreetestPackage::model()->findByPk(1);
		$nodePack2 = TreetestPackage::model()->findByPk(2);
		$this->assertTrue($nodePack2->treempAppendTo($nodePack1));
		$nodePack9 = TreetestPackage::model()->findByPk(9);
		$this->assertEquals('1:2:9:', $nodePack9->path);
	}

	/**
	 * Test isNewRecord exception
	 * @covers TreempActiveRecordBehavior::treempAppendTo
	 * @expectedException CException
	 * @expectedExceptionMessage The current model can not be set descendant transmitted, as transmitted model is new
	 */
	public function testTreempAppendToException() {
		$newNode = new Treetest();
		$newNode->name = 'newName1';
		
		$node1 = Treetest::model()->findByPk(1);
		$newNode->treempAppendTo($newNode);
	}

}
