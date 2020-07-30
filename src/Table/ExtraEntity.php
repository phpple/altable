<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 20:59
 * @copyright: 2018@hunbasha.com
 * @filesource: TableEntity.php
 */

namespace Phpple\Altable\Table;

class ExtraEntity implements IEntity
{
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
     * @var string 行格式
     */
    public $rowFormat;
}
