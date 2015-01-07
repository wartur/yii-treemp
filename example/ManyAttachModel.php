<?php

/**
 * This is the model class for table "many_attach_model".
 *
 * The followings are the available columns in table 'many_attach_model':
 * @property integer $id
 * @property string $name
 *
 * The followings are the available model relations:
 * @property Attachtest[] $attachtests
 */
class ManyAttachModel extends CActiveRecord {

	public $newAttachIds = array();

	public function getAttachList() {
		$result = array();
		foreach ($this->treetests as $entry) {
			$result[] = $entry->name;
		}
		return $result;
	}

	public function getAttachPathList() {
		$result = array();
		foreach ($this->treetests as $entry) {
			$pathModels = $entry->treempGetPathModels();
			$resultArray = array();

			// because caching provides a sufficiently high speed such design
			foreach ($pathModels as $pathEntry) {
				$resultArray[] = $pathEntry->name;
			}

			$result[] = implode(' :: ', $resultArray);
		}
		return $result;
	}

	public function behaviors() {
		return array_merge(parent::behaviors(), array(
			'TreempMultiattachActiveRecordBehavior' => array(
				'class' => 'treemp.behaviors.TreempMultiattachActiveRecordBehavior',
				'multiAttachModelName' => 'Attachtest',
				'multiAttachModelTargetIdField' => 'many_attach_model_id',
				'multiAttachModelTreempIdField' => 'treetest_id',
				'attachField' => 'newAttachIds'
			)
		));
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName() {
		return 'many_attach_model';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules() {
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('name', 'required'),
			array('newAttachIds', 'type', 'type' => 'array'),
			array('name', 'length', 'max' => 255),
			// The following rule is used by search().
			// @todo Please remove those attributes that should not be searched.
			array('id, name', 'safe', 'on' => 'search'),
		);
	}

	/**
	 * @return array relational rules.
	 */
	public function relations() {
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'attachtests' => array(self::HAS_MANY, 'Attachtest', 'many_attach_model_id'),
			'treetests' => array(self::MANY_MANY, 'Treetest', 'attachtest(many_attach_model_id, treetest_id)'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels() {
		return array(
			'id' => 'ID',
			'name' => 'Name',
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 *
	 * Typical usecase:
	 * - Initialize the model fields with values from filter form.
	 * - Execute this method to get CActiveDataProvider instance which will filter
	 * models according to data in model fields.
	 * - Pass data provider to CGridView, CListView or any similar widget.
	 *
	 * @return CActiveDataProvider the data provider that can return the models
	 * based on the search/filter conditions.
	 */
	public function search() {
		// @todo Please modify the following code to remove attributes that should not be searched.

		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id);
		$criteria->compare('name', $this->name, true);

		return new CActiveDataProvider($this, array(
			'criteria' => $criteria,
		));
	}

	/**
	 * Returns the static model of the specified AR class.
	 * Please note that you should have this exact method in all your CActiveRecord descendants!
	 * @param string $className active record class name.
	 * @return ManyAttachModel the static model class
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

}
