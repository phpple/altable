<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 19:23
 * @copyright: 2018@hunbasha.com
 * @filesource: Index.php
 */

namespace Phpple\Altable\Table;

class IndexEntity
{
    /**
     * @var string 名称
     */
    public $name;
    /**
     * @var string[] 字段
     */
    public $fields = [];
    /**
     * @var string 类型
     */
    public $type;
    /**
     * @var bool 是否唯一
     */
    public $unique = false;
}
