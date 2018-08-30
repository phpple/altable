<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:23
 * @copyright: 2018@hunbasha.com
 * @filesource: Field.php
 */

namespace Phpple\Altable\Table;

/**
 * 字段对象
 * @see https://dev.mysql.com/doc/refman/5.6/en/data-types.html
 * @package Phpple\Altable\Table
 */
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
     * @var array 可选项，如果是enum或者set时才有用
     */
    public $options = [];

    /**
     * @var int 长度
     */
    public $length;
    /**
     * @var string 评论
     */
    public $comment;
    /**
     * @var bool 是否自增
     */
    public $autoinc = false;

    /**
     * @var string 编码，文本类型才有意义
     */
    public $charset;

    /**
     * @var string 校对集,文本类型才有意义
     */
    public $collate;

    /**
     * @var bool 是否无符号，整形才有意义
     */
    public $unsigned = false;

    /**
     * @var bool 是否不允许为NULL
     */
    public $notnull = false;
    /**
     * @var mixed 默认值
     */
    public $default;
    /**
     * @var bool 是否为主键
     */
    public $pk = false;
}
