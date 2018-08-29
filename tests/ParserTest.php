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
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testCompile()
    {
        $file = '/Users/ronnie/Documents/sqls/3306.sql';
        $parser = new Parser();
        $dbs = $parser->parse($file);
        $this->assertNotEmpty($dbs);
        var_dump($dbs[0]->tables[0]);
    }
}
