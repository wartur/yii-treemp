For running tests
==================

It is believed that the extension is in the following directory (used aliases and relative paths):
ext.wartur.yii-treemp

- Add the settings in the config/main.php as described ext.wartur.yii-treemp/README.md
(hereinafter referred to believe that there was an alias "treemp")

- Create a directory
/test/unit/extentions/wartur

- Create a symbolic link
treemp.tests.unit >>> application/test/unit/extentions/wartur/yii-treemp
ln -s /path/to/treemp__/tests/unut /path/to/project/tests__/unit/extentions/wartur
If you have a Window, use junction

- Создать семантическую ссылку для фикстуры

- Настроить конфингурационный файл согласно описанию