<?php

/**
 * TreempDropdownWidget class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-treemp
 * @license		New BSD license
 */

/**
 * Widget generate select with tree level space indention
 * It using RealActiveRecordTreeBehavior for data source
 * 
 * <pre>
 * <? $this->widget('treemp.widgets.TreempDropdownWidget', array(
 *     'model' => $model,
 *     'attribute' => 'parent_id',
 *     'deleteHimself' => true,
 *     'spaceMultiplier' => 2,
 *     'emptyText' => '(Select parent)'
 * )) ?>
 * </pre>
 * 
 * Widget extend CInputWidget
 */
class TreempDropdownWidget extends CInputWidget {

	/**
	 * @var boolean if true, then this node and all children will remove from select input
	 */
	public $deleteHimself = true;

	/**
	 * @var int if larger, then more space on each tree level
	 */
	public $spaceMultiplier = 2;

	/**
	 * @var mixed text layout if option not select. Default is "(Select parent)".
	 * You can change text this or use Yii:t
	 */
	public $emptyText = null;

	/**
	 * @var boolean using ram cache mechanism in RealActiveRecordTreeBehavior
	 */
	public $useCacheInternal = true;

	public function init() {
		parent::init();

		/*
		 * layout understandably error, if model not compatible
		 * wait next Yii release... need method hasBehavior. This workaround not work.
		 * 
		  if(!method_exists($this->model, 'getRootline')) {
		  throw new CException(Yii::t('RealTreeSelectWidget','{class} must exists recursiveGenerateSelect method, defined in RealActiveRecordTreeBehavior',array('{class}'=>get_class($this->model))));
		  }
		 */

		$this->emptyText = $this->emptyText === null ? Yii::t('RealTreeSelectWidget', '(Select parent)') : $this->emptyText;
	}

	public function run() {
		list($name, $id) = $this->resolveNameID();
		
		echo CHtml::activeDropDownList($this->model, $this->attribute, $this->recursiveGenerateSelect(), array_merge(array(
			'encode' => false,
			'empty' => $this->emptyText,
			'name' => $name,
			'id' => $id), $this->htmlOptions));
	}

	/**
	 * Generate tree data input point
	 * @return array key => name pair
	 */
	public function recursiveGenerateSelect() {
		$exeptid = $this->deleteHimself ? $this->model->getPrimaryKey() : 0;

		// need walk around all tree, cache it
		if($this->useCacheInternal) {
			$this->model->loadAllTreeCache();
		}

		return $this->recursiveGenerateOpgrouptedSelect($this->model->getRootline(), $exeptid, 0);
	}

	/**
	 * Recursive generate tree data
	 * @param CActiveRecord[] $items part of tree nodes
	 * @param int $exeptid node pk which will remove from options with subnodes
	 * @param int $level tree level
	 * @return array part of data
	 */
	protected function recursiveGenerateOpgrouptedSelect($items, $exeptid, $level) {
		$result = array();
		$bspResult = str_repeat('&nbsp;', $level * $this->spaceMultiplier);

		foreach ($items as $entry) {
			if ($exeptid == $entry->getPrimaryKey()) { // let exeptid and all brunch
				continue;
			}

			$result[$entry->getPrimaryKey()] = $bspResult . $entry->getNodeName();

			if ($entry->getChildExists($this->useCacheInternal)) {
				$result += $this->recursiveGenerateOpgrouptedSelect($entry->getChildren(), $exeptid, $level + 1);
			}
		}

		return $result;
	}

}
