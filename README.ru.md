YII-TREEMP ([English version](https://github.com/wartur/yii-treemp/blob/master/README.md))
==========================================================================================

Расширение для Yii для работы c деревом с помощью алгоритма материализованного пути.
При использовании данного расширения не забывайте пользоваться транзакциями или блокировками таблиц.

ДЕМО: http://yii-treemp.wartur.ru

## Релиз 2.0.0 для Yii второй и последний. Дальнейшая разработка [будет проводиться на Yii2](https://github.com/wartur/yii2-treemp)

###### От автора
> Данное расширение создавалось для фана и для удовлетворения своего
> низменного, пошлого и никому не нужного в практике перфекционизма.

Абстракт
--------
Расширение для работы с деревом в реляционной базе данных.
Алгоритм материализованного пути. Алгоритм ускоряет работу с деревом на таких
операциях как выборка ветки, построение дерева или части дерева.
Благодаря алгоритму материализованного пути ускоряется скорость вставки
в произвольное место таблицы. Для дополнительной сортировки веток
на каждом из уровне требуется использовать дополнительный алгоритм.
В качестве примера берите https://github.com/wartur/yii-sorter

ВАЖНО: все операции с использованием данного расширения ДОЛЖНЫ производиться через
транзакции(ISOLATION LEVEL SERIALIZABLE)/блокировки. В противном случае
есть вероятность разрушения базы данных.
Все действия данного расширения включают в себя транзакции, в случаях когда таблица
не поддерживает транзакции требуется пользоваться блокировкой таблицы

Подключение расширения к проекту
--------------------------------
1) [Скачайте новейший релиз](https://github.com/wartur/yii-treemp/releases)

2) Распакуйте yii-treemp в директории ext.wartur.yii-treemp

3) Добавьте новый алиас пути в начало конфигурационного файла (по умолчанию: config/main.php)
```php
Yii::setPathOfAlias('treemp', 'protected/extensions/wartur/yii-treemp');
```

4) Добавьте поведение в модель в которой требуется поддержка работы с деревьями.
Минимальная конфигурация:
```php
public function behaviors() {
	return array_merge(parent::behaviors(), array(
		'TreempActiveRecordBehavior' => array(
			'class' => 'treemp.behaviors.TreempActiveRecordBehavior',
		)
	));
}
```

5) Проверьте, что ваша модель удовлетворяет схеме, указанной в [treemp.tests.env.schema](https://github.com/wartur/yii-treemp/blob/master/tests/env/schema/treetest.sql).
Помните для работы поведения требуется поле VARCHAR(255) path с УНИКАЛЬНЫМ ключом. Подробнее
вы можете прочитать в [API reference поведения](https://github.com/wartur/yii-treemp/blob/master/behaviors/TreempActiveRecordBehavior.php). MySQL не поддерживает индексацию текстового поля более 255 символов.

6) Если вы уже имеете структуре дерева, основанную на parent_id. Прозведите перестройку материализованного пути, вызвав метод API treempRebuildAllPath
```php
$treeModel = Treetest::model();
$treeModel->treempRebuildAllPath();
```

Поведение работы с деревьями имеет простое программное API
----------------------------------------------------------
```php
// для привязки вершины к текущей вершине
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$currentNode->treempAppendChild($newNode);

// для привязки текущей вершины к другой вершине (результат будет тот же)
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$newNode->treempAppendTo($currentNode);

// материализованный путь перестроится автоматически, если вы смените parent_id
$currentNode = Treetest::model()->findByPk(1);
$newNode = new Treetest();
$newNode->name = 'newName1';
$newNode->parent_id = $currentNode->id;
$newNode->save();

// получить произвольную вершину по идентификатору. Эта вешина будет закэширована
$node = Treetest::model()
$rootLine = $node->treempGetNodeByPk(100500);
$rootLine2 = $node->treempGetNodeByPk(100500);
// $rootLine == $rootLine2

// получить список корневых вершин
$node = Treetest::model()
$rootLine = $node->treempGetRootline();

// получить список наследников
$currentNode = Treetest::model()->findByPk(666);
$childrenNodes = $currentNode->treempGetChildren();

// получить родителя текущего узла
$currentNode = Treetest::model()->findByPk(666);
$parentNode = $currentNode->treempGetParent();

// загрузить все дерево в оперативный кэш
$currentNode = Treetest::model();
$currentNode->treempLoadAllTreeCache();
$node100500 = $currentNode->treempGetNodeByPk(100500);	// попадание в кэш
$node100501 = $currentNode->treempGetNodeByPk(100501);	// попадание в кэш
$node666 = $currentNode->treempGetNodeByPk(666);	// попадание в кэш

// загрузить ветку дерева в оперативный кэш... В БД есть ветки 1:10:100:100500, 1:10:100:100501, 666
$currentNode = Treetest::model()->findByPk(1);
$currentNode->treempLoadBranchCache();
$node100500 = $currentNode->treempGetNodeByPk(100500);	// попадание в кэш
$node100501 = $currentNode->treempGetNodeByPk(100501);	// попадание в кэш
$node666 = $currentNode->treempGetNodeByPk(666);	// промах кэша

// отчистить оперативный кэш
$currentNode->treempCleanoutCache();

// что бы получить путь от корня до текущей вершины. Этот функционнал можно использовать в breadcrumb
$someNode = Treetest::model()->findByPk(100500);
$pathModels = $someNode->treempGetPathModels();		// path => 1:10:100:100500
$resultArray = array();
foreach ($pathModels as $entry) {
	$resultArray[] = $entry->name;
}
echo implode(' :: ', $resultArray);
// name1 :: name10 :: name100 :: name100500

// Вы можете это производить и в цикле, все обращения к БД кэшируются.
// TargetModel - это модель к которой привязаны несколько веток дерева через таблицу много-ко-многим
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
// Во второй строке вершины name1, name10, name100 были взяты из кэша

// получить корень текущей ветки
$currentNode = Treetest::model()->findByPk(100500);
$rootNode = $currentNode->treempGetRootParent();

```

Работа с готовыми виджетами
---------------------------
К расширению поставляется базовый набор виджетов.
Используя текущее API, вы можете написать свой виджет, остальное за вас сделает поведение.

1) Виджет для составления дерева

