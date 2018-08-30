<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:23
 * @copyright: 2018@hunbasha.com
 * @filesource: Field.php
 */

namespace Phpple\Altable\Table;

class FieldEntity implements IEntity
{
    /**
     * @var string 名称
     */
    public $name;
    /**
     * @var string 字段类型
     */
    public $type;
    /**
     * @var string 评论
     */
    public $comment;
    /**
     * @var bool 是否自增
     */
    public $autoIncrement = false;
    /**
     * @var bool 是否不允许为NULL
     */
    public $notNull = false;
    /**
     * @var mixed 默认值
     */
    public $default;
    /**
     * @var bool 是否为主键
     */
    public $primaryKey = false;
}
