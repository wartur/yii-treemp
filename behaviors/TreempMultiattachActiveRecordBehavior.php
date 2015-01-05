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
	 * Сохранения связей будет осуществляться сразу после сохранения основной записи
	 * @param type $event
	 */
	public function afterSave($event) {
		parent::afterSave($event);

		$multiAttachModelName = $this->multiAttachModelName;
		$newRecordSet = $this->owner->{$this->attachField};

		// получить исходный список
		$storeRecordSet = Yii::app()->db->createCommand()
				->select($this->multiAttachModelTreempIdField)
				->from($multiAttachModelName::model()->tableName())
				->where($this->multiAttachModelTargetIdField . ' = :pk', array('pk' => $this->owner->getPrimaryKey()))
				->queryColumn();

		$newDiff = array_diff($newRecordSet, $storeRecordSet);
		$newModels = $this->findModelSet($newDiff); // дополнительная проверка для исключения дубликатов
		foreach ($newDiff as $entry) {
			if (empty($newModels[$entry])) {
				$newModel = new $multiAttachModelName();
				$newModel->{$this->multiAttachModelTargetIdField} = $this->owner->getPrimaryKey();
				$newModel->{$this->multiAttachModelTreempIdField} = $entry;
				$newModel->save();
			}
		}

		$deleteDiff = array_diff($storeRecordSet, $newRecordSet);
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
