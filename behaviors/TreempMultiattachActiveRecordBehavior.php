<?php

/**
 * TreempMultiattachActiveRecordBehavior class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-treemp
 * @license		New BSD license
 */

/**
 * Behavior, allows you to attach to the current model using a variety of other intermediate table.
 * 
 * To generate a tree using checkboxes take widget treemp.widgets.TreempMultiattachWidget
 * 
 * Using in CActiveRecord
 * <pre>
 * // add public variable
 * public $newAttachIds = array();
 * 
 * // add behavior indicating a variable that has been added above
 * public function behaviors() {
 * 	return array_merge(parent::behaviors(), array(
 * 		'TreempMultiattachActiveRecordBehavior' => array(
 * 			'class' => 'treemp.behaviors.TreempMultiattachActiveRecordBehavior',
 * 			'multiAttachModelName' => 'Attachtest',
 * 			'multiAttachModelTargetIdField' => 'many_attach_model_id',
 * 			'multiAttachModelTreempIdField' => 'treetest_id',
 * 			'attachField' => 'newAttachIds'
 * 		)
 * 	));
 * }
 * 
 * // add to validators
 * public function rules() {
 * 	return array(
 * 		// ...
 * 		array('newAttachIds', 'type', 'type' => 'array'),
 * 		// ...
 * 	);
 * }
 * </pre>
 * 
 * Описание настройки:
 * multiAttachModelName - indicates a class used to store many-to-many communication
 * multiAttachModelTargetIdField - variables in the model {multiAttachModelName} pointing to the target model to which will be attached a list of other models
 * multiAttachModelTreempIdField - variables in the model {multiAttachModelName} indicating the model you want to attach to the target model
 */
class TreempMultiattachActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * @var string name of the model for communication connection many-to-many
	 * Example models and databases, see the directory treemp.example
	 */
	public $multiAttachModelName = null;

	/**
	 * @var string name field model for a lot of communication due to many.
	 * Specifies the field of the target model
	 * Example models and databases, see the directory treemp.example
	 */
	public $multiAttachModelTargetIdField = null;

	/**
	 * @var string name field model for a lot of communication due to many.
	 * Indicates a field tree model
	 * Example models and databases, see the directory treemp.example
	 */
	public $multiAttachModelTreempIdField = null;

	/**
	 * @var string field model for conservation
	 */
	public $attachField = 'newAttachIds';
	
	/**
	 * @var array a list of identifiers are at the beginning of the work in the database
	 */
	private $storeRecordSet = array();

	/**
	 * Getting the name of the primary key
	 * @return string|array line primary key field or an array of composite key
	 */
	private function primaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	private function findModelSet($recordSet) {
		$multiAttachModelName = $this->multiAttachModelName;

		// if so, we will not ship the database
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
		
		// get the source list. Written in a public member variable in the model and the behavior of private variable
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

		// add new checked
		$newDiff = array_diff($newRecordSet, $this->storeRecordSet);
		foreach ($newDiff as $entry) {
			$newModel = new $multiAttachModelName();
			$newModel->{$this->multiAttachModelTargetIdField} = $this->owner->getPrimaryKey();
			$newModel->{$this->multiAttachModelTreempIdField} = $entry;
			$newModel->save();
		}

		// delete unchecked
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
		
		$this->owner->{$this->attachField} = array();

		// Only in debug mode. On production is a waste of electricity
		if (YII_DEBUG) {
			if (!is_string($this->primaryKeyName())) {
				throw new CException(Yii::t('TreempMultiattachActiveRecordBehavior', 'The library does not know how to work a composite primary key'));
			}
		}
	}

}
