<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of TreempAttachGroupActiveRecordBehavior
 *
 * @author user
 */
class TreempMultiattachActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * @var string имя модели для связи связи много ко многим
	 * Пример модели и базы данных смотрите в директории treemp.example
	 */
	public $multiAttachModelName = null;

	/**
	 * @var string имя поля модели для связи связи много ко многим.
	 * Указывает поле целевой модели
	 * Пример модели и базы данных смотрите в директории treemp.example
	 */
	public $multiAttachModelTargetIdField = null;

	/**
	 * @var string имя поля модели для связи связи много ко многим.
	 * Указывает на поле модели дерева
	 * Пример модели и базы данных смотрите в директории treemp.example
	 */
	public $multiAttachModelTreempIdField = null;

	/**
	 * @var string поле модели для сохранения
	 */
	public $attachField = 'newAttachIds';
	
	/**
	 * @var array список идентификаторов находящиеся на начало работы в бд
	 */
	private $storeRecordSet;

	/**
	 * Getting the name of the primary key
	 * @return string|array line primary key field or an array of composite key
	 */
	private function primaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	private function findModelSet($recordSet) {
		$multiAttachModelName = $this->multiAttachModelName;

		// если так, то не будем грузить базу данных
		if(empty($recordSet)) {
			return array();
		} else {
			$criteria = new CDbCriteria();
			$criteria->addInCondition($this->multiAttachModelTreempIdField, $recordSet);
			$criteria->addColumnCondition(array($this->multiAttachModelTargetIdField => $this->owner->getPrimaryKey()));
			$models = $multiAttachModelName::model()->findAll($criteria);

			$result = array();
			foreach ($models as $entry) {
				$result[$entry->{$this->multiAttachModelTreempIdField}] = $entry;
			}
			return $result;
		}
	}
	
	/**
	 * Responds to {@link CActiveRecord::onAfterFind} event.
	 * Override this method and make it public if you want to handle the corresponding event
	 * of the {@link CBehavior::owner owner}.
	 * @param CEvent $event event parameter
	 */
	public function afterFind($event) {
		parent::afterFind($event);
		
		$multiAttachModelName = $this->multiAttachModelName;
		
		// получить исходный список. Записать в публичную переменную модели и в приватную переменную поведения
		$this->owner->{$this->attachField} = $this->storeRecordSet = Yii::app()->db->createCommand()
				->select($this->multiAttachModelTreempIdField)
				->from($multiAttachModelName::model()->tableName())
				->where($this->multiAttachModelTargetIdField . ' = :pk', array('pk' => $this->owner->getPrimaryKey()))
				->queryColumn();
	}

	/**
	 * Responds to {@link CActiveRecord::onAfterSave} event.
	 * Override this method and make it public if you want to handle the corresponding event
	 * of the {@link CBehavior::owner owner}.
	 * @param CEvent $event event parameter
	 */
	public function afterSave($event) {
		parent::afterSave($event);

		$multiAttachModelName = $this->multiAttachModelName;
		$newRecordSet = $this->owner->{$this->attachField};

		$newDiff = array_diff($newRecordSet, $this->storeRecordSet);
		foreach ($newDiff as $entry) {
			$newModel = new $multiAttachModelName();
			$newModel->{$this->multiAttachModelTargetIdField} = $this->owner->getPrimaryKey();
			$newModel->{$this->multiAttachModelTreempIdField} = $entry;
			$newModel->save();
		}

		$deleteDiff = array_diff($this->storeRecordSet, $newRecordSet);
		$deleteModels = $this->findModelSet($deleteDiff);
		foreach ($deleteModels as $entry) {
			$entry->delete();
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
				throw new CException(Yii::t('TreempMultiattachActiveRecordBehavior', 'The library does not know how to work a composite primary key'));
			}
		}
	}

}
