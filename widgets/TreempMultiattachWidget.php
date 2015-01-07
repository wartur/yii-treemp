<?php

/**
 * TreempMultiattachWidget class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-treemp
 * @license		New BSD license
 */

/**
 * Widget generate select with tree level space indention
 * It using TreempActiveRecordBehavior for data source and
 * TreempMultiattachActiveRecordBehavior for attach functionality
 * 
 * If you want to make Single attach, you can use the widget
 * TreempAttachWidget
 * 
 * Ising in view
 * <pre>
 * <? $this->widget('treemp.widgets.TreempMultiattachWidget', array(
 *     'model' => $model,
 *     'attribute' => 'newAttachIds',
 *     'treempModel' => 'Treetest',
 * )) ?>
 * </pre>
 * 
 * Widget extend CInputWidget
 */
class TreempMultiattachWidget extends CInputWidget {

	/**
	 * @var string model, which should be used to generate the tree
	 */
	public $treempModel = null;

	/**
	 * @var int the number of pixels to indent each level of the tree.
	 * Is a simple design styles.
	 * You can disable this functionality by specifying 0 as the value.
	 * After that, you can use nested div management control styles.
	 * Nested div have a class that is controlled by $ divTabClass
	 */
	public $tabPadding = 15;

	/**
	 * @var string the class name of the nested div
	 */
	public $divTabClass = 'tablevel';

	/**
	 * @var string div class name decorates every checkbox with the label
	 */
	public $checkDivClass = 'checkdiv';

	/**
	 * @var boolean the allocation of root branches, disable and uncheck descendants, it will provide a more optimal preservation in the database.
	 * If false, controlling JS code is disabled
	 */
	public $jsAutodisable = true;

	/**
	 * @var boolean using ram cache mechanism in TreempActiveRecordBehavior
	 */
	public $useCacheInternal = true;

	public function init() {
		parent::init();
	}

	public function run() {
		list($name, $id) = $this->resolveNameID();

		$treempModelName = $this->treempModel;
		$treempModel = $treempModelName::model();

		// need walk around all tree, cache it
		if ($this->useCacheInternal) {
			$treempModel->treempLoadAllTreeCache();
		}

		$data = $treempModel->treempGetRootline();

		// in our algorithm is not supported separator. Instead, the division of a block-level div
		$this->htmlOptions['separator'] = '';

		// change the functionality of the default checkbox Yii
		$this->htmlOptions['container'] = isset($this->htmlOptions['container']) ? $this->htmlOptions['container'] : 'div';

		if ($this->jsAutodisable) {
			$baseId = CHtml::getIdByName($name);

			$js = <<<EOD
jQuery('#$baseId input').change(function(e){
	var self = jQuery(this),
		inputSublevels = self.parent().next('.{$this->divTabClass}').find('input');
	
	if(self.attr('disabled')) {
		return false;
	}
	
	if(self.prop('checked')) {
		inputSublevels.removeAttr('checked');
		inputSublevels.attr('disabled','disabled');
	} else {
		inputSublevels.removeAttr('disabled');
	}
});

jQuery('#$baseId input').change();
EOD;

			$cs = Yii::app()->getClientScript();
			$cs->registerCoreScript('jquery');
			$cs->registerScript($id, $js);
		}

		echo $this->activeCheckBoxList($this->model, $this->attribute, $data, $this->htmlOptions);
	}

