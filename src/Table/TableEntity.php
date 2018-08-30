<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:22
 * @copyright: 2018@hunbasha.com
 * @filesource: Structure.php
 */

namespace Phpple\Altable\Table;

/**
 * 数据表解析类
 * @see https://dev.mysql.com/doc/refman/5.6/en/create-table.html
 * @package Phpple\Altable\Table
 */
class TableEntity implements IEntity
{
    /**
     * @var string 名称
     */
    public $name;
    /**
     * @var FieldEntity[]
     */
    public $fields = [];
    /**
     * @var string[] 主键
     */
    public $pk = [];
    /**
     * @var IndexEntity[]
     */
    public $indexes = [];

    /**
     * @var string 引擎
     */
    public $engine;
    /**
     * @var string 编码
     */
    public $charset;
    /**
     * @var string 校对集
     */
    public $collate;

    /**
     * @var int 从哪个数自增
     */
    public $autoIncrement = 0;
    /**
     * @var string 评论
     */
    public $comment;

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
                    $f->pk = true;
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
