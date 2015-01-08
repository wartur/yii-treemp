YII-TREEMP ([Русская версия](https://github.com/wartur/yii-treemp/blob/master/README.ru.md))
============================================================================================

Extension for Yii for working with tree structure using an algorithm materialized path.
When using this extension do not forget to use table locks or transactions.

DEMO: http://yii-treemp.wartur.ru

## Release 2.0.1 for Yii second and last. Further development [to be carried out on Yii2](https://github.com/wartur/yii2-treemp)

###### From the author
> This extension was created for fun and to meet their vile,
> vulgar and useless in practice, perfectionism.

(Sorry for my english. I'm using google translate)

Abstract
--------
Extension for working with tree structure in a relational database.
Algorithm materialized path.
The algorithm speeds up the tree in such operations as sampling branches, tree building or part of the tree.
Due to the algorithm materialized path accelerated insertion rate in an arbitrary position in the table.
For additional sorting branches at each level of the need to use an additional algorithm.
As an example, take https://github.com/wartur/yii-sorter

IMPORTANT: all operations using this extension shall be made through
the transaction (ISOLATION LEVEL SERIALIZABLE) / lock.
Otherwise, there is a possibility of destruction of the database.
All actions of this expansion include transactions in cases when
the table does not support transactions in the component
you want to specify that you want to use a table lock.

Connecting to the expansion project
-----------------------------------
1) [Download the latest release](https://github.com/wartur/yii-treemp/releases)

2) Unpack yii-treemp in the directory ext.wartur.yii-treemp

3) Add a new alias path to the top of the configuration file (default: config / main.php)
```php
Yii::setPathOfAlias('treemp', 'protected/extensions/wartur/yii-treemp');
```

4) Add behavior to a model in which support is required to work with tree structure.
Minimum configuration:
```php
public function behaviors() {
	return array_merge(parent::behaviors(), array(
		'TreempActiveRecordBehavior' => array(
			'class' => 'treemp.behaviors.TreempActiveRecordBehavior',
		)
	));
}
```

5) Check that your model satisfies the schema given in the
[treemp.tests.env.schema](https://github.com/wartur/yii-treemp/blob/master/tests/env/schema/treetest.sql).
Remember to work the behavior required field VARCHAR (255) path with a unique key. More you can read in
[API reference поведения](https://github.com/wartur/yii-treemp/blob/master/behaviors/TreempActiveRecordBehavior.php).
MySQL does not support indexing the text field more then 255 characters.

6) If you already have a tree structure based on the parent_id.
Make restructuring materialized path by calling the API treempRebuildAllPath
```php
$treeModel = Treetest::model();
$treeModel->treempRebuildAllPath();
```

Software API
------------
```php
// attach some node to the current node
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$currentNode->treempAppendChild($newNode);

// attach current node to other node (the result will be equal)
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$newNode->treempAppendTo($currentNode);

// materialized path reconstructed automatically if you change the parent_id (the result will be equal)
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$newNode->parent_id = $currentNode->id;
$newNode->save();

// obtain an arbitrary node on the identifier.
$node = Treetest::model()
$rootLine = $node->treempGetNodeByPk(100500);	// This node will be cached
$rootLine2 = $node->treempGetNodeByPk(100500);
// $rootLine == $rootLine2

// get a list of the root node
$node = Treetest::model()
$rootLine = $node->treempGetRootline();

// get the children of the current node
$currentNode = Treetest::model()->findByPk(666);
$childrenNodes = $currentNode->treempGetChildren();

// get the parent of the current node
$currentNode = Treetest::model()->findByPk(666);
$parentNode = $currentNode->treempGetParent();

// load all tree in an operational cache
$currentNode = Treetest::model();
$currentNode->treempLoadAllTreeCache();
$node100500 = $currentNode->treempGetNodeByPk(100500);	// cache hit
$node100501 = $currentNode->treempGetNodeByPk(100501);	// cache hit
$node666 = $currentNode->treempGetNodeByPk(666);	// cache hit

// load a tree branch in an operational cache
// In the database there are threads 1:10:100:100500, 1:10:100:100501, 666
$currentNode = Treetest::model()->findByPk(1);
$currentNode->treempLoadBranchCache();
$node100500 = $currentNode->treempGetNodeByPk(100500);	// cache hit
$node100501 = $currentNode->treempGetNodeByPk(100501);	// cache hit
$node666 = $currentNode->treempGetNodeByPk(666);	// cache miss

// clear the cache-line
$currentNode->treempCleanoutCache();

// to get the path from the root to the current node. This functionality can be used in the breadcrumb
$someNode = Treetest::model()->findByPk(100500);
$pathModels = $someNode->treempGetPathModels();		// path => 1:10:100:100500
$resultArray = array();
foreach ($pathModels as $entry) {
	$resultArray[] = $entry->name;
}
echo implode(' :: ', $resultArray);
// name1 :: name10 :: name100 :: name100500

// You can make it in a loop, all access to the database are cached.
// TargetModel - this model is bound to several branches of the tree across the table many-to-many
$targetModel = TargetModel::model()->findByPk(666);
$result = array();
foreach ($targetModel->treetests as $entry) {
	$pathModels = $entry->treempGetPathModels();
	$resultArray = array();
	
	// because caching provides a sufficiently high speed such design
	foreach ($pathModels as $pathEntry) {
		$resultArray[] = $pathEntry->name;
	}
	
	$result[] = implode(' :: ', $resultArray);
}
return implode("\n ", $result);
// name1 :: name10 :: name100 :: name100500
// name1 :: name10 :: name100 :: name100501
// In the second row name1, name10, name100 was take from cache

// get the root of the current branch
$currentNode = Treetest::model()->findByPk(100500);
$rootNode = $currentNode->treempGetRootParent();

```

Working with Widget Ready
-------------------------
To expand the available basic set of widgets.
Using the current API, you can write your widget will do the rest for you behavior.

1) Widget for drawing up a tree

