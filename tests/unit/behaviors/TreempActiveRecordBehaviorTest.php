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
class TreempActiveRecordBehaviorTest extends CDbTestCase {

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
	 * @covers TreempActiveRecordBehavior::getNodeName
	 */
	public function testGetNodeName() {
		$object = new Treetest();

		$nodeName = 'someName';

		// setup name via model
		$object->name = $nodeName;

		// get name via behavior
		$this->assertEquals($nodeName, $object->getNodeName());
	}

	/**
	 * @covers TreempActiveRecordBehavior::getParent
	 */
	public function testGetParent() {
		$parentNode4 = Treetest::model()->findByPk($this->treetest['node4']['id']);

		// get without cache
		$parentNode3 = $parentNode4->getParent(false);
		$this->assertEquals($this->treetest['node3']['id'], $parentNode3->id);

		// get with cache
		$parentNode1 = $parentNode3->treempGetParent();
		$this->assertEquals($this->treetest['node1']['id'], $parentNode1->id);

		// get cached node
		$parentNode1Cached = $parentNode3->treempGetParent();
		$this->assertEquals($this->treetest['node1']['id'], $parentNode1Cached->id);

		// check cache equals
		$this->assertEquals($parentNode1, $parentNode1Cached);

		// get null
		$parentNodeNull = $parentNode1->treempGetParent();
		$this->assertNull($parentNodeNull);
	}

