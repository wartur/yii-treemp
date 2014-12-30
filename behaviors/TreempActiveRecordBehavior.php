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
 * 			'useCacheInternal' => true
 * 		)
 * 	));
 * }
 * </pre>
 * 
 * Дополнительная информация:
 * Есл вы используете useCacheInternal = false и передаете в методы useCache = false
 * мобель будет использовать только запросы по полю parent_id, без использования path
 * Это не рекомендуется из-за ухудшения производительности
 * 
 * Рекомендации:
 * Для всех операций обновлений дерева требуется использовать транзакции
 */
class TreempActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * Идентификатор корневой записи в кеше
	 */
	const ROOT = 'root';

	/**
	 * Разделитель материализованного пути
	 */
	const PATH_DELIMETR = ':';

	/**
	 * @var string порядок сортировки при выводе записей дерева
	 */
	public $sequenceOrder = 'name ASC';

	/**
	 * @var string поле названия
	 */
	public $nameField = 'name';

	/**
	 * @var string поле идентфикатор родителья
	 */
	public $parentField = 'parent_id';

	/**
	 * @var string поле материализованного пути
	 * format 1:3:4:
	 * search as LIKE '1:3:%'
	 */
	public $pathField = 'path';

	/**
	 * @var boolean флаг использования кеша во внутренних операциях модели,
	 * например обновление материализованного пути в рекурсивном режиме
	 * сначала кеширует все записи ветки одим запросом, а потом обновляет их.
	 */
	public $useCacheInternal = true;

	/**
	 * @var boolean флаг использования пакетного обновления материализованного пути.
	 * Если false будет использовано рекурсивное обновление материализованного пути.
	 * Так же читайте про флаг useCacheInternal
	 * 
	 * @todo Имплементировать в 2.0.0
	 */
	public $usePathPackageUpdate = true;

	/**
	 * @var int packet size records for processing, when use usePathPackageUpdate
	 * is used to speed up the database. More than this number,
	 * the more records at a time is loaded from the database.
	 * If the packet size is equal to 1,
	 * it is assumed that the batch processing is disabled
	 * 
	 * See ALGORITHM.md
	 */
	public $packageSize = 200;

	/**
	 * @var array массив моделей группированные под определенным названием класса.
	 * (для поддержки поведением множества классов одновременно)
	 * Структура:
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
	 * @var array массив связей группированные под определенным названием класса.
	 * (для поддержки поведением множества классов одновременно)
	 * Структура:
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
	 * @var mixed оригинальное значение parent_id (see: AfterFind)
	 * Используется для активации действий поведения
	 */
	protected $originalParentId = null;

	/**
	 * @var int оригинальное значение первичного ключа (see: AfterFind)
	 * Используется для активации действий поведения
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
	 * Получить родительскую ноду
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord|null родительская модель или null если это корень
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
			 * Если вы считаете, что тут требуется записывать в $cacheIndexChildren...
			 * Этого делать не требуется, потому что кеш может быть кеширован
			 * либо полностью, либо вообще не кеширован. Неполное заполнение
			 * может нарушить работу алгоритма кеширования. Для получения полного
			 * кеша требуеся пользоваться методом getChildren или loadAllBranchCache
			 */

			return $parent;
		} else {
			return $this->owner->findByPk($this->owner->$parentField);
		}
	}

	/**
	 * Получить всех потомков текущей записи
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord[] модели потомки, упорядоченные по $sequenceOrder
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
				 * Не кешируйте $cacheIndexChildren[$childPk] = array(), так как
				 * частичное заполнение кеша сломает алгорим
				 */
			}

			return $cacheIndexChildren[$currentPk];
		} else {
			return self::indexIt($this->owner->findAllByAttributes(array($this->parentField => $currentPk), array('order' => $this->sequenceOrder)));
		}
	}

	/**
	 * Получить количество потомков
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return integer количество потомков
	 */
	public function treempGetChildrenCount($useCache = true) {
		if ($useCache) {
			return count($this->treempGetChildren($useCache)); // Запросим через внутренние механизмы поведения
		} else {
			return $this->owner->countByAttributes(array($this->parentField => $this->owner->getPrimaryKey()));
		}
	}

	/**
	 * Присутствуют ли потомки у текущей модели
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return boolean если true присутсвут
	 */
	public function treempGetChildExists($useCache = false) {
		return $this->treempGetChildrenCount($useCache) > 0;
	}

	/**
	 * Присутсвует ли предок у текущей модели
	 * @return boolean true if has parent
	 */
	public function treempGetParentExists() {
		$parentField = $this->parentField;

		return $this->owner->$parentField !== null;
	}

	/**
	 * Получить список корневых моделей
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord[] модели предки, упорядоченные по $sequenceOrder
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
				 * Не кешируйте $cacheIndexChildren[$childPk] = array(), так как
				 * частичное заполнение кеша сломает алгорим
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
	 * Получение моделей пути, упорядоченные по вложенности
	 * 
	 * При выполнении данного метода в цикле, напимер при выгрузке данных или
	 * вывода в таблице рекомендуется продумать целесообразность использования
	 * loadAllTreeCache или loadAllBranchCache. В противном случае алгоритм попытается
	 * максимально эффективно загрузить записи через использование кеша. Отключение
	 * кешированя крайне негативно скажется на производительности генерации пути.
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

			return $result;
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
	}

	/**
	 * Получить элемент дерева
	 * @param mixed $pk первичный ключ
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord|null модель или null если такой записи не найдено
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
			 * Если вы считаете, что тут требуется записывать в $cacheIndexChildren...
			 * Этого делать не требуется, потому что кеш может быть кеширован
			 * либо полностью, либо вообще не кеширован. Неполное заполнение
			 * может нарушить работу алгоритма кеширования. Для получения полного
			 * кеша требуеся пользоваться методом getChildren или loadAllBranchCache
			 */

			return $element;
		} else {
			return $this->owner->findByPk($pk);
		}
	}

	/**
	 * Поиск в потомках по PK
	 * @param mixed $pk искомый первичный ключ
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord|null модель или null если такой записи не найдено, либо она не является потомоком текущей
	 */
	public function treempGetChildById($pk, $useCache = true) {
		// получить предполагаемого потомка
		$supposedChild = $this->treempGetNodeByPk($pk, $useCache);
		if (empty($supposedChild)) {
			return null;
		}

		// проверяем, что PK текущей моделт находится в предках предполагаемой модели
		if (in_array($this->owner->getPrimaryKey(), $supposedChild->treempGetPathArray())) {
			return $supposedChild;
		} else {
			return null;
		}
	}

	/**
	 * Поиск в родителях по PK
	 * @param mixed $pk искомый первичный ключ
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord|null модель или null если такой записи не найдено, либо она не является предеком текущей
	 */
	public function treempGetParentById($pk, $useCache = true) {
		if (in_array($pk, $this->treempGetPathArray())) { // if has pk in path
			return $this->treempGetNodeByPk($pk, $useCache);
		} else {
			return null;
		}
	}

	/**
	 * Определяет, что указанная запись является предком для записи с указанным PK
	 * @param mixed $pk значение первичного ключа для сравнения
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return boolean
	 */
	public function treempIsAncestor($pk, $useCache = true) {
		if (empty($pk)) { // точно не предок =)
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // самjому себе является предком
			return true;
		}

		// поиск в наследниках PK, если модель будет найдена, значит текущая модель является предком
		return $this->treempGetChildById($pk, $useCache) !== null;
	}

	/**
	 * Определяет, что указанная запись является наследником для записи с указанным PK
	 * @param mixed $pk значение первичного ключа для сравнения
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return boolean
	 */
	public function treempIsDescendant($pk, $useCache = true) {
		if (empty($pk)) { // точно не наследник =)
			return false;
		}

		if ($this->owner->getPrimaryKey() == $pk) { // самому себе является наследником
			return true;
		}

		// поиск в предках по PK, если модель будет найдена, значит текущая модель является наследником
		return $this->treempGetParentById($pk, $useCache) !== null;
	}

	/**
	 * Получить корень ветки
	 * @param boolean $useCache true для использования кеша при получении (default=true)
	 * @return CActiveRecord|null модель или null если такой записи не найдено (что маловероятно)
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
	 * Отчистна оперативного кеша
	 */
	public function treempCleanoutCache() {
		$cacheActiveRecords = &$this->getCacheStoreActiveRecords();
		$cacheActiveRecords = array();

		$cacheIndexChildren = &$this->getCacheStoreIndexChildren();
		$cacheIndexChildren = array();
	}

	/**
	 * Загрузить все записи и построить дерево
	 * 
	 * Заметка по оптимизации:
	 * Используйте данный метод если заранее известно, что потребуется все дерево.
	 * Пример работы: TreempDropdownWidget
	 * 
	 * Метод НЕ ИСПОЛЬЗУЕТ материализованные пути. Его можно использовать
	 * для восстановления материализованного пути в случае сбоя, используя parent_id
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

			// кешируем модель
			$cacheActiveRecords[$entryPk] = $entry;

			// кешируем связи
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
	 * Загрузить все записи ветки и построить дерево
	 * 
	 * Оптимизированная процедура, если заранее известно, что потребуются
	 * все записи ветки дерева
	 * 
	 * Метод ИСПОЛЬЗУЕТ материализованные пути для оптимизации запроса в БД
	 */
	public function treempLoadBranchCache() {
		$this->treempCleanoutCache();

		/*
		 * Information:
		 * Быстрый запрос по материализованному пути
		 * 
		 * Как это работает при обновлении parent_id?
		 * После обновления записи например при перемещении ветки дерева
		 * в базе данных остается устаревшая информация в поле path. Идет
		 * запрос ветки по устаревшей информации. Далее строится дерево
		 * и происходит созранения всех потомков в методе rebuildAllBranchIndex
		 * с указанием нового path
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

			$cacheActiveRecords[$entryPk] = $entry; // кеширование модели

			if (empty($parentEntryPk)) {
				$cacheIndexChildren[self::ROOT][$entryPk] = $entry;
			} else {
				$cacheIndexChildren[$parentEntryPk][$entryPk] = $entry;
			}

			if (!isset($cacheIndexChildren[$entryPk])) {
				$cacheIndexChildren[$entryPk] = array();   // установить пустым, может быть заполнится при следующей итерации
			}
		}
	}

	/**
	 * Полностью перестроить материализованный путь
	 * 
	 * DANGER: Это наиболее тяжелая операция!!!
	 * Метод может использоваться для восстановления целостности данных, либо
	 * для первой генерациии материализованного пути
	 * 
	 * Метод НЕ ИСПОЛЬЗУЕТ материализованные пути
	 * @param boolean $useCache true для использования кеша (default=true)
	 */
	public function treempRebuildAllPath($useCache = true) {
		if ($useCache) {
			$this->treempLoadAllTreeCache();
		}

		$rootLine = $this->treempGetRootline($useCache);

		foreach ($rootLine as $rootEntry) {
			$rootEntry->rebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Генерировать условие LIKE для запроса ветки
	 * @return string
	 */
	public function treempGetBranchLikeCondition() {
		return $this->owner->{$this->pathField} . '%';
	}

	/**
	 * Получить название текущего класса
	 * return string название класса
	 */
	public function treempGetOwnerClassName() {
		return get_class($this->owner);
	}

	/**
	 * Добавить потомка к текущей ноде
	 * @param CActiveRecord $childModel
	 * @param boolean $autoSave сразу записать изменения в базу данных
	 */
	public function treempAppendChild($childModel, $autoSave = true) {
		if($this->owner->isNewRecord) {
			throw new CException('Текущую модель невозможно установить родителем переданной, так как текущая модель является новой');
		}
		
		$childModel->{$this->parentField} = $this->owner->id;
		
		if($autoSave) {
			// перестройка path произойдет сама
			$childModel->save();
		}
	}

	/**
	 * Добавить текущую ноду в потомки к указанному родителю
	 * @param CActiveRecord|null $parentModel модель или null, если требуется добавить как корневую ноду
	 * @param boolean $autoSave сразу записать изменения в базу данных
	 */
	public function treempAppendTo($parentModel, $autoSave = true) {
		if(empty($parentModel)) {
			$this->owner->{$this->parentField} = null;
		} else {
			if($parentModel->isNewRecord) {
				throw new CException('Текущую модель невозможно установить потомком переданной, так как переданная модель является новой');
			}
			
			$this->owner->{$this->parentField} = $parentModel->getPrimaryKey();
		}
		
		// перестройка path произойдет сама
		if($autoSave) {
			$this->owner->save();
		}
	}

	/**
	 * Вставить текущую ноду после указанного PK
	 * @param integer $pk node where insert after this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treempInsertAfter($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Вставить текущую ноду перед указанным PK
	 * @param integer $pk node where insert before this node
	 * @throws Exception Not Implemented Execption
	 */
	public function treempInsertBefore($pk) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Добавить в потомки указаного PK на первую позицию
	 * @param integer $pk node where append to begin this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treempAppendToBegin($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Добавить в потомки указаного PK на последнюю позицию
	 * @param integer $pk node where append to end this subnode
	 * @throws Exception Not Implemented Execption
	 */
	public function treempAppendToEnd($pk = null) {
		throw new Exception('Not Implemented Execption');
	}

	/**
	 * Перестроить материализованный путь ветки дерева
	 * @param boolean $useCache true для использования кеша (default=true)
	 */
	private function rebuildAllPathBranch($useCache = true) {
		if ($this->usePathPackageUpdate) {
			$this->rebuildAllPathBranchPackageMode($useCache);
		} else {
			if ($useCache) {
				$this->treempLoadBranchCache();
			}

			$this->rebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Перестроить материализованный путь ветки дерева используя
	 * пакетный нерекурсивный метод
	 */
	private function rebuildAllPathBranchPackageMode($useCache = true) {
		$pathField = $this->pathField;

		if ($this->owner->isNewRecord) {
			// если это новая запись, то просто строим новый path
			$this->buildPath($useCache);
		} else {
			// если это не новая запись, то обновляем всех потомков
			$srcPath = $this->owner->$pathField;

			// сгенерировать path для корневой записи
			$this->buildPath($useCache);

			$condition = new CDbCriteria();
			$condition->addSearchCondition($this->pathField, $srcPath . '%', false);
			$condition->limit = $this->packageSize;
			$condition->offset = 0;

			// пакетная обработка
			do {
				$nodes = $this->owner->model()->findAll($condition);

				foreach ($nodes as $entry) {
					$branchPath = str_replace($srcPath, '', $entry->$pathField);
					$entry->$pathField = $this->owner->$pathField . $branchPath;
					$entry->save();
				}

				// следующий пакет
				$condition->offset += $this->packageSize;
			} while (count($nodes) == $this->packageSize);
		}
	}

	/**
	 * Рекурсивный обход дерева и изменение поля path
	 * @param boolean $useCache true для использования кеша (default=true)
	 */
	private function rebuildAllPathBranchRecursive($useCache) {
		$this->buildPath($useCache);

		$children = $this->treempGetChildren($useCache);

		foreach ($children as $entry) {
			$entry->rebuildAllPathBranchRecursive($useCache);
		}
	}

	/**
	 * Получить оперативный кеш моделей текущего класса
	 * see self::$_cacheActiveRecords
	 * return array УКАЗАТЕЛЬ на кеш
	 */
	private function &getCacheStoreActiveRecords() {
		$ownerClassName = $this->treempGetOwnerClassName();

		if (!isset(self::$_cacheActiveRecords[$ownerClassName])) {
			self::$_cacheActiveRecords[$ownerClassName] = array();
		}

		return self::$_cacheActiveRecords[$ownerClassName];
	}

	/**
	 * Полкучить оперативный кеш связей текщего класса
	 * see self::$_cacheIndexChildren
	 * return array УКАЗАТЕЛЬ на кеш
	 */
	private function &getCacheStoreIndexChildren() {
		$ownerClassName = $this->treempGetOwnerClassName();

		if (!isset(self::$_cacheIndexChildren[$ownerClassName])) {
			self::$_cacheIndexChildren[$ownerClassName] = array();
		}

		return self::$_cacheIndexChildren[$ownerClassName];
	}

	/**
	 * Построить поле path для текущей записи. Для этого требуется знать
	 * родительский path и текущий PK
	 * @param boolean $useCache true для использования кеша (default=true)
	 * @return boolean true в случае если удалось сохранить модель
	 */
	private function buildPath($useCache = true) {
		$parentField = $this->parentField;
		$pathField = $this->pathField;

		$parent = $this->treempGetParentExists() ? $this->treempGetParent($useCache) : null;

		if (empty($parent)) { // это корневая нода
			$this->owner->$pathField = $this->owner->getPrimaryKey() . ':';
		} else {
			$this->owner->$pathField = $parent->$pathField . $this->owner->getPrimaryKey() . ':';
		}

		// вместе с path обновлять кеш оригинальных значений
		$this->originalParentId = $this->owner->$parentField;
		$this->originalPk = $this->owner->getPrimaryKey();

		if ($this->owner->isNewRecord) {
			/*
			 * Если добавляется новая запись, модет происходить двойная вставка
			 * это корректная работа CActiveRecord, но проблема для нас.
			 * Исправим её! Это можно обработать через временную смену сценария модели,
			 * однако оно мне показалось лишним. Кроме того, это может иметь
			 * побочные эффекты в других поведениях.
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
	 * Проверить на петлю или цикл
	 * @return type
	 */
	private function checkForLoop() {
		$parentField = $this->parentField;

		return $this->owner->$parentField != $this->originalParentId && $this->treempIsAncestor($this->owner->$parentField, false);
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
		if ($this->checkForLoop()) {
			$this->owner->addError($this->parentField, Yii::t('RealActiveRecordTreeBehavior', 'detect loop for new parent_id({parent_id})', array('{parent_id}' => $this->owner->$parentField)));
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
