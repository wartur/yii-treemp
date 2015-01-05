<?php

/**
 * TreempActiveRecordBehavior class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-treemp
 * @license	 New BSD license
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
 * - path			- field for materialized path (For MySQL length <= 255)
 * - name|sort		- field for sorting
 * 
 * Using in CActiveRecord
 * <pre>
 * public function behaviors() {
 * 	return array_merge(parent::behaviors(), array(
 * 		'TreempActiveRecordBehavior' => array(
 * 			'class' => 'treemp.behaviors.TreempActiveRecordBehavior',
 * 			// other params
 * 			'sequenceOrder' => 'name ASC',
 * 			'nameField' => 'name',
 * 			'parentField' => 'parent_id',
 * 			'pathField' => 'path',
 * 			'useCacheInternal' => true,
 *			'usePathPackageUpdate' => true
 * 		)
 * 	));
 * }
 * </pre>
 * 
 * Additional Information:
 * If you are using $useCacheInternal = false and passed into the method $useCache = false
 * model will use only the requests on the field parent_id, without path.
 * This is not recommended because of performance degradation
 * 
 * Recommendations:
 * For all operations update the tree you want to use transactions
 */
class TreempActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * ID of the root entry in the cache
	 */
	const ROOT = 'root';

	/**
	 * Materialized path separator
	 */
	const PATH_DELIMETR = ':';

	/**
	 * @var string the sort order of the derivation tree records
	 */
	public $sequenceOrder = 'name ASC';

	/**
	 * @var string field name
	 */
	public $nameField = 'name';

	/**
	 * @var string field ID of the parent
	 */
	public $parentField = 'parent_id';

	/**
	 * @var string field materialized path
	 * format 1:3:4:
	 * search as LIKE '1:3:%'
	 */
	public $pathField = 'path';

	/**
	 * @var boolean flag the cache in internal operations model,
	 * such as updating the materialized path in recursive mode
	 * first caches all entries branches widely regarded as one request,
	 * and then updates them.
	 */
	public $useCacheInternal = true;

	/**
	 * @var boolean flag to use batch update materialized path.
	 * If false is used recursively update the materialized path.
	 * Also read about the flag $useCacheInternal
	 */
	public $usePathPackageUpdate = true;

	/**
	 * @var int packet size records for processing, when use usePathPackageUpdate
	 * is used to speed up the database. More than this number,
	 * the more records at a time is loaded from the database.
	 * If the packet size is equal to 1,
	 * it is assumed that the batch processing is disabled
	 */
	public $packageSize = 200;

	/**
	 * @var array an array of models grouped under a specific name class.
	 * (to support the behavior of many classes at the same time)
	 * Structure:
	 * <pre>
	 * array(
	 * 		'classname' => array(
	 * 			pk1 => model1,
	 * 			pk2 => model2,
	 * 			....
	 * 			pkN => modelN
	 * 		)
	 * ));
	 * </pre>
	 */
	private static $_cacheActiveRecords = array();

	/**
	 * @var array an array of links grouped under a specific name class.
	 * (to support the behavior of many classes at the same time)
	 * Structure:
	 * <pre>
	 * array(
	 * 		'classname' => array(
	 * 			pk1 => array(
	 * 				'childrenPk1' => childrenModel1,
	 * 				'childrenPk2' => childrenModel2,
	 * 				... 
	 * 				'childrenPkN' => childrenModelN,
	 * 			),
	 * 			....
	 * 			pkN => array(...)
	 * 		)
	 * ));
	 * </pre>
	 */
	private static $_cacheIndexChildren = array();

	/**
	 * @var mixed the original value parent_id (see: AfterFind)
	 * Is used for the activation of Conduct
	 */
	protected $originalParentId = null;

	/**
	 * @var int the original value of the primary key (see: AfterFind)
	 * Is used for the activation of Conduct
	 */
	protected $originalPk = null;

	/**
	 * Получить название текущей ноды
	 * @return string название текущей ноды
	 */
	public function treempGetNodeName() {
		return $this->owner->{$this->nameField};
	}

	/**
	 * Get the parent node
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord|null parent model or null if this is the root
	 */
	public function treempGetParent($useCache = true) {
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

			// кешировать найденную и родительскую запись
			$cacheActiveRecords[$this->owner->getPrimaryKey()] = $this->owner;
			$cacheActiveRecords[$this->owner->$parentField] = $parent;

			/*
			 * If you think that there must write to the $cacheIndexChildren...
			 * To do so is not required, because the cache can be cached either completely or not at all cached.
			 * Incomplete filling can disrupt the caching algorithm.
			 * For a complete cache is required to use the method self::treempGetChildren or self::loadAllBranchCache
			 */

			return $parent;
		} else {
			return $this->owner->findByPk($this->owner->$parentField);
		}
	}

	/**
	 * Get all the descendants of the current record
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord[] model descendants sorted by $sequenceOrder
	 */
	public function treempGetChildren($useCache = true) {
		$currentPk = $this->owner->getPrimaryKey();

		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
			$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

			if (isset($cacheIndexChildren[$currentPk])) {
				return $cacheIndexChildren[$currentPk];
			}

			$children = $this->owner->findAllByAttributes(array($this->parentField => $currentPk), array('order' => $this->sequenceOrder));
			$cacheActiveRecords[$currentPk] = $this->owner;

			// Если потомков не найден, кешируем пустой набор записей
			$cacheIndexChildren[$currentPk] = array();

			foreach ($children as $entry) {
				$childPk = $entry->getPrimaryKey();

				$cacheActiveRecords[$childPk] = $entry;
				$cacheIndexChildren[$currentPk][$childPk] = $entry;

				/*
				 * Not cached $cacheIndexChildren[$childPk] = array(), as partial filling cache algorithm breaks
				 */
			}

			return $cacheIndexChildren[$currentPk];
		} else {
			return self::indexIt($this->owner->findAllByAttributes(array($this->parentField => $currentPk), array('order' => $this->sequenceOrder)));
		}
	}

	/**
	 * Get the number of descendants
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return integer number of descendants
	 */
	public function treempGetChildrenCount($useCache = true) {
		if ($useCache) {
			return count($this->treempGetChildren($useCache)); // Запросим через внутренние механизмы поведения
		} else {
			return $this->owner->countByAttributes(array($this->parentField => $this->owner->getPrimaryKey()));
		}
	}

	/**
	 * Are there descendants of the current model
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return boolean true if has
	 */
	public function treempGetChildExists($useCache = false) {
		return $this->treempGetChildrenCount($useCache) > 0;
	}

	/**
	 * Whether there is an ancestor of the current model
	 * @return boolean true if has parent
	 */
	public function treempGetParentExists() {
		$parentField = $this->parentField;

		return $this->owner->$parentField !== null;
	}

	/**
	 * Get a list of root models
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord[] model ancestors sorted by $sequenceOrder
	 */
	public function treempGetRootline($useCache = true) {
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
				 * Not cached $cacheIndexChildren[$childPk] = array(), as partial filling cache algorithm breaks
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
	public function treempGetPathArray() {
		// trim end delimenr and explode it
		return explode(self::PATH_DELIMETR, trim($this->owner->{$this->pathField}, self::PATH_DELIMETR));
	}

	/**
	 * Getting the path models, sorted by nesting
	 * 
	 * In carrying out this method in a loop, for example when unloading or output data in the table, it is advisable to consider the feasibility of using loadAllTreeCache or loadAllBranchCache.
	 * Otherwise, the algorithm tries to download as efficiently as possible through the use of cache entries.
	 * Disabling caching negatively affect the performance of path generation.
	 * 
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord[] модели пути упорядоченные по вложенности
	 */
	public function treempGetPathModels($useCache = true) {
		$result = array();
		$pathArray = $this->treempGetPathArray();

		if ($useCache) {
			// попытаться загрузить ноду из кеша и собрать последовательность
			foreach ($pathArray as $entryId) {
				$result[$entryId] = $this->treempGetNodeByPk($entryId, $useCache);
			}
		} else {
			// загрузить одним запросом и собрать последовательность
			$criteria = new CDbCriteria();
			$criteria->addInCondition($this->primaryKeyName(), $pathArray);
			$models = self::indexIt($this->owner->model()->findAll($criteria));

			foreach ($pathArray as $entryId) {
				if (empty($models[$entryId])) {
					Yii::log("Data corruption. Not found ID = $entryId, though he were present in the path", CLogger::LEVEL_WARNING);
					$result[$entryId] = null; // is this normal behavior?
				} else {
					$result[$entryId] = $models[$entryId];
				}
			}
		}
		
		return $result;
	}

	/**
	 * Получить элемент дерева
	 * @param mixed $pk первичный ключ
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord|null model or null if such a record is not found
	 */
	public function treempGetNodeByPk($pk, $useCache = true) {
		if ($useCache) {
			$cacheActiveRecords = &$this->getCacheStoreActiveRecords();

			if (isset($cacheActiveRecords[$pk])) {
				return $cacheActiveRecords[$pk];
			}

			$element = $this->owner->findByPk($pk);

			$cacheActiveRecords[$pk] = $element;

			/*
			 * If you think that there must write to the $cacheIndexChildren...
			 * To do so is not required, because the cache can be cached either completely or not at all cached.
			 * Incomplete filling can disrupt the caching algorithm.
			 * For a complete cache is required to use the method self::treempGetChildren or self::loadAllBranchCache
			 */

			return $element;
		} else {
			return $this->owner->findByPk($pk);
		}
	}

	/**
	 * Search descendants PK
	 * @param mixed $pkSeeking primary key
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord|null model or null if such a record is not found, or it is not a child of the current
	 */
	public function treempGetChildById($pk, $useCache = true) {
		// get the expected offspring
		$supposedChild = $this->treempGetNodeByPk($pk, $useCache);
		if (empty($supposedChild)) {
			return null;
		}

		// check that the current PK model is within the proposed model
		if (in_array($this->owner->getPrimaryKey(), $supposedChild->treempGetPathArray())) {
			return $supposedChild;
		} else {
			return null;
		}
	}

	/**
	 * Search parents on PK
	 * @param mixed $pk Seeking primary key
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord|null model or null if such a record is not found, or it is not the parent of the current
	 */
	public function treempGetParentById($pk, $useCache = true) {
		if (in_array($pk, $this->treempGetPathArray())) { // if has pk in path
			return $this->treempGetNodeByPk($pk, $useCache);
		} else {
			return null;
		}
	}

	/**
	 * Specifies that the specified record is the ancestor of the specified record for PK
	 * @param mixed $pk the primary key value for comparison
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return boolean
	 */
	public function treempIsAncestor($pk, $useCache = true) {
		if (empty($pk)) {
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // itself is an ancestor
			return true;
		}

		// search heirs PK, if the model is found, then the current model is the ancestor
		return $this->treempGetChildById($pk, $useCache) !== null;
	}

	/**
	 * Specifies that the specified record is the successor to the specified record PK
	 * @param mixed $pk the primary key value for comparison
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return boolean
	 */
	public function treempIsDescendant($pk, $useCache = true) {
		if (empty($pk)) {
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // itself is an ancestor
			return true;
		}

		// search for ancestors PK, if the model is found, then the current model is the successor
		return $this->treempGetParentById($pk, $useCache) !== null;
	}

	/**
	 * Get root branches
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return CActiveRecord|null model or null if such records are not found (which is unlikely)
	 */
	public function treempGetRootParent($useCache = true) {
		$pathArray = $this->treempGetPathArray();
		$parentField = $this->parentField;

		// if himself
		if (empty($this->owner->$parentField)) {
			return $this->owner;
		}

		// fast query by path
		return $this->treempGetNodeByPk($pathArray[0], $useCache);
	}

	/**
	 * Cleanup of the ram cache
	 */
	public function treempCleanoutCache() {
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheActiveRecords = array();

		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();
		$cacheIndexChildren = array();
	}

	/**
	 * Load all records and build a tree
	 * 
	 * Note optimization:
	 * Use this method if you know that it will take the whole tree.
	 * example work you can see in TreempDropdownWidget
	 * 
	 * Method DOES NOT use the materialized path.
	 * It can be used to restore the materialized path in case of failure, using parent_id
	 */
	public function treempLoadAllTreeCache() {
		$this->treempCleanoutCache();

		$allTreeEntry = $this->owner->findAll(array('order' => $this->sequenceOrder));

		$parentField = $this->parentField;
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

		foreach ($allTreeEntry as $entry) {
			$entryPk = $entry->getPrimaryKey();
			$parentEntryPk = $entry->$parentField;

			// caching model
			$cacheActiveRecords[$entryPk] = $entry;

			// caching links
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
	 * Load all records and build a tree branch
	 * 
	 * The optimized procedure, if you know that it will take all the records tree branch
	 * 
	 * The method USES the materialized path to optimize the query to the database
	 */
	public function treempLoadBranchCache() {
		$this->treempCleanoutCache();

		/*
		 * Information:
		 * Quick inquiry materialized path
		 * 
		 * How does it work when you upgrade parent_id?
		 * After updating the record such as moving tree branches in a database is outdated information in the path.
		 * There is a request for branches outdated information.
		 * Next, we construct the tree is saved and all the descendants of the method rebuildAllBranchIndex indicating a new path
		 */
		$criteria = new CDbCriteria();
		$criteria->addSearchCondition($this->pathField, $this->treempGetBranchLikeCondition(), false);
		$criteria->order = $this->sequenceOrder;
		$branchTree = $this->owner->findAll($criteria);

		$parentField = $this->parentField;
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();

		foreach ($branchTree as $entry) {
			$entryPk = $entry->getPrimaryKey();
			$parentEntryPk = $entry->$parentField;

			$cacheActiveRecords[$entryPk] = $entry; // caching model

			if (empty($parentEntryPk)) {
				$cacheIndexChildren[self::ROOT][$entryPk] = $entry;
			} else {
				$cacheIndexChildren[$parentEntryPk][$entryPk] = $entry;
			}

			if (!isset($cacheIndexChildren[$entryPk])) {
				$cacheIndexChildren[$entryPk] = array();   // Place an empty can be filled in the next iteration
			}
		}
	}

	/**
	 * Completely rebuild the materialized path
	 * 
	 * DANGER: This is the most difficult operation!!!
	 * The method can be used to restore the integrity of the data, or for the generation of the first path materialized
	 * 
	 * Method DOES NOT use the materialized path
	 * 
	 * If you want to build a big tree (over 10k records), use $ useCache = false
	 * 
	 * @param boolean $useCache true for using cache (default=true)
	 */
	public function treempRebuildAllPath($useCache = true) {
		if ($useCache) {
			$this->treempLoadAllTreeCache();
		}

		$rootLine = $this->treempGetRootline($useCache);

		foreach ($rootLine as $rootEntry) {
			$rootEntry->treempRebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Generate a LIKE query for branches
	 * @return string
	 */
	public function treempGetBranchLikeCondition() {
		return $this->owner->{$this->pathField} . '%';
	}

	/**
	 * Get the name of the current class
	 * return string the name of the class
	 */
	public function treempGetOwnerClassName() {
		return get_class($this->owner);
	}

	/**
	 * Add a child of the current node
	 * @param CActiveRecord $childModel model to add to the descendants of the current
	 * @param boolean $autoSave immediately write the changes to the database
	 * @return boolean true is save success. If pass $autoSave = false, then return false
	 * @throws CException if owner model is new
	 */
	public function treempAppendChild($childModel, $autoSave = true) {
		if($this->owner->isNewRecord) {
			throw new CException(Yii::t('TreempActiveRecordBehavior', 'The current model can not be installed parents pass, as the current model is new'));
		}
		
		$childModel->{$this->parentField} = $this->owner->id;
		
		if($autoSave) {
			// restructuring path will itself
			return $childModel->save();
		} else {
			return false;
		}
	}

	/**
	 * Add the current node in the descendants of the specified parent
	 * @param CActiveRecord|null $parentModel model or null, if you want to add as the root node
	 * @param boolean $autoSave immediately write the changes to the database
	 * @return boolean true is save success. If pass $autoSave = false, then return false
	 * @throws CException if $parentModel is new
	 */
	public function treempAppendTo($parentModel, $autoSave = true) {
		if(empty($parentModel)) {
			$this->owner->{$this->parentField} = null;
		} else {
			if($parentModel->isNewRecord) {
				throw new CException(Yii::t('TreempActiveRecordBehavior', 'The current model can not be set descendant transmitted, as transmitted model is new'));
			}
			
			$this->owner->{$this->parentField} = $parentModel->getPrimaryKey();
		}
		
		// restructuring path will itself
		if($autoSave) {
			return $this->owner->save();
		} else {
			return false;
		}
	}

	/**
	 * Insert the current node after specified PK
	 * @param integer $pk node where insert after this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treempInsertAfter($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Insert the current node to the specified PK
	 * @param integer $pk node where insert before this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treempInsertBefore($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Add to the descendants of the specified PC in the first position
	 * @param integer $pk node where append to begin this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treempAppendToBegin($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Add to the descendants of the specified PC to the last position of the position
	 * @param integer $pk node where append to end this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treempAppendToEnd($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Rebuild materialized path tree branches
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 */
	public function treempRebuildAllPathBranch($useCache = true) {
		// batch update does not work if the original is damaged materialized path
		if ($this->usePathPackageUpdate) {
			$this->rebuildAllPathBranchPackageMode($useCache);
		} else {
			if ($useCache) {
				$this->treempLoadBranchCache();
			}

			$this->treempRebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Rebuild materialized path tree branches using the batch method of FIR
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 */
	private function rebuildAllPathBranchPackageMode($useCache = true) {
		$pathField = $this->pathField;

		if ($this->owner->isNewRecord) {
			// if this is a new record, then just build a new path
			$this->buildPath($useCache);
		} else {
			// if it is not a new record, then update all descendants
			$srcPath = $this->owner->$pathField;

			// generate a path for the root entry
			$this->buildPath($useCache);

			$condition = new CDbCriteria();
			$condition->addSearchCondition($this->pathField, $srcPath . '%', false);
			$condition->limit = $this->packageSize;
			$condition->offset = 0;

			// batch processing
			do {
				$nodes = $this->owner->model()->findAll($condition);

				foreach ($nodes as $entry) {
					$branchPath = str_replace($srcPath, '', $entry->$pathField);
					$entry->$pathField = $this->owner->$pathField . $branchPath;
					$entry->save();
				}

				// the next batch
				$condition->offset += $this->packageSize;
			} while (count($nodes) == $this->packageSize);
			
			// after changes in the offspring, clean obsolete cache
			$this->treempCleanoutCache();
		}
	}

	/**
	 * Recursive tree and field change path
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 */
	public function treempRebuildAllPathBranchRecursive($useCache = true) {
		$this->buildPath($useCache);

		$children = $this->treempGetChildren($useCache);

		foreach ($children as $entry) {
			$entry->treempRebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Check for loop or cycle
	 * @return type
	 */
	public function treempCheckForLoop() {
		$parentField = $this->parentField;

		return $this->owner->$parentField != $this->originalParentId && $this->treempIsAncestor($this->owner->$parentField, false);
	}

	/**
	 * Get prompt cache models of the current class
	 * see self::$_cacheActiveRecords
	 * return array A POINTER to the cache
	 */
	private function &getCacheStoreActiveRecords() {
		$ownerClassName = $this->treempGetOwnerClassName();

		if (!isset(self::$_cacheActiveRecords[$ownerClassName])) {
			self::$_cacheActiveRecords[$ownerClassName] = array();
		}

		return self::$_cacheActiveRecords[$ownerClassName];
	}

	/**
	 * Get the operational cash bonds of the current class
	 * see self::$_cacheIndexChildren
	 * return array A POINTER to the cache
	 */
	private function &getCacheStoreIndexChildren() {
		$ownerClassName = $this->treempGetOwnerClassName();

		if (!isset(self::$_cacheIndexChildren[$ownerClassName])) {
			self::$_cacheIndexChildren[$ownerClassName] = array();
		}

		return self::$_cacheIndexChildren[$ownerClassName];
	}

	/**
	 * Build path field for the current record.
	 * This requires a parent to know the current path and PK
	 * @param boolean $useCache true for use in the preparation of the cache (default=true)
	 * @return boolean true if the model is able to maintain
	 */
	private function buildPath($useCache = true) {
		$parentField = $this->parentField;
		$pathField = $this->pathField;

		$parent = $this->treempGetParentExists() ? $this->treempGetParent($useCache) : null;

		if (empty($parent)) { // is the root node
			$this->owner->$pathField = $this->owner->getPrimaryKey() . ':';
		} else {
			$this->owner->$pathField = $parent->$pathField . $this->owner->getPrimaryKey() . ':';
		}

		// along with the path to update the cache of the original values
		$this->originalParentId = $this->owner->$parentField;
		$this->originalPk = $this->owner->getPrimaryKey();

		if ($this->owner->isNewRecord) {
			/*
			 * If you are adding a new entry can be double insertion.
			 * This work correctly CActiveRecord, but the problem for us.
			 * fix it!
			 * 
			 * It can be treated through a temporary change of scenario models, but it seemed to me more than.
			 * Furthermore, it may have side effects in other works.
			 */
			return $this->owner->updateByPk($this->owner->id, array(
						$pathField => $this->owner->$pathField
			));
		} else {
			return $this->owner->save();
		}
	}

	/**
	 * Getting the name of the primary key
	 * @return string|array line primary key field or an array of composite key
	 */
	private function primaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
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

		// check at loop for new parent_id
		if ($this->treempCheckForLoop()) {
			$this->owner->addError($this->parentField, Yii::t('TreempActiveRecordBehavior', 'detect loop for new parent_id({parent_id})', array('{parent_id}' => $this->owner->{$this->parentField})));
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
			$this->treempRebuildAllPathBranch($this->useCacheInternal);
		}
	}

	/**
	 * Validation of the setting is correct behavior
	 * @param CEvent $event
	 */
	public function afterConstruct($event) {
		parent::afterConstruct($event);

		// Only in debug mode. On production is a waste of electricity
		if (YII_DEBUG) {
			if (!is_string($this->primaryKeyName())) {
				throw new CException(Yii::t('TreempActiveRecordBehavior', 'The library does not know how to work a composite primary key'));
			}
		}
	}

	/**
	 * Индексировать список моделей по PK
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
