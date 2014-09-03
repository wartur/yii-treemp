yii-tree-mp-behavior
====================

This extension allows managing trees stored in database as materialized path.
It's implemented as Active Record behavior for Yii 1.

Now it's simple but fast and powerfull implementation materialized path...

Installing and configuring
--------------------------

- download last release https://github.com/wartur/yii-tree-mp-behavior/releases
- unpack to `ext.wartur.yii-tree-mp-behavior`
- implement schema https://github.com/wartur/yii-tree-mp-behavior/blob/master/schema/treetest.sql
- update config
```php
// ...
'import'=>array(
	//...
	'ext.wartur.yii-tree-mp-behavior.*',
	//...
),
// ...
```
- update model (I'm name it Treetest)
```php
public function behaviors() {
	return array_merge(parent::behaviors(), array(
		'realActiveRecordTreeBehavior' => array(
			'class' => 'RealActiveRecordTreeBehavior',
		)
	));
}
```
- remove validate from `path` field. It field calculate by behavior.





Use RealActiveRecordTreeBehavior.php
------------------------------------

This behavior work transparently. It provide optimise operation when you just work with it.

### Craete some root node
```php
$modelRoot = new Treetest();
$modelRoot->name = 'root';
$modelRoot->save();

```

### Craete some 2 subnode
```php
$modelSub1 = new Treetest();
$modelSub1->name = 'subnode2';
$modelSub1->parent_id = $modelRoot->id;
$modelSub1->save();

$modelSub2 = new Treetest();
$modelSub2->name = 'subnode1';
$modelSub1->parent_id = $modelRoot->id;
$modelSub2->save();

```

### Get children
```php
$children = $modelRoot->getChildren();
/*
 * result ...
 * $children -> subnode1, subnode2
 * you see what query with sort 'name ASC'... you can configure this in behavior
 * 
 * this was optimize query by materialized path...
 *
 */
 
```

### Work with all branch...
```php
$modelRoot->loadAllBranchCache(); // will query by materialized path and cached records
$children = $modelRoot->getChildren(); // no query now...
```

I hope it was helpful... other functionality you find in api docs of source code.
I has prepared simple widget RealTreeSelectWidget for fast start and edit this structure.






Use RealTreeSelectWidget
------------------------

As you see work with behavior is wery simple, just save parent_id what you need. This widget help with this operation.
This widget implement tree view emulation in html <select> tag using &nbsp; for indent

### Using in view
```php
<? $this->widget('RealTreeSelectWidget', array(
	'model' => $model,
	'attribute' => 'parent_id',
	'deleteHimself' => true,
	'spaceMultiplier' => 2,
	'emptyText' => 'My Custom Empty Text...'
)) ?>
```

For other doc read possible config in source.