	/**
	 * Code is from Yii 1.1.16 with small adaptations and additions.
	 * The following is a documentation Yii...
	 * 
	 * Generates a check box list for a model attribute.
	 * The model attribute value is used as the selection.
	 * If the attribute has input error, the input field's CSS class will
	 * be appended with {@link errorCss}.
	 * Note that a check box list allows multiple selection, like {@link listBox}.
	 * As a result, the corresponding POST value is an array. In case no selection
	 * is made, the corresponding POST value is an empty string.
	 * @param CModel $model the data model
	 * @param string $attribute the attribute
	 * @param array $data value-label pairs used to generate the check box list.
	 * Note, the values will be automatically HTML-encoded, while the labels will not.
	 * @param array $htmlOptions additional HTML options. The options will be applied to
	 * each checkbox input. The following special options are recognized:
	 * <ul>
	 * <li>template: string, specifies how each checkbox is rendered. Defaults
	 * to "{input} {label}", where "{input}" will be replaced by the generated
	 * check box input tag while "{label}" will be replaced by the corresponding check box label.</li>
	 * <li>separator: string, specifies the string that separates the generated check boxes.</li>
	 * <li>checkAll: string, specifies the label for the "check all" checkbox.
	 * If this option is specified, a 'check all' checkbox will be displayed. Clicking on
	 * this checkbox will cause all checkboxes checked or unchecked.</li>
	 * <li>checkAllLast: boolean, specifies whether the 'check all' checkbox should be
	 * displayed at the end of the checkbox list. If this option is not set (default)
	 * or is false, the 'check all' checkbox will be displayed at the beginning of
	 * the checkbox list.</li>
	 * <li>encode: boolean, specifies whether to encode HTML-encode tag attributes and values. Defaults to true.</li>
	 * <li>labelOptions: array, specifies the additional HTML attributes to be rendered
	 * for every label tag in the list.</li>
	 * <li>container: string, specifies the checkboxes enclosing tag. Defaults to 'span'.
	 * If the value is an empty string, no enclosing tag will be generated</li>
	 * <li>baseID: string, specifies the base ID prefix to be used for checkboxes in the list.
	 * This option is available since version 1.1.13.</li>
	 * </ul>
	 * Since 1.1.7, a special option named 'uncheckValue' is available. It can be used to set the value
	 * that will be returned when the checkbox is not checked. By default, this value is ''.
	 * Internally, a hidden field is rendered so when the checkbox is not checked, we can still
	 * obtain the value. If 'uncheckValue' is set to NULL, there will be no hidden field rendered.
	 * @return string the generated check box list
	 * @see checkBoxList
	 */
	private function activeCheckBoxList($model, $attribute, $data, $htmlOptions = array()) {
		CHtml::resolveNameID($model, $attribute, $htmlOptions);
		$selection = CHtml::resolveValue($model, $attribute);
		if ($model->hasErrors($attribute))
			CHtml::addErrorCss($htmlOptions);
		$name = $htmlOptions['name'];
		unset($htmlOptions['name']);

		if (array_key_exists('uncheckValue', $htmlOptions)) {
			$uncheck = $htmlOptions['uncheckValue'];
			unset($htmlOptions['uncheckValue']);
		} else
			$uncheck = '';

		$hiddenOptions = isset($htmlOptions['id']) ? array('id' => CHtml::ID_PREFIX . $htmlOptions['id']) : array('id' => false);
		$hidden = $uncheck !== null ? CHtml::hiddenField($name, $uncheck, $hiddenOptions) : '';

		return $hidden . $this->checkBoxList($name, $selection, $data, $htmlOptions);
	}

	/**
	 * Code is from Yii 1.1.16 with small adaptations and additions.
	 * The following is a documentation Yii...
	 * 
	 * Generates a check box list.
	 * A check box list allows multiple selection, like {@link listBox}.
	 * As a result, the corresponding POST value is an array.
	 * @param string $name name of the check box list. You can use this name to retrieve
	 * the selected value(s) once the form is submitted.
	 * @param mixed $select selection of the check boxes. This can be either a string
	 * for single selection or an array for multiple selections.
	 * @param array $data value-label pairs used to generate the check box list.
	 * Note, the values will be automatically HTML-encoded, while the labels will not.
	 * @param array $htmlOptions additional HTML options. The options will be applied to
	 * each checkbox input. The following special options are recognized:
	 * <ul>
	 * <li>template: string, specifies how each checkbox is rendered. Defaults
	 * to "{input} {label}", where "{input}" will be replaced by the generated
	 * check box input tag while "{label}" be replaced by the corresponding check box label,
	 * {beginLabel} will be replaced by &lt;label&gt; with labelOptions, {labelTitle} will be replaced
	 * by the corresponding check box label title and {endLabel} will be replaced by &lt;/label&gt;</li>
	 * <li>separator: string, specifies the string that separates the generated check boxes.</li>
	 * <li>checkAll: string, specifies the label for the "check all" checkbox.
	 * If this option is specified, a 'check all' checkbox will be displayed. Clicking on
	 * this checkbox will cause all checkboxes checked or unchecked.</li>
	 * <li>checkAllLast: boolean, specifies whether the 'check all' checkbox should be
	 * displayed at the end of the checkbox list. If this option is not set (default)
	 * or is false, the 'check all' checkbox will be displayed at the beginning of
	 * the checkbox list.</li>
	 * <li>labelOptions: array, specifies the additional HTML attributes to be rendered
	 * for every label tag in the list.</li>
	 * <li>container: string, specifies the checkboxes enclosing tag. Defaults to 'span'.
	 * If the value is an empty string, no enclosing tag will be generated</li>
	 * <li>baseID: string, specifies the base ID prefix to be used for checkboxes in the list.
	 * This option is available since version 1.1.13.</li>
	 * </ul>
	 * @return string the generated check box list
	 */
	private function checkBoxList($name, $select, $data, $htmlOptions = array()) {
		$template = isset($htmlOptions['template']) ? $htmlOptions['template'] : '{input} {label}';
		$separator = isset($htmlOptions['separator']) ? $htmlOptions['separator'] : CHtml::tag('br');
		$container = isset($htmlOptions['container']) ? $htmlOptions['container'] : 'span';
		unset($htmlOptions['template'], $htmlOptions['separator'], $htmlOptions['container']);

		if (substr($name, -2) !== '[]')
			$name.='[]';

		if (isset($htmlOptions['checkAll'])) {
			$checkAllLabel = $htmlOptions['checkAll'];
			$checkAllLast = isset($htmlOptions['checkAllLast']) && $htmlOptions['checkAllLast'];
		}
		unset($htmlOptions['checkAll'], $htmlOptions['checkAllLast']);

		$labelOptions = isset($htmlOptions['labelOptions']) ? $htmlOptions['labelOptions'] : array();
		unset($htmlOptions['labelOptions']);

		$items = array();
		$baseID = isset($htmlOptions['baseID']) ? $htmlOptions['baseID'] : CHtml::getIdByName($name);
		unset($htmlOptions['baseID']);
		$id = 0;
		$checkAll = true;

		/*
		 * Вместо цикла сделаем рекурсивную генерацию с блекджеком и шлюхами.
		 * В результате получится список переменных с контентом, идентичным исходному коду Yii
		 * К сожалению придется пробросить много локальных переменых
		 */
		list($items, $checkAll, $id) = $this->recursiveGenerateOpgrouptedCheckbox($data, 0, $select, $checkAll, $baseID, $id, $name, $htmlOptions, $labelOptions, $template);

		if (isset($checkAllLabel)) {
			$htmlOptions['value'] = 1;
			$htmlOptions['id'] = $id = $baseID . '_all';
			$option = CHtml::checkBox($id, $checkAll, $htmlOptions);
			$beginLabel = CHtml::openTag('label', $labelOptions);
			$label = CHtml::label($checkAllLabel, $id, $labelOptions);
			$endLabel = CHtml::closeTag('label');
			$item = strtr($template, array(
				'{input}' => $option,
				'{beginLabel}' => $beginLabel,
				'{label}' => $label,
				'{labelTitle}' => $checkAllLabel,
				'{endLabel}' => $endLabel,
			));
			if ($checkAllLast)
				$items[] = $item;
			else
				array_unshift($items, $item);
			$name = strtr($name, array('[' => '\\[', ']' => '\\]'));
			$js = <<<EOD
jQuery('#$id').click(function() {
	jQuery("input[name='$name']").prop('checked', this.checked);
});
jQuery("input[name='$name']").click(function() {
	jQuery('#$id').prop('checked', !jQuery("input[name='$name']:not(:checked)").length);
});
jQuery('#$id').prop('checked', !jQuery("input[name='$name']:not(:checked)").length);
EOD;
			$cs = Yii::app()->getClientScript();
			$cs->registerCoreScript('jquery');
			$cs->registerScript($id, $js);
		}

		if (empty($container))
			return implode($separator, $items);
		else
			return CHtml::tag($container, array('id' => $baseID), implode($separator, $items));
	}

