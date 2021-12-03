<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:47
 * @copyright: 2018@hunbasha.com
 * @filesource: ParserTest.php
 */

namespace Phpple\Altable\Tests;

use Phpple\Altable\Parser;
use Phpple\Altable\Table\DbEntity;
use Phpple\Altable\Table\ExtraEntity;
use Phpple\Altable\Table\FieldEntity;
use Phpple\Altable\Table\TableEntity;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    /**
     * @var Parser
     */
    private $parser;

    /**
     * @before
     */
    public function before()
    {
        $this->parser = new Parser();
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage parser.fileNotReadable
     */
    public function testFileNotReadable()
    {
        $file = __DIR__.'/notexisted.sql';
        $this->parser->parse($file);
    }

    public function testCompile()
    {
        $file = __DIR__ . '/dump.sql';
        $dbs = $this->parser->parse($file);
        $this->assertEquals(1, count($dbs));
        foreach ($dbs as $db) {
            $this->assertInstanceOf(DbEntity::class, $db);

            foreach ($db->tables as $table) {
                $this->assertInstanceOf(TableEntity::class, $table);
            }
        }
        // 字段个数
        $this->assertEquals(5, count($dbs[0]->tables[0]->fields));
        $this->assertEquals(11, count($dbs[0]->tables[1]->fields));
        $this->assertEquals('phpple', $dbs[0]->name);
        $this->assertEquals(['foo', 'u_user'], [$dbs[0]->tables[0]->name, $dbs[0]->tables[1]->name]);

        $table = $dbs[0]->tables[0];
        $this->assertEquals('foo', $table->name);
        $this->assertEquals('utf8', $table->charset);
        $this->assertEquals('InnoDB', $table->engine);

        $this->parser->dbFilters = ['phpple' => null];
        $dbs = $this->parser->parse($file);
        $this->assertEmpty($dbs);

        $this->parser->dbFilters = ['phpple' => ['foo']];
        $dbs = $this->parser->parse($file);
        $this->assertEquals(1, count($dbs));
        $this->assertEquals(1, count($dbs[0]->tables));
        $this->assertEquals('u_user', $dbs[0]->tables[0]->name);
        $this->assertEquals('id', $dbs[0]->tables[0]->fields[0]->name);
        $this->assertTrue($dbs[0]->tables[0]->fields[0]->pk);
        $this->assertEquals('del_flag', $dbs[0]->tables[0]->fields[8]->name);
        $this->assertFalse($dbs[0]->tables[0]->fields[8]->pk);
        $this->assertTrue($dbs[0]->tables[0]->fields[8]->notnull);
        $this->assertContains('tinyint', $dbs[0]->tables[0]->fields[8]->type);
    }

    public function testFind()
    {
        $dbName = 'phpple';
        $tbName = 'u_user';
        $fieldName = 'view_num';
        $notexistName = 'xxsdfsdfsd';

        $file = __DIR__ . '/dump.sql';
        $dbs = $this->parser->parse($file);

        try {
            $this->parser->findEntity($dbs, '');
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('parser.dbNameRequired', $ex->getMessage());
        }

        $db = $this->parser->findEntity($dbs, $notexistName);
        $this->assertNull($db);

        $db = $this->parser->findEntity($dbs, $dbName);
        $this->assertInstanceOf(DbEntity::class, $db);
        $this->assertEquals($dbName, $db->name);

        $tb = $this->parser->findEntity($dbs, $notexistName, $notexistName);
        $this->assertNull($tb);

        $tb = $this->parser->findEntity($dbs, $dbName, $notexistName);
        $this->assertNull($tb);

        $tb = $this->parser->findEntity($dbs, $dbName, $tbName);
        $this->assertInstanceOf(TableEntity::class, $tb);
        $this->assertEquals($tbName, $tb->name);

        $field = $this->parser->findEntity($dbs, $dbName, $tbName, $notexistName);
        $this->assertNull($field);

        $field = $this->parser->findEntity($dbs, $dbName, $tbName, $fieldName);
        $this->assertInstanceOf(FieldEntity::class, $field);
        $this->assertEquals($fieldName, $field->name);
    }


    public function testParseDb()
    {
        $line = 'USE `fine_db`;';
        $dbname = $this->parser->detectPrefix(Parser::PREFIX_DB, $line);
        $this->assertEquals('fine_db', $dbname);

        $line = 'USE `fine_db';
        $dbname = $this->parser->detectPrefix(Parser::PREFIX_DB, $line);
        $this->assertNull($dbname);
    }

    public function testParseTable()
    {
        $line = 'CREATE TABLE `post` (';
        $tbname = $this->parser->detectPrefix(Parser::PREFIX_TABLE, $line);
        $this->assertEquals('post', $tbname);

        $line = 'CREATE TABLE `post" (';
        $tbname = $this->parser->detectPrefix(Parser::PREFIX_TABLE, $line);
        $this->assertNull($tbname);
    }

    public function testParseField()
    {
        // 普通字段
        $line = '  `uname` varchar(36) NOT NULL COMMENT \'用户名\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('uname', $field->name);
        $this->assertEquals('varchar', $field->type);
        $this->assertEquals(36, $field->length);
        $this->assertTrue($field->notnull);
        $this->assertFalse($field->unsigned);
        $this->assertEquals('用户名', $field->comment);
        $this->assertFalse($field->autoinc);

        // notnull，autoincrement
        $line = '  `uid` int(10) unsigned NOT NULL AUTO_INCREMENT,';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertTrue($field->notnull);
        $this->assertTrue($field->autoinc);

        // 错误解析
        $line = '  `uname` varchar(36) NOT COMMENT \'用户名\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNull($field);

        // charset
        $line = '  `template_value` mediumtext CHARACTER SET utf8mb4 NOT NULL COMMENT \'配置项json\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('utf8mb4', $field->charset);
        $this->assertTrue($field->notnull);
        $this->assertEquals('配置项json', $field->comment);

        // collate
        $line = '  `domain` varchar(32) COLLATE utf8_bin NULL COMMENT \'域名\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('utf8_bin', $field->collate);
        $this->assertEquals('域名', $field->comment);
        $this->assertFalse($field->notnull);

        // onupdate
        $line = '  `start_time` timestamp(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('CURRENT_TIMESTAMP(6)', $field->default);
        $this->assertEquals(6, $field->length);
        $this->assertEquals('CURRENT_TIMESTAMP(6)', $field->onupdate);

        // enum类型
        $line = "  `ssl_type` enum('','ANY','X509','SPECIFIED') CHARACTER SET utf8 NOT NULL DEFAULT '',";
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNotNull($field);
        $this->assertEquals('enum', $field->type);
        $this->assertEquals(['', 'ANY', 'X509', 'SPECIFIED'], $field->options);
        $this->assertEquals('utf8', $field->charset);
        $this->assertEquals('', $field->default);

        // set类型
        $line = "  `Column_priv` set('Select','Insert','Update','References') CHARACTER SET utf8 NOT NULL DEFAULT '',";
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('set', $field->type);
        $this->assertEquals(['Select','Insert','Update','References'], $field->options);
        $this->assertEquals('utf8', $field->charset);
        $this->assertEquals('', $field->default);

        // 同时有charset和collate
        $line = "  `community_desc` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '帖子描述',";
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('community_desc', $field->name);
        $this->assertEquals(256, $field->length);
        $this->assertEquals('utf8mb4', $field->charset);
        $this->assertEquals('utf8mb4_unicode_ci', $field->collate);
        $this->assertEquals('帖子描述', $field->comment);

        // 乱码
        $line = "  `auth_type` tinyint(1) unsigned NOT NULL COMMENT 'éªŒè¯<81>ç±»åž‹ 1 BasicéªŒè¯<81>',";
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNotEquals(1, $field->length);
        $this->assertEquals('éªŒè¯<81>ç±»åž‹ 1 BasicéªŒè¯<81>', $field->comment);

        $line = "  `campus_name_new` varchar(100) NOT NULL DEFAULT '' COMMENT '教学中心名称'";
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('campus_name_new', $field->name);

        // 默认值有空格
        $line = '  `sub_item_date` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\' COMMENT \'购物时间\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNotNull($field);
        $this->assertEquals('sub_item_date', $field->name);
        $this->assertEquals('0000-00-00 00:00:00', $field->default);
    }

    public function testParsePk()
    {
        $line = '  PRIMARY KEY (`uid`)';
        $pks = $this->parser->detectPrefix(Parser::PREFIX_PK, $line);
        $this->assertEquals(['uid'], $pks);

        $line = '  PRIMARY KEY (`city_id`,`uid`)';
        $pks = $this->parser->detectPrefix(Parser::PREFIX_PK, $line);
        $this->assertEquals(['city_id', 'uid'], $pks);

        $line = 'PRIMARY KEY (`uid`)';
        $pks = $this->parser->detectPrefix(Parser::PREFIX_PK, $line);
        $this->assertNull($pks);

        $line = '  PRIMARY KEY (`uid`';
        $pks = $this->parser->detectPrefix(Parser::PREFIX_PK, $line);
        $this->assertNull($pks);

        $line = '  PRIMARY KEY (`third_remark_id`) USING BTREE,';
        $pks = $this->parser->detectPrefix(Parser::PREFIX_PK, $line);
        $this->assertEquals(['third_remark_id'], $pks);
    }

    public function testParseIndex()
    {
        $line = '  KEY `idx_email` (`email`)';
        $index = $this->parser->detectPrefix(Parser::PREFIX_INDEX, $line);
        $this->assertEquals(['email'], $index->fields);

        $line = '  KEY `idx_city_id_sex` (`city_id`,`sex`) USING BTREE';
        $index = $this->parser->detectPrefix(Parser::PREFIX_INDEX, $line);
        $this->assertEquals('idx_city_id_sex', $index->name);
        $this->assertEquals('BTREE', $index->type);

        $line = '  KEY `idx_city_id_sex` `city_id`';
        $index = $this->parser->detectPrefix(Parser::PREFIX_INDEX, $line);
        $this->assertNull($index);
        $line = '  KEY `idx_city_id_sex`(`city_id`';
        $index = $this->parser->detectPrefix(Parser::PREFIX_INDEX, $line);
        $this->assertNull($index);
    }

    public function testExtra()
    {
        $line = ') ENGINE=MyIsam AUTO_INCREMENT=1100000 DEFAULT CHARSET=utf9;';
        $extra = $this->parser->detectPrefix(Parser::PREFIX_EXTRA, $line);
        $this->assertInstanceOf(ExtraEntity::class, $extra);
        $this->assertEquals('utf9', $extra->charset);
        $this->assertEquals('MyIsam', $extra->engine);

        $line = ') 3ENGINE=InnoDB 3AUTO_INCREMENT=1100000 DEFAULT CHARSET=utf9;';
        $extra = $this->parser->detectPrefix(Parser::PREFIX_EXTRA, $line);
        $this->assertNull($extra);

        $line = ') ENGINE=InnoDB AUTO_INCREMENT=1018 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;';
        $extra = $this->parser->detectPrefix(Parser::PREFIX_EXTRA, $line);
        $this->assertNotNull($extra);
        $this->assertEquals('utf8', $extra->charset);
        $this->assertEquals('utf8_bin', $extra->collate);

        $line = ') ENGINE=InnoDB AUTO_INCREMENT=9688 DEFAULT CHARSET=utf8 COMMENT=\'婚礼请柬-用户创建的请帖管理\';';
        $extra = $this->parser->detectPrefix(Parser::PREFIX_EXTRA, $line);
        $this->assertNotNull($extra);
        $this->assertEquals(9688, $extra->autoIncrement);
        $this->assertEquals('utf8', $extra->charset);
        $this->assertEquals('婚礼请柬-用户创建的请帖管理', $extra->comment);

        $line = ') ENGINE=InnoDB AUTO_INCREMENT=252912 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT=\'员工信息历史记录表\';';
        $extra = $this->parser->detectPrefix(Parser::PREFIX_EXTRA, $line);
        $this->assertNotNull($extra);
        $this->assertEquals(252912, $extra->autoIncrement);
        $this->assertEquals('utf8', $extra->charset);
        $this->assertEquals('COMPACT', $extra->rowFormat);
    }

    /**
     * 如果一个字段是默认的null，会出现解析错误
     */
    public function testDefaultNull()
    {
        $line = '  `name_ab` varchar(100) CHARACTER SET utf8mb4 DEFAULT NULL COMMENT \'校区简称\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNotNull($field);
        $this->assertEquals('NULL', $field->default);
        $this->assertFalse($field->notnull);
    }
}
