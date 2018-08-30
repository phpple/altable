<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 21:12
 * @copyright: 2018@hunbasha.com
 * @filesource: DbEntity.php
 */

namespace Phpple\Altable\Table;

class DbEntity implements IEntity
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var TableEntity[]
     */
    public $tables = [];

    /**
     * 添加Table
     * @param TableEntity $table
     */
    public function addTable(TableEntity $table)
    {
        $this->tables[] = $table;
    }
}
