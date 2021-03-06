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
 * It using TreempActiveRecordBehavior for data source
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
	 * @var boolean using ram cache mechanism in TreempActiveRecordBehavior
	 */
	public $useCacheInternal = true;

	public function init() {
		parent::init();
		
		$this->emptyText = $this->emptyText === null ? Yii::t('TreempDropdownWidget', '(Select parent)') : $this->emptyText;
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
			$this->model->treempLoadAllTreeCache();
		}

		return $this->recursiveGenerateOpgrouptedSelect($this->model->treempGetRootline(), $exeptid, 0);
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

			$result[$entry->getPrimaryKey()] = $bspResult . $entry->treempGetNodeName();

			if ($entry->treempGetChildExists($this->useCacheInternal)) {
				$result += $this->recursiveGenerateOpgrouptedSelect($entry->treempGetChildren(), $exeptid, $level + 1);
			}
		}

		return $result;
	}

}
