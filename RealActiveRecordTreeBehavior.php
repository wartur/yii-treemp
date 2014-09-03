<?php

/**
 * RealActiveRecordTreeBehavior class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		http://wartur.ru/me	(ПОМЕНЯТЬ НА GITHUB)
 * @license		http://wartur.ru/license
 */

/**
 * Behavior for work with materialized path
 * It support:
 * - fast query use materialized path (LIKE x:y:%)
 * - fast update materialized path if tree change
 * - check for loop
 * - ram cache for fast work with tree or branch (about: $_cacheActiveRecords, $_cacheIndexChildren)
 * 
 * For work need:
 * - pk				- recommend integer
 * - parent_id		- recommend integer
 * - path			- materialized path field
 * - name|sort		- field for sorting
 * 
 * Using in CActiveRecord
 * <pre>
 * public function behaviors() {
 * 	return array_merge(parent::behaviors(), array(
 * 		'realActiveRecordTreeBehavior' => array(
 * 			'class' => 'RealActiveRecordTreeBehavior',
 * 			// other params
 * 			'sequenceOrder' => 'name ASC',
 * 			'nameField' => 'name',
 * 			'parentField' => 'parent_id',
 * 			'pathField' => 'path',
 * 			'useCacheInternal' => true
 * 		)
 * 	));
 * }
 * </pre>
 * 
 * Ext information:
 * if you set useCacheInternal = false, and send to method useCache = false,
 * model will use only parent_id for query tree from db (pure work with RDBM)
 * it's not fast, but pure
 * 
 * Common recommendation:
 * For all operation recommend USE TRANSACTIONS
 */
class RealActiveRecordTreeBehavior extends CActiveRecordBehavior {

	/**
	 * Ram cache rootline name
	 */
	const ROOT = 'root';

	/**
	 * delimetr symbol for materialized path
	 */
	const PATH_DELIMETR = ':';

	/**
	 * @var string order condition
	 */
	public $sequenceOrder = 'name ASC';

	/**
	 * @var string name field
	 */
	public $nameField = 'name';

	/**
	 * @var string parent id field
	 */
	public $parentField = 'parent_id';

	/**
	 * @var string materialized path field
	 * format 1:3:4:
	 * search as LIKE '1:3:%'
	 */
	public $pathField = 'path';

	/**
	 * @var boolean use ram cache for internal operation
	 */
	public $useCacheInternal = true;

	/**
	 * @var array of classname of array ram cache for active record
	 * keep record as pk => activeRecord
	 */
	private static $_cacheActiveRecords = array();

	/**
	 * @var array of classname of array ram cache for tree relationship
	 * parent_id => array(pk1 => children1, pk2 => children2, ..., pkN => childrenN)
	 */
	private static $_cacheIndexChildren = array();

	/**
	 * @var mixed original parent id (see: AfterFind)
	 */
	protected $originalParentId = null;

	/**
	 * @var int original pk (see: AfterFind)
	 */
	protected $originalPk = null;

	/**
	 * Get current node name
	 * @return string current node name
	 */
	public function getNodeName() {
		return $this->owner->{$this->nameField};
	}