	/**
	 * @covers TreempActiveRecordBehavior::getChildren
	 */
	public function testGetChildren() {
		$rootNode = Treetest::model()->findByPk($this->treetest['node1']['id']);

		// get without cache
		$withoutCacheChildren = $rootNode->getChildren(false);
		$this->assertInternalType('array', $withoutCacheChildren);
		$this->assertEquals(array(3, 7, 8), self::extractIds($withoutCacheChildren));

		// get with cache
		$childrenCached = $rootNode->getChildren();
		$this->assertInternalType('array', $childrenCached);
		$this->assertEquals(array(3, 7, 8), self::extractIds($childrenCached));

		// get cached
		$childrenFromCached = $rootNode->getChildren();
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
		$rootNode2 = Treetest::model()->findByPk($this->treetest['node2']['id']);
		$orderedChildren = $rootNode2->getChildren(false); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check order with cache
		$orderedChildren = $rootNode2->getChildren(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check order from cache
		$orderedChildren = $rootNode2->getChildren(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($node9, $node6) = array_values($orderedChildren);
		$this->assertEquals(9, $node9->id);
		$this->assertEquals(6, $node6->id);

		// check keys identity for useCache or not
		$this->assertEquals(array_keys($withoutCacheChildren), array_keys($childrenFromCached));
	}

	/**
	 * @covers TreempActiveRecordBehavior::getChildrenCount
	 */
	public function testGetChildrenCount() {
		$rootNode = Treetest::model()->findByPk($this->treetest['node1']['id']);

		$this->assertEquals(3, $rootNode->getChildrenCount(false)); // without cache
		$this->assertEquals(3, $rootNode->getChildrenCount());  // with cache
		$this->assertEquals(3, $rootNode->getChildrenCount());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::getChildExists
	 */
	public function testGetChildExists() {
		// has nodes
		$rootNode = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$this->assertTrue($rootNode->getChildExists(false)); // without cache
		$this->assertTrue($rootNode->getChildExists());   // with cache
		$this->assertTrue($rootNode->getChildExists());   // use cache
		// empty subnode
		$noSubnode = Treetest::model()->findByPk($this->treetest['node5']['id']);
		$this->assertFalse($noSubnode->getChildExists(false)); // without cache
		$this->assertFalse($noSubnode->getChildExists());  // with cache
		$this->assertFalse($noSubnode->getChildExists());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::getParentExists
	 */
	public function testGetParentExists() {
		// has parent
		$hasParent = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$this->assertTrue($hasParent->getParentExists(false)); // without cache
		$this->assertTrue($hasParent->getParentExists());  // with cache
		$this->assertTrue($hasParent->getParentExists());  // use cache
		// empty parent
		$noParent = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$this->assertFalse($noParent->getParentExists(false)); // without cache
		$this->assertFalse($noParent->getParentExists());  // with cache
		$this->assertFalse($noParent->getParentExists());  // use cache
	}

	/**
	 * @covers TreempActiveRecordBehavior::getRootline
	 */
	public function testGetRootline() {
		$model = new Treetest();
		$rootline = $model->getRootline();

		// get without cache
		$withoutCacheChildren = $model->getRootline(false);
		$this->assertInternalType('array', $withoutCacheChildren);
		$this->assertEquals(array(1, 2), self::extractIds($withoutCacheChildren));

		// get with cache
		$childrenCached = $model->getRootline();
		$this->assertInternalType('array', $childrenCached);
		$this->assertEquals(array(1, 2), self::extractIds($childrenCached));

		// get cached
		$childrenFromCached = $model->getRootline();
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
		$model->cleanoutCache(); // after delete need clean cache... or we need active and recursive cleaner =(
		// get empty
		$emptyRootline = $model->getRootline(false);
		$this->assertInternalType('array', $emptyRootline);
		$this->assertEmpty($emptyRootline);

		$emptyCached = $model->getRootline();
		$this->assertInternalType('array', $emptyCached);
		$this->assertEmpty($emptyCached);

		// insert new 2 records
		$model1 = new Treetest();
		$model1->name = 'b';
		$this->assertTrue($model1->save());

		$model2 = new Treetest();
		$model2->name = 'a';
		$this->assertTrue($model2->save());

		$model->cleanoutCache(); // after query last rootline its was cached
		// check order
		$orderedChildren = $model->getRootline(false); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check order with cache
		$orderedChildren = $model->getRootline(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check order from cache
		$orderedChildren = $model->getRootline(); // see fixtures. id in one order, name in other order
		$this->assertInternalType('array', $orderedChildren);
		$this->assertCount(2, $orderedChildren);
		list($nodeA, $nodeB) = array_values($orderedChildren);
		$this->assertEquals('b', $nodeB->name);
		$this->assertEquals('a', $nodeA->name);

		// check keys identity for useCache or not
		$this->assertEquals(array_keys($withoutCacheChildren), array_keys($childrenFromCached));
	}

	/**
	 * @covers TreempActiveRecordBehavior::getPathArray
	 */
	public function testGetPathArray() {
		$record = Treetest::model()->findByPk($this->treetest['node5']['id']);
		$this->assertEquals('1:3:4:5:', $record->path);
		$this->assertEquals(array(1, 3, 4, 5), $record->getPathArray());
	}

	/**
	 * @covers TreempActiveRecordBehavior::getNodeByPk
	 */
	public function testGetNodeByPk() {
		$model = new Treetest();

		// has element
		// without cache
		$someNode = $model->getNodeByPk($this->treetest['node1']['id'], false);
		$this->assertNotEmpty($someNode);

		// cached
		$someNodeCache = $model->getNodeByPk($this->treetest['node1']['id']);
		$this->assertNotEmpty($someNode);

		// get cache
		$someNodeCacheSame = $model->getNodeByPk($this->treetest['node1']['id']);
		$this->assertNotEmpty($someNode);

		// check cache identity
		$this->assertEquals($someNodeCache, $someNodeCacheSame);

		// no element
		// without cache
		$someNode = $model->getNodeByPk(100500, false);
		$this->assertNull($someNode);

		// cached
		$someNode = $model->getNodeByPk(100500);
		$this->assertNull($someNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::getChildById
	 */
	public function testGetChildById() {
		$model = Treetest::model()->findByPk($this->treetest['node1']['id']);

		// not need test useCache... implemetns in testGetNodeByPk
		// ok
		$treeNode = $model->getChildById($this->treetest['node5']['id']);
		$this->assertNotNull($treeNode);

		// find in db but it's not supposed
		$treeNode = $model->getChildById($this->treetest['node9']['id']);
		$this->assertNull($treeNode);

		// not find in db
		$treeNode = $model->getChildById(100500);
		$this->assertNull($treeNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::getParentById
	 */
	public function testGetParentById() {
		$model = Treetest::model()->findByPk($this->treetest['node5']['id']);

		// not need test useCache... implemetns in testGetNodeByPk
		// parent via path
		$treeNode = $model->getParentById($this->treetest['node1']['id']);
		$this->assertNotNull($treeNode);

		// not parent via path
		$treeNode = $model->getParentById($this->treetest['node9']['id']);
		$this->assertNull($treeNode);
	}

	/**
	 * @covers TreempActiveRecordBehavior::isAncestor
	 */
	public function testIsAncestor() {
		$model = Treetest::model()->findByPk($this->treetest['node1']['id']);

		// not need test useCache... implemetns in testGetNodeByPk
		// null check
		$this->assertFalse($model->isAncestor(null));

		// himselfCheck
		$this->assertTrue($model->isAncestor($this->treetest['node1']['id']));

		// searchincild
		$this->assertTrue($model->isAncestor($this->treetest['node5']['id']));

		// searchincild not that branch
		$this->assertFalse($model->isAncestor($this->treetest['node9']['id']));

		// searchincild not find
		$this->assertFalse($model->isAncestor(100500));
	}

	/**
	 * @covers TreempActiveRecordBehavior::isDescendant
	 */
	public function testIsDescendant() {
		$model = Treetest::model()->findByPk($this->treetest['node5']['id']);

		// not need test useCache... implemetns in testGetNodeByPk
		// null check
		$this->assertFalse($model->isDescendant(null));

		// himselfCheck
		$this->assertTrue($model->isDescendant($this->treetest['node5']['id']));

		// searchincild
		$this->assertTrue($model->isDescendant($this->treetest['node1']['id']));

		// searchincild not that branch
		$this->assertFalse($model->isDescendant($this->treetest['node9']['id']));

		// searchincild not find
		$this->assertFalse($model->isDescendant(100500));
	}

	/**
	 * @covers TreempActiveRecordBehavior::getRootParent
	 */
	public function testGetRootParent() {
		$model = Treetest::model()->findByPk($this->treetest['node5']['id']);

		// get root parent
		$rootParent = $model->getRootParent();
		$this->assertEquals(1, $rootParent->id);

		// himself
		$rootParentHimseld = $rootParent->treempGetRootParent();
		$this->assertEquals($rootParent, $rootParentHimseld);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::cleanoutCache
	 */
	public function testCleanoutCache() {
		$model = new Treetest();
		
		// cache node
		$cachedNode = $model->getNodeByPk($this->treetest['node1']['id']);
		$cachedNode->name = 'ulala100500';
		
		// load from cache node
		$cachedNodeFromCache = $model->getNodeByPk($this->treetest['node1']['id']);
		$this->assertEquals($cachedNode, $cachedNodeFromCache);
		
		$model->cleanoutCache();
		$cachedNode3Time = $model->getNodeByPk($this->treetest['node1']['id']);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::loadAllTreeCache
	 */
	public function testLoadAllTreeCache() {
		$model = new Treetest();
		
		$model->loadAllTreeCache();
		
		$cachedNode = $model->getNodeByPk($this->treetest['node1']['id']);			// cache node
		$cachedNode->name = 'ulala100500';
		$cachedNodeFromCache = $model->getNodeByPk($this->treetest['node1']['id']);	// load from cache node
		$this->assertEquals($cachedNode, $cachedNodeFromCache);
		
		$model->loadAllTreeCache();
		$cachedNode3Time = $model->getNodeByPk($this->treetest['node1']['id']);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);
	}

	/**
	 * can check this indirectly
	 * 
	 * @covers TreempActiveRecordBehavior::loadAllBranchCache
	 */
	public function testLoadAllBranchCache() {
		$model = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		$model->loadAllBranchCache();
		$cachedNode = $model->getNodeByPk($this->treetest['node4']['id']);			// cache node
		$cachedNode->name = 'ulala100500';
		$cachedNodeFromCache = $model->getNodeByPk($this->treetest['node4']['id']);	// load from cache node
		$this->assertEquals($cachedNode, $cachedNodeFromCache);
		
		$model->loadAllBranchCache();
		$cachedNode3Time = $model->getNodeByPk($this->treetest['node4']['id']);
		$this->assertNotEquals($cachedNode, $cachedNode3Time);
	}

	/**
	 * @covers TreempActiveRecordBehavior::rebuildAllPath
	 */
	public function testRebuildAllPath() {
		$node1Src = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$node3Src = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Src = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// set corrupt (chang path some nodes)
		Treetest::model()->updateByPk($this->treetest['node1']['id'], array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk($this->treetest['node1']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node3']['id'], array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node4']['id'], array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// rebuild
		$node1Err->rebuildAllPath();
		
		// check
		$node1Recover = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$node3Recover = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Recover = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
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
		Treetest::model()->updateByPk($this->treetest['node1']['id'], array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk($this->treetest['node1']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node3']['id'], array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node4']['id'], array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// rebuild
		$node1Err->rebuildAllPath(false);
		
		// check
		$node1Recover = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$node3Recover = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Recover = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// test for recover
		$this->assertEquals($node1Src->path, $node1Recover->path);
		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::rebuildAllPathBranch
	 */
	public function testRebuildAllPathBranch() {
		$node1Src = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$node3Src = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Src = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// set corrupt (chang path some nodes)
		Treetest::model()->updateByPk($this->treetest['node1']['id'], array('path' => '100500:1:'));
		$node1Err = Treetest::model()->findByPk($this->treetest['node1']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node3']['id'], array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node4']['id'], array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// REBUILD 3 AND NEXT BRANCH
		$node3Src->rebuildAllPathBranch();
		
		// check
		$node1Recover = Treetest::model()->findByPk($this->treetest['node1']['id']);
		$node3Recover = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Recover = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		// test for cached
		$this->assertNotEquals($node1Src->path, $node1Err->path);
		$this->assertNotEquals($node3Src->path, $node3Err->path);
		$this->assertNotEquals($node4Src->path, $node4Err->path);
		
		// test for recover
		$this->assertNotEquals($node1Src->path, $node1Recover->path);	// node 1 not change
		
		// it is not right work because $node1Src has error... but it's changed... it's right work
		$this->assertNotEquals($node3Err->path, $node3Recover->path);	// $node3Err with $node3Recover !!!!
		$this->assertNotEquals($node4Err->path, $node4Recover->path);
		
		// =====================================================================
		// now rebuild all
		$node1Err->rebuildAllPath();
		
		// node 1 not change now! for correct work..
		
		Treetest::model()->updateByPk($this->treetest['node3']['id'], array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node4']['id'], array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		$node3Src->rebuildAllPathBranch();
		$node3Recover = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Recover = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);
		
		// =====================================================================
		// WITHOUT CACHE
		
		Treetest::model()->updateByPk($this->treetest['node3']['id'], array('path' => '100500:2:'));
		$node3Err = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		Treetest::model()->updateByPk($this->treetest['node4']['id'], array('path' => '100500:3:'));
		$node4Err = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		$node3Src->rebuildAllPathBranch(false);
		$node3Recover = Treetest::model()->findByPk($this->treetest['node3']['id']);
		$node4Recover = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		$this->assertEquals($node3Src->path, $node3Recover->path);
		$this->assertEquals($node4Src->path, $node4Recover->path);
	}

	/**
	 * @covers TreempActiveRecordBehavior::getBranchLikeCondition
	 */
	public function testGetBranchLikeCondition() {
		$node = Treetest::model()->findByPk($this->treetest['node4']['id']);
		
		$this->assertEquals('1:3:4:%', $node->getBranchLikeCondition());
	}

	/**
	 * @covers TreempActiveRecordBehavior::checkForLoop
	 */
	public function testCheckForLoop() {
		$node = Treetest::model()->findByPk($this->treetest['node1']['id']);
		
		// check detect loop
		$node->parent_id = 3;
		
		$this->assertTrue($node->checkForLoop());
		
		$node->parent_id = 9;
		
		$this->assertFalse($node->checkForLoop());
	}

	/**
	 * @covers TreempActiveRecordBehavior::beforeValidate
	 */
	public function testBeforeValidate() {
		$node = Treetest::model()->findByPk($this->treetest['node1']['id']);
		
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
		$node = Treetest::model()->findByPk($this->treetest['node3']['id']);
		
		$node->parent_id = 9;
		$this->assertTrue($node->save());
		
		// get next tree and check path
		$this->assertEquals('2:9:3:4:', $node->getChildById($this->treetest['node4']['id'])->path);
		$this->assertEquals('2:9:3:4:5:', $node->getChildById($this->treetest['node5']['id'])->path);
	}

}