Для связывания вершин между собой можно воспользоваться виджетом генерации выпадающего списка со стилизацией под дерево. Для этого в отображении добавьте следующий код
```php
<? $this->widget('treemp.widgets.TreempDropdownWidget', array(
	'model' => $model,
	'attribute' => 'parent_id',
)) ?>
```
У виджета имеются дополнительные настройки. Вы можете убрать из списка выбора ветку к которой привязана текущая вершина. Это исключит возможность сделать петлю или цикл. По умолчанию ветка к которой привязана редактируемая вершина дерева скрывается, если по какой-то причине её требуется показать, то используйте настройку $deleteHimself = false. Учтите в этом случае при сохранении может возникать ошибка нахождения циклов в дереве. Для настройки отступа используйте переменную $spaceMultiplier.
```php
<? $this->widget('treemp.widgets.TreempDropdownWidget', array(
	'model' => $model,
	'attribute' => 'parent_id',
	'deleteHimself' => false, // покажем все ветки, даже те которые потенциально могут вызвать ошибку
	'spaceMultiplier' => 1,   // уменьшим отступ
	'emptyText' => '(My custom empty text)' // поменяем текст по умолчанию
)) ?>
```

2) Виджет для связывания вершины дерева с конкретной моделью
```php
<? $this->widget('treemp.widgets.TreempAttachWidget', array(
	'model' => $model,
	'attribute' => 'treetest_id',
	'treempModel' => 'Treetest',
)) ?>
```
Этот виджет ничем не отличается от TreempDropdownWidget за исключением того, что требуется указать имя модели дерева в переменной $treempModel. В данном виджете отсутствует $deleteHimself, так как присоединение ведется к внешней моделе, а не между ветками дерева.

3) Виджет и поведение для связывания нескольких веток дерева к конкретной моделью через промежуточную таблицу

В моделе к которой нужно делать присоединения требуется добавить
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
- multiAttachModelName - указывает на класс модели, в которой хранятся связи многие-ко-многим
- multiAttachModelTargetIdField - переменная в модели {multiAttachModelName}, указывая на целевую модель, в которой будет прикреплен список других моделей
- multiAttachModelTreempIdField - переменная в модели {multiAttachModelName}, указывающие модель, которую вы хотите прикрепить к целевой модели. В нашем случае ветки дерева

В переменной модели $newAttachIds поведением будут загружен текущий список привязанных моделей, после сохранения модели поведение будет сохранять привязки которые были изменены

В отображении надо добавить следующий виджет, который создает список чекбоксов с дополнительной стилизацией под дерево.
```php
<? $this->widget('treemp.widgets.TreempMultiattachWidget', array(
	'model' => $model,
	'attribute' => 'newAttachIds',
	'treempModel' => 'Treetest',
)) ?>
```
Виджет TreempMultiattachWidget имеет дополнительные возможности по настройке. Читайте [API reference](https://github.com/wartur/yii-treemp/blob/master/widgets/TreempMultiattachWidget.php).

Удачной работы!