	/**
	 * Return parent node
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord parent model
	 */
	public function getParent($useCache = true) {
		$parentField = $this->parentField;

		if (empty($this->owner->$parentField)) {
			return null;
		}

		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();

			if (isset($cacheActiveRecords[$this->owner->$parentField])) {
				return $cacheActiveRecords[$this->owner->$parentField];
			}

			$parent = $this->owner->findByPk($this->owner->$parentField);

			// cache this and cache parent record
			$cacheActiveRecords[$this->owner->getPrimaryKey()] = $this->owner;
			$cacheActiveRecords[$this->owner->$parentField] = $parent;

			/*
			 * If you think what need cache $cacheIndexChildre...
			 * This not need do. Because in cache can be only full children set.
			 * Partially filled will break ram cached algorithm. For get
			 * full children set use self::getChildren
			 */

			return $parent;
		} else {
			return $this->owner->findByPk($this->owner->$parentField);
		}
	}

	/**
	 * Get all children set
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord[] models of next level children set sort by $sequenceOrder
	 */
	public function getChildren($useCache = true) {
		$currentPk = $this->owner->getPrimaryKey();

		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
			$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

			if (isset($cacheIndexChildren[$currentPk])) {
				return $cacheIndexChildren[$currentPk];
			}

			$children = $this->owner->findAllByAttributes(array($this->parentField => $currentPk), array('order' => $this->sequenceOrder));
			$cacheActiveRecords[$currentPk] = $this->owner;

			// if $children is empty, we was to have cached empty children set
			$cacheIndexChildren[$currentPk] = array();

			foreach ($children as $entry) {
				$childPk = $entry->getPrimaryKey();

				$cacheActiveRecords[$childPk] = $entry;
				$cacheIndexChildren[$currentPk][$childPk] = $entry;

				/*
				 * Do not cache $cacheIndexChildren[$childPk] = array(), because
				 * partial raw caching broke cache algorithm
				 */
			}

			return $cacheIndexChildren[$currentPk];
		} else {
			return self::indexIt($this->owner->findAllByAttributes(array($this->parentField => $currentPk), array('order' => $this->sequenceOrder)));
		}
	}

	/**
	 * Get children count
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return integer children count
	 */
	public function getChildrenCount($useCache = true) {
		if ($useCache) {
			return count($this->getChildren($useCache)); // query from internal mechanism and count it
		} else {
			return $this->owner->countByAttributes(array($this->parentField => $this->owner->getPrimaryKey()));
		}
	}

	/**
	 * Get child exists
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return boolean true if has children
	 */
	public function getChildExists($useCache = false) {
		return $this->getChildrenCount($useCache) > 0;
	}

	/**
	 * Get parent exists
	 * @return boolean true if has parent
	 */
	public function getParentExists() {
		$parentField = $this->parentField;

		return $this->owner->$parentField !== null;
	}

	/**
	 * Get all rootline tree records
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord[] return record set sort by $sequenceOrder
	 */
	public function getRootline($useCache = true) {
		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
			$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

			if (isset($cacheIndexChildren[self::ROOT])) {
				return $cacheIndexChildren[self::ROOT];
			}

			$rootLine = $this->owner->model()->findAllByAttributes(array($this->parentField => null), array('order' => $this->sequenceOrder));

			$cacheActiveRecords[$this->owner->getPrimaryKey()] = $this->owner;

			$cacheIndexChildren[self::ROOT] = array();

			foreach ($rootLine as $entry) {
				$childPk = $entry->getPrimaryKey();

				$cacheActiveRecords[$childPk] = $entry;
				$cacheIndexChildren[self::ROOT][$childPk] = $entry;

				/*
				 * Do not cache $cacheIndexChildren[$childPk] = array(), because
				 * partial raw caching broke cache algorithm
				 */
			}

			return $cacheIndexChildren[self::ROOT];
		} else {
			return self::indexIt($this->owner->model()->findAllByAttributes(array($this->parentField => null), array('order' => $this->sequenceOrder)));
		}
	}

	/**
	 * Get path like array
	 * for example if you has 1:5:7 return array(1, 5, 7)
	 * @return array exploding path to array
	 */
	public function getPathArray() {
		$treeIndexField = $this->pathField;

		// trim end delimenr and explode it
		return explode(self::PATH_DELIMETR, trim($this->owner->$treeIndexField, self::PATH_DELIMETR));
	}

	/**
	 * Get any node by pk
	 * @param mixed $pk searching primary key
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord|null node record or null
	 */
	public function getNodeByPk($pk, $useCache = true) {
		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();

			if (isset($cacheActiveRecords[$pk])) {
				return $cacheActiveRecords[$pk];
			}

			$element = $this->owner->findByPk($pk);

			$cacheActiveRecords[$pk] = $element;

			/*
			 * Nod add to $cacheIndexChildren see getParent method
			 */

			return $element;
		} else {
			return $this->owner->findByPk($pk);
		}
	}

	/**
	 * Search in childrens by pk
	 * @param mixed $pk searching primary key
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord|null node record or null
	 */
	public function getChildById($pk, $useCache = true) {
		// get supposed child
		$supposedChild = $this->getNodeByPk($pk, $useCache);
		if (empty($supposedChild)) {
			return null;
		}

		// if this pk has in supposed child then is child
		if (in_array($this->owner->getPrimaryKey(), $supposedChild->getPathArray())) {
			return $supposedChild;
		} else {
			return null;
		}
	}

	/**
	 * Search in parens by pk
	 * @param mixed $pk searching primary key
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord|null node record or null
	 */
	public function getParentById($pk, $useCache = true) {
		if (in_array($pk, $this->getPathArray())) { // if has pk in path
			return $this->getNodeByPk($pk, $useCache);
		} else {
			return null;
		}
	}

	/**
	 * Whether the current object is an ancestor of the parameters for pk
	 * @param mixed $pk searching primary key
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return boolean
	 */
	public function isAncestor($pk, $useCache = true) {
		if (empty($pk)) { // it not parent =)
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // it himself
			return true;
		}

		return $this->getChildById($pk, $useCache) !== null; // search in child
	}

	/**
	 * Whether the current object is a descendant of the pk of the parameters
	 * @param mixed $pk searching primary key
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return boolean
	 */
	public function isDescendant($pk, $useCache = true) {
		if (empty($pk)) { // it not parent =)
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // it himself
			return true;
		}

		return $this->getParentById($pk, $useCache) !== null; // search in parent
	}

	/**
	 * Get rootline parent
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return CActiveRecord|null root node record or null
	 */
	public function getRootParent($useCache = true) {
		$treeIndex = $this->getPathArray();
		$parentField = $this->parentField;

		// if himself
		if (empty($this->owner->$parentField)) {
			return $this->owner;
		}

		// fast query by path
		return $this->getNodeByPk($treeIndex[0], $useCache);
	}

	/**
	 * Cleanout ram cache
	 * 
	 * With cache variable we work through pointers
	 */
	public function cleanoutCache() {
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheActiveRecords = array();

		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();
		$cacheIndexChildren = array();
	}

	/**
	 * Load all record and build tree
	 * 
	 * Oprimisation procedure if you know what you need all tree nodes
	 * 
	 * method NOT USE materialized path
	 */
	public function loadAllTreeCache() {
		$this->cleanoutCache();

		$allTreeEntry = $this->owner->findAll(array('order' => $this->sequenceOrder));

		$parentField = $this->parentField;
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

		foreach ($allTreeEntry as $entry) {
			$entryPk = $entry->getPrimaryKey();
			$parentEntryPk = $entry->$parentField;

			// cache model
			$cacheActiveRecords[$entryPk] = $entry;

			// cache node
			if ($parentEntryPk === null) { // is rootline node
				$cacheIndexChildren[self::ROOT][$entryPk] = $entry;

				if (!isset($cacheIndexChildren[$entryPk])) {
					$cacheIndexChildren[$entryPk] = array();
				}
			} else {
				$cacheIndexChildren[$parentEntryPk][$entryPk] = $entry;

				if (!isset($cacheIndexChildren[$entryPk])) {
					$cacheIndexChildren[$entryPk] = array();
				}
			}
		}
	}

	/**
	 * Load branch record and build branch tree
	 * 
	 * Oprimisation procedure if you know what you need all branch nodes
	 * 
	 * Method USE materialized path for fast work
	 */
	public function loadAllBranchCache() {
		/*
		 * Information:
		 * Delete all cache. For correct work need delete all current cache.
		 * 
		 * We might use partial clean but is difficult operation.
		 */
		$this->cleanoutCache();

		/*
		 * Information:
		 * Fast query use materialized path
		 * 
		 * Why I'm use this where update materialized path
		 * If change pk|parent_id of this model, next query using current path as condition,
		 * we will to have all old branch with old path, but new pk|parent_id
		 * In next we need rebuild path use rebuildAllBranchIndex.
		 */
		$criteria = new CDbCriteria();
		$criteria->addSearchCondition($this->pathField, $this->getBranchLikeCondition(), false);
		$criteria->order = $this->sequenceOrder;
		$branchTree = $this->owner->findAll($criteria);

		$parentField = $this->parentField;
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

		foreach ($branchTree as $entry) {
			$entryPk = $entry->getPrimaryKey();
			$parentEntryPk = $entry->$parentField;

			$cacheActiveRecords[$entryPk] = $entry; // model caching

			if (empty($parentEntryPk)) {
				$cacheIndexChildren[self::ROOT][$entryPk] = $entry;
			} else {
				$cacheIndexChildren[$parentEntryPk][$entryPk] = $entry;
			}

			if (!isset($cacheIndexChildren[$entryPk])) {
				$cacheIndexChildren[$entryPk] = array();   // set empty children. may be will set tree nodes IN THIS METHOD next
			}
		}
	}

	/**
	 * Rebuild all tree index
	 * 
	 * DANGER: Is most hard operation!!!
	 * Method need for rebuld all tree if you have data integrity error
	 * 
	 * This operation not use LIKE query
	 */
	public function rebuildAllPath($useCache = true) {
		if ($useCache) {
			$this->loadAllTreeCache();
		}

		$rootLine = $this->getRootline($useCache);

		foreach ($rootLine as $rootEntry) {
			$rootEntry->rebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Rebuild all tree branch
	 * @param boolean $useCache true if need ram cache (default=true)
	 */
	public function rebuildAllPathBranch($useCache = true) {
		if ($useCache) {
			$this->loadAllBranchCache();
		}

		$this->rebuildAllPathBranchRecursive($useCache);
	}

	/**
	 * Recursive bypass all tree brunch and rabuild path
	 * @param boolean $useCache true if need ram cache (default=true)
	 */
	protected function rebuildAllPathBranchRecursive($useCache) {
		$this->buildTreeIndex($useCache);

		$children = $this->getChildren($useCache);

		foreach ($children as $entry) {
			$entry->rebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Get LIKE condition for query brunch
	 * @return string
	 */
	public function getBranchLikeCondition() {
		$treeIndexField = $this->pathField;

		return $this->owner->$treeIndexField . '%';
	}

	/**
	 * Get owner class name. Use in ram cache
	 * return string owner class name
	 */
	protected function getOwnerClassName() {
		return get_class($this->owner);
	}

	/**
	 * Get ram cache store of current model class
	 * see self::$_cacheActiveRecords
	 * return array pointer on ram cache array
	 */
	protected function &getCacheStoreActiveRecords() {
		$ownerClassName = $this->getOwnerClassName();

		if (!isset(self::$_cacheActiveRecords[$ownerClassName])) {
			self::$_cacheActiveRecords[$ownerClassName] = array();
		}

		return self::$_cacheActiveRecords[$ownerClassName];
	}

	/**
	 * Get ram cache store of tree relationship
	 * see self::$_cacheIndexChildren
	 * return array pointer on ram cache array
	 */
	protected function &getCacheStoreIndexChildren() {
		$ownerClassName = $this->getOwnerClassName();

		if (!isset(self::$_cacheIndexChildren[$ownerClassName])) {
			self::$_cacheIndexChildren[$ownerClassName] = array();
		}

		return self::$_cacheIndexChildren[$ownerClassName];
	}

	/**
	 * Build path for current record
	 * @param boolean $useCache true if need ram cache (default=true)
	 * @return boolean true if success update path
	 */
	protected function buildTreeIndex($useCache = true) {
		$parentField = $this->parentField;
		$treeIndexField = $this->pathField;

		$parent = $this->getParentExists() ? $this->getParent($useCache) : null;

		if (empty($parent)) { // is rootline node
			$this->owner->$treeIndexField = $this->owner->getPrimaryKey() . ':';
		} else {
			$this->owner->$treeIndexField = $parent->$treeIndexField . $this->owner->getPrimaryKey() . ':';
		}

		// with path update pk and parent_id
		$this->originalParentId = $this->owner->$parentField;
		$this->originalPk = $this->owner->getPrimaryKey();

		if ($this->owner->isNewRecord) {
			/*
			 * if you insert new record might be double insert,
			 * it's correct work for CActiveRecord,
			 * but is problem for us and we fixed it
			 */
			return $this->owner->updateByPk($this->owner->id, array(
						$treeIndexField => $this->owner->$treeIndexField
			));
		} else {
			return $this->owner->save();
		}
	}

	/**
	 * Insert this node after node specify by $pk
	 * @todo in 1.1.0 when use RealActiveRecordSorterBehavior implement sortable nodes
	 * @param integer $pk node where insert after this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treeInsertAfter($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Insert this node before node specify by $pk
	 * @todo in 1.1.0 when use RealActiveRecordSorterBehavior implement sortable nodes
	 * @param integer $pk node where insert before this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treeInsertBefore($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Append this node as subnode to begin node specify by $pk
	 * @todo in 1.1.0 when use RealActiveRecordSorterBehavior implement sortable nodes
	 * @param integer $pk node where append to begin this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treeAppendToBegin($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Append this node as subnode to end node specify by $pk
	 * @todo in 1.1.0 when use RealActiveRecordSorterBehavior implement sortable nodes
	 * @param integer $pk node where append to end this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treeAppendToEnd($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Get way from root to node
	 * @todo in 1.1.0
	 * @param mixed $pk identity of node. If null it's current node
	 * @throws Exception Not Implemented Execption
	 * 
	 * // must return CActiveRecord[]
	 */
	public function treeGetWayFromRoot($pk = null) {
		throw new Exception('Not Implemented Execption');
	}
	
	public function checkForLoop() {
		$parentField = $this->parentField;
		
		return $this->owner->$parentField != $this->originalParentId && $this->isAncestor($this->owner->$parentField, false);
	}

	/**
	 * @param CModelEvent $event event parameter
	 */
	public function afterFind($event) {
		parent::afterFind($event);

		// save original parametrs
		$this->originalParentId = $this->owner->{$this->parentField};
		$this->originalPk = $this->owner->getPrimaryKey();
	}

	/**
	 * @param CModelEvent $event event parameter
	 */
	public function beforeValidate($event) {
		parent::beforeValidate($event);

		$parentField = $this->parentField;

		// check at loop for new parent_id
		if ($this->checkForLoop()) {
			$this->owner->addError($parentField, Yii::t('RealActiveRecordTreeBehavior', 'detect loop for new parent_id({parent_id})', array('{parent_id}' => $this->owner->$parentField)));
		}
	}

	/**
	 * @param CModelEvent $event event parameter
	 */
	public function afterSave($event) {
		parent::afterSave($event);

		$parentField = $this->parentField;

		// if change pk|parent_id
		if ($this->owner->$parentField != $this->originalParentId || $this->owner->getPrimaryKey() != $this->originalPk) {
			$this->rebuildAllPathBranch($this->useCacheInternal);
		}
	}

	/**
	 * Index data by id
	 * @param CActiveRecord $input
	 */
	private static function indexIt($input) {
		$result = [];
		foreach ($input as $entry) {
			$result[$entry->getPrimaryKey()] = $entry;
		}
		return $result;
	}

}
