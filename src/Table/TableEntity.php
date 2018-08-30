<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:22
 * @copyright: 2018@hunbasha.com
 * @filesource: Structure.php
 */

namespace Phpple\Altable\Table;

class TableEntity implements IEntity
{
    /**
     * @var string
     */
    public $name;
    /**
     * @var FieldEntity[]
     */
    public $fields = [];
    /**
     * @var string[]
     */
    public $pk = [];
    /**
     * @var IndexEntity[]
     */
    public $indexes = [];

    /**
     * @var string
     */
    public $comment;
    /**
     * @var int
     */
    public $autoIncrement = 0;
    /**
     * @var string
     */
    public $charset;
    /**
     * @var string
     */
    public $engine;

    /**
     * 添加字段
     * @param FieldEntity $field
     * @return $this
     */
    public function addField(FieldEntity $field)
    {
        $this->fields[] = $field;
        return $this;
    }

    /**
     * 设置主键
     * @param mixed $fields
     */
    public function setPrimaryKeys($fields)
    {
        foreach ($fields as $field) {
            foreach ($this->fields as $f) {
                if ($f->name == $field) {
                    $f->primaryKey = true;
                }
            }
        }
    }

    /**
     * 添加索引
     * @param IndexEntity $index
     */
    public function addIndex(IndexEntity $index)
    {
        $this->indexes[] = $index;
    }
}
