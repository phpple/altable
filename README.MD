Phpple Altable
================

本项目用来对通过mysqldump出来的表结构数据进行解析，分析出数据库、数据表、字段、主键、索引等信息。
[![Latest Stable Version](https://img.shields.io/packagist/v/phpple/altable.svg?style=flat-square)](https://packagist.org/packages/phpple/mysql)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://img.shields.io/travis/phpple/altable/master.svg?style=flat-square)](https://travis-ci.org/phpple/mysql)
[![codecov](https://codecov.io/gh/phpple/altable/branch/master/graph/badge.svg)](https://codecov.io/gh/phpple/mysql)

## 使用步骤

composer引入项目
```bash
composer require phpple/altable
```

通过mysqldump导出需要的表结构：
```bash
mysqldump --all-databases --no-data > dump.sql
```


编写php脚本分析数据库结构
```php
<?php
require 'vendor/autoload.php';

$parser = new Phpple\Altable\Parser();
// 设定不需要分析哪些库或者表
$parser->dbFilters = [
    // 整个库不予分析
    'mysql' => null,
    // foo.bar不予分析
    'foo' => ['bar'],
];
$dbs = Phpple\Altable\Parser::parse(__DIR__.'/dump.sql');

// 开始进行分析
foreach($dbs as $db) {
    foreach($db->tables as $table) {
        foreach($table->fields as $field) {
            if ($field->name == 'uid') {
                echo "`{$db->name}`.`{$table->name}` found uid field";
            }
        }
    }
}
```