To bind together the vertices can use the drop-down list widget generation with stylized tree.
To do this, add the following code mapping
```php
<? $this->widget('treemp.widgets.TreempDropdownWidget', array(
	'model' => $model,
	'attribute' => 'parent_id',
)) ?>
```
In the widget, there are additional settings.
You can remove the branch from the selection list is bound to the current vertex.
This would eliminate the opportunity to loop or cycle.
Default branch is bound to editable tree top hidden if for some reason you want to show it,
use setting $deleteHimself = false.
Note in this case, may occur during the preservation cycle error location in the tree.
To adjust the spacing of the variable $spaceMultiplier.
```php
<? $this->widget('treemp.widgets.TreempDropdownWidget', array(
	'model' => $model,
	'attribute' => 'parent_id',
	'deleteHimself' => false, // Show all branches, even those that can potentially cause an error
	'spaceMultiplier' => 1,   // decrease Indent
	'emptyText' => '(My custom empty text)' // change the default text
)) ?>
```

2) Widget to bind the tree node with a specific model
```php
<? $this->widget('treemp.widgets.TreempAttachWidget', array(
	'model' => $model,
	'attribute' => 'treetest_id',
	'treempModel' => 'Treetest',
)) ?>
```
This widget is equal TreempDropdownWidget except that you must specify
the name of the tree model in the variable $treempModel.
This widget is missing $deleteHimself, since the addition is carried out to the external model,
and not between the tree branches.

3) Widget and behavior for the attach of several branches of the tree to a specific model via an intermediate table

In the model to which you want to make attachment you want to add
```php
// add public variable
public $newAttachIds = array();

// add behavior indicating a variable that has been added above
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

// add to validators
public function rules() {
	return array(
		// ...
		array('newAttachIds', 'type', 'type' => 'array'),
		// ...
	);
}
```
- multiAttachModelName - indicates the model class that stores a many-to-many
- multiAttachModelTargetIdField - variable in the model {multiAttachModelName}, pointing to the target model, which will be attached a list of other models
- multiAttachModelTreempIdField - variable in the model {multiAttachModelName}, indicating the model that you want to attach to the target model. In our case, the tree

In the model, the variable $newAttachIds behavior will be loaded with the current list of linked models,
after saving the model behavior will remain binding that have been changed

In the view must be added the following widget that creates a list of checkboxes with more stylized tree.
```php
<? $this->widget('treemp.widgets.TreempMultiattachWidget', array(
	'model' => $model,
	'attribute' => 'newAttachIds',
	'treempModel' => 'Treetest',
)) ?>
```
Widget TreempMultiattachWidget has additional customization capabilities. Read the [API reference](https://github.com/wartur/yii-treemp/blob/master/widgets/TreempMultiattachWidget.php).

Good work!
