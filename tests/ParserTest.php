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
        $this->assertTrue($dbs[0]->tables[0]->fields[0]->primaryKey);
        $this->assertEquals('del_flag', $dbs[0]->tables[0]->fields[8]->name);
        $this->assertFalse($dbs[0]->tables[0]->fields[8]->primaryKey);
        $this->assertTrue($dbs[0]->tables[0]->fields[8]->notNull);
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
        $line = '  `uname` varchar(36) NOT NULL COMMENT \'用户名\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertEquals('uname', $field->name);

        $line = '  `uname` varchar(36) NOT COMMENT \'用户名\',';
        $field = $this->parser->detectPrefix(Parser::PREFIX_FIELD, $line);
        $this->assertNull($field);
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
    }
}
