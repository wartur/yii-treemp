<?php

/**
 * TreempAttachWidget class file.
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
 * <? $this->widget('treemp.widgets.TreempAttachWidget', array(
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
class TreempAttachWidget extends CInputWidget {

	/**
	 * @var string model, which should be used to generate the tree
	 */
	public $treempModel = null;

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
		$treempModelName = $this->treempModel;
		$treempModel = $treempModelName::model();

		// need walk around all tree, cache it
		if ($this->useCacheInternal) {
			$treempModel->treempLoadAllTreeCache();
		}

		return $this->recursiveGenerateOpgrouptedSelect($treempModel->treempGetRootline(), 0);
	}

	/**
	 * Recursive generate tree data
	 * @param CActiveRecord[] $items part of tree nodes
	 * @param int $level tree level
	 * @return array part of data
	 */
	protected function recursiveGenerateOpgrouptedSelect($items, $level) {
		$result = array();
		$bspResult = str_repeat('&nbsp;', $level * $this->spaceMultiplier);

		foreach ($items as $entry) {
			$result[$entry->getPrimaryKey()] = $bspResult . $entry->treempGetNodeName();

			if ($entry->treempGetChildExists($this->useCacheInternal)) {
				$result += $this->recursiveGenerateOpgrouptedSelect($entry->treempGetChildren(), $level + 1);
			}
		}

		return $result;
	}

}