	/**
	 * Recursive generate tree data.
	 * Documented only variables that are required to understand.
	 * Other variables required for the algorithm Yii
	 * @param CActiveRecord[] $data part of tree nodes
	 * @param int $level tree level
	 * @return array part of data
	 */
	private function recursiveGenerateOpgrouptedCheckbox($data, $level, $select, $checkAll, $baseID, $id, $name, $htmlOptions, $labelOptions, $template) {
		$items = array();

		// if there is a simple indentation, add it
		if ($this->tabPadding > 0) {
			$currentTabPadding = $level * $this->tabPadding;
			$htmlOptions['style'] = "margin-left: {$currentTabPadding}px";
		}

		// generate a level.
		$items[] = CHtml::openTag('div', array('class' => $this->divTabClass));
		foreach ($data as $entry) {
			/* @var $entry TreempActiveRecordBehavior */
			$value = $entry->getPrimaryKey();
			$labelTitle = $entry->treempGetNodeName();

			$checked = !is_array($select) && !strcmp($value, $select) || is_array($select) && in_array($value, $select);
			$checkAll = $checkAll && $checked;
			$htmlOptions['value'] = $value;
			$htmlOptions['id'] = $baseID . '_' . $id++;
			$option = CHtml::checkBox($name, $checked, $htmlOptions);
			$beginLabel = CHtml::openTag('label', $labelOptions);
			$label = CHtml::label($labelTitle, $htmlOptions['id'], $labelOptions);
			$endLabel = CHtml::closeTag('label');

			// decorates every checkbox div tags
			$items[] = CHtml::openTag('div', array('class' => $this->checkDivClass));
			$items[] = strtr($template, array(
				'{input}' => $option,
				'{beginLabel}' => $beginLabel,
				'{label}' => $label,
				'{labelTitle}' => $labelTitle,
				'{endLabel}' => $endLabel,
			));
			$items[] = CHtml::closeTag('div');

			// if there are descendants, then generate a sublavel
			if ($entry->treempGetChildExists($this->useCacheInternal)) {
				list($recursiveItems, $checkAll, $id) = $this->recursiveGenerateOpgrouptedCheckbox($entry->treempGetChildren(), $level + 1, $select, $checkAll, $baseID, $id, $name, $htmlOptions, $labelOptions, $template);
				$items = array_merge($items, $recursiveItems);
			}
		}
		$items[] = CHtml::closeTag('div');

		return array($items, $checkAll, $id);
	}

}
