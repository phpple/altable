<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/29 18:43
 * @copyright: 2018@hunbasha.com
 * @filesource: Structure.php
 */

namespace Phpple\Altable;

use Phpple\Altable\Table\DbEntity;
use Phpple\Altable\Table\IEntity;
use Phpple\Altable\Table\TableEntity;
use Phpple\Altable\Table\ExtraEntity;
use Phpple\Altable\Table\IndexEntity;
use Phpple\Altable\Table\FieldEntity;

class Parser
{
    public $dbFilters = [];

    const PREFIX_DB = 'db';
    const PREFIX_TABLE = 'table';
    const PREFIX_FIELD = 'field';
    const PREFIX_PK = 'pk';
    const PREFIX_INDEX = 'index';
    const PREFIX_UNIQUE_INDEX = 'uniqueIndex';
    const PREFIX_EXTRA = 'extra';

    const FIELD_PARSE_EXP = '#([a-zA-Z][a-zA-Z\_\-0-9]+)`' .    // 1:字段名称
    '\s+([a-z]+)' .                                             // 2:字段类型
    '(?:\(([^\)]+)\))?' .                                       // 3:字段长度
    '(?:\s+(unsigned))?' .                                      // 4:是否无符号
    '(?:\s+CHARACTER\s+SET\s+([^\s]+))?'.                       // 5:编码
    '(?:\s+COLLATE\s+([^\s]+))?'.                               // 6:collate
    '(?:(\s+NOT)?\s+NULL)?' .                                   // 7:是否允许为null
    '(\s+AUTO_INCREMENT)?' .                                    // 8:是否自增
    '(?:\s+DEFAULT\s+(\'[^\']*\'|[^\'\s\b]+))?' .               // 9:默认值x
    '(?:\s+ON\s+UPDATE\s+([^\s,]+))?'.                          // 10:update更新内容
    '(?:\s+COMMENT\s+\'(.+)\')?,?$#'                            // 11:字段注释
    ;

    const EXTRA_PARSE_EXP = '#ENGINE=([^\s]+)' . // 1:Engine
    '(?:\s+AUTO_INCREMENT=(\d+))?' .             // 2:autoIncrement
    '(?:\s+DEFAULT CHARSET=([^\s]+))?' .         // 3:charset
    '(?:\s+COLLATE=([^\s]+))?'.                  // 4:collate
    '(?:\s+ROW_FORMAT=([^\s]+))?'.               // 5:rowFormat
    '(?:\s+COMMENT=\'(.+)\')?'.                  // 6.comment
    ';#';                                        // 7:comment

    /**
     * 特殊的前缀
     * @var array
     */
    private static $specialPrefixes = [
        self::PREFIX_DB => 'USE `',
        self::PREFIX_TABLE => 'CREATE TABLE `',
        self::PREFIX_FIELD => '  `',
        self::PREFIX_PK => '  PRIMARY KEY (',
        self::PREFIX_INDEX => '  KEY ',
        self::PREFIX_UNIQUE_INDEX => '  UNIQUE KEY ',
        self::PREFIX_EXTRA => ') ',
    ];

    private static $specialPrefixLens = null;
    const SPLIT_FLAG = '`';

    public function __construct()
    {
        if (self::$specialPrefixLens === null) {
            self::$specialPrefixLens = [];
            foreach (self::$specialPrefixes as $key => $prefix) {
                self::$specialPrefixLens[$key] = strlen($prefix);
            }
        }
    }

    /**
     * 解析某种关键字
     * @param string $key
     * @param string $line
     * @return IEntity|null
     */
    public function detectPrefix($key, $line)
    {
        $prefix = self::$specialPrefixes[$key];
        $len = self::$specialPrefixLens[$key];
        if (strncmp($line, $prefix, $len) !== 0) {
            return null;
        }

        $method = 'parse' . ucfirst($key);
        return $this->$method(substr($line, $len));
    }

    /**
     * 解析DB名
     * @param string $line
     * @return null|string
     */
    public function parseDb($line)
    {
        $pos = strpos($line, self::SPLIT_FLAG);
        if ($pos === false) {
            return null;
        }
        return substr($line, 0, $pos);
    }

    /**
     * 解析TABLE名称
     * @param string $line
     * @return null|string
     */
    public function parseTable($line)
    {
        $pos = strpos($line, self::SPLIT_FLAG);
        if ($pos === false) {
            return null;
        }
        return substr($line, 0, $pos);
    }

    /**
     * 解析字段名称
     * @param string $line
     * @return FieldEntity|null
     */
    public function parseField($line)
    {
        if (preg_match(self::FIELD_PARSE_EXP, $line, $ms)) {
            $fieldEntity = new FieldEntity();
            $fieldEntity->name = $ms[1];
            $fieldEntity->type = $ms[2];
            // 如果是int现关类型，后面的长度没有意义
            // @see https://dev.mysql.com/doc/refman/5.6/en/integer-types.html
            if (strpos($fieldEntity->type, 'int') !== false) {
                $fieldEntity->length = 0;
            } elseif ($fieldEntity->type == 'enum' || $fieldEntity->type == 'set') {
                $options = explode(',', $ms[3]);
                foreach ($options as $key => $option) {
                    $options[$key] = trim($option, '\'');
                }
                $fieldEntity->options = $options;
            } else {
                // 如果是双精度，可能是DECIMAL(5,2)、DOUBLE(16,2)的样子
                // @see https://dev.mysql.com/doc/refman/5.6/en/fixed-point-types.html
                // @see https://dev.mysql.com/doc/refman/5.6/en/floating-point-types.html
                $fieldEntity->length = isset($ms[3]) ? $ms[3] : 0;
            }
            $fieldEntity->unsigned = !empty($ms[4]);
            $fieldEntity->charset = isset($ms[5]) ? $ms[5] : null;
            $fieldEntity->collate = isset($ms[6]) ? $ms[6] : null;
            $fieldEntity->notnull = !empty($ms[7]);
            $fieldEntity->autoinc = !empty($ms[8]);
            $fieldEntity->default = isset($ms[9]) ? trim($ms[9], "'") : null;
            $fieldEntity->onupdate = isset($ms[10]) ? $ms[10] : null;
            $fieldEntity->comment = isset($ms[11]) ? $ms[11] : null;
            return $fieldEntity;
        }
        return null;
    }

    /**
     * 解析主键
     * @example PRIMARY KEY (`tag_id`,`topic_id`,`type`)
     * @param string $line
     * @return string[]|null
     */
    public function parsePk($line)
    {
        $pos = strpos($line, ')');
        if ($pos !== false) {
            $pks = explode(',', substr($line, 0, $pos));
            foreach ($pks as &$pk) {
                $pk = trim($pk, self::SPLIT_FLAG);
            }
            return $pks;
        }
        return null;
    }

    /**
     * 解析索引
     * @example
     * KEY `nw_ticket_id` (`nw_ticket_id`)
     * UNIQUE KEY `admin_search` (`expo_id`,`project_id`,`ticket_status`,`add_time`),
     * UNIQUE KEY `ticket_id` (`ticket_id`) USING BTREE
     * @param string $line
     * @param bool $unique
     * @return IndexEntity|null
     */
    public function parseIndex($line, $unique = false)
    {
        $leftPos = strpos($line, '(');
        if ($leftPos === false) {
            return null;
        }
        $rightPos = strpos($line, ')', $leftPos + 1);
        if ($rightPos === false) {
            return null;
        }
        $indexEntity = new IndexEntity();
        $indexEntity->name = substr($line, 1, $leftPos - 3);
        $fields = explode(',', substr($line, $leftPos + 1, $rightPos - $leftPos - 1));
        foreach ($fields as &$field) {
            $field = substr($field, 1, -1);
        }
        $indexEntity->fields = $fields;

        if (preg_match('#USING (BTREE|HASH)#', substr($line, $rightPos + 1), $ms)) {
            $indexEntity->type = $ms[1];
        }
        $indexEntity->unique = $unique;
        return $indexEntity;
    }

    /**
     * 解析唯一索引
     * @param string $line
     * @see Parser::parseIndex()
     * @return null|IndexEntity
     */
    public function parseUniqueIndex($line)
    {
        return $this->parseIndex($line, true);
    }

    /**
     * 解析额外的信息，如engine、charset等
     * @example ) ENGINE=InnoDB AUTO_INCREMENT=210 DEFAULT CHARSET=utf8;
     * @param string $line
     * @return ExtraEntity
     */
    public function parseExtra($line)
    {
        if (preg_match(self::EXTRA_PARSE_EXP, $line, $ms)) {
            $extraEntity = new ExtraEntity();
            $extraEntity->engine = isset($ms[1]) ? $ms[1] : '';
            $extraEntity->autoIncrement = isset($ms[2]) ? intval($ms[2]) : 0;
            $extraEntity->charset = isset($ms[3]) ? $ms[3] : '';
            $extraEntity->collate = isset($ms[4]) ? $ms[4] : '';
            $extraEntity->rowFormat = isset($ms[5]) ? $ms[5] : '';
            $extraEntity->comment = isset($ms[6]) ? $ms[6] : '';
            return $extraEntity;
        }
        return null;
    }


    /**
     * 解析SQL文件
     * @param string $file
     * @return DbEntity[]
     */
    public function parse($file)
    {
        if (!is_readable($file)) {
            throw new \InvalidArgumentException('parser.fileNotReadable');
        }

        $fh = fopen($file, 'r');
        $dbs = [];
        $dbEntity = null;
        $tableEntity = null;

        $ignoreDb = false;
        $ignoreTb = false;
        $dbNames = [];

        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line);
            // 过滤掉注释内容
            if (!$line || strncmp($line, '/*!', 3) === 0 || strncmp($line, '--', 2) === 0) {
                continue;
            }

            //记录库名
            $ret = $this->detectPrefix(self::PREFIX_DB, $line);
            if ($ret !== null) {
                $dbname = $ret;
                // 如果在需要被过滤的db名称，则不予处理
                // 如果值为null，表示此库全部过滤
                if ($dbname
                    && array_key_exists($dbname, $this->dbFilters)
                    && $this->dbFilters[$dbname] === null) {
                    $ignoreDb = true;
                    continue;
                } else {
                    $ignoreDb = false;
                }

                if (isset($dbNames[$ret])) {
                    $dbEntity = null;
                    continue;
                }

                // 检查是否已经有过该库
                $dbEntity = new DbEntity();
                $dbEntity->name = $ret;
                $dbs[] = $dbEntity;
                $dbNames[$ret] = true;
                continue;
            }
            if ($ignoreDb || !$dbEntity) {
                continue;
            }

            //记录表名
            $ret = $this->detectPrefix(self::PREFIX_TABLE, $line);
            if ($ret !== null) {
                $tbName = $ret;
                // 过滤需要处理的table
                if ($dbname
                    && $tbName
                    && isset($this->dbFilters[$dbname])
                    && in_array($tbName, $this->dbFilters[$dbname])) {
                    $ignoreTb = true;
                    continue;
                } else {
                    $ignoreTb = false;
                }

                $tableEntity = new TableEntity();
                $tableEntity->name = $tbName = $ret;
                continue;
            }
            if ($ignoreTb) {
                continue;
            }

            // 找出字段
            $ret = $this->detectPrefix(self::PREFIX_FIELD, $line);
            if ($ret !== null) {
                $tableEntity->addField($ret);
                continue;
            }

            // 找出主键
            $ret = $this->detectPrefix(self::PREFIX_PK, $line);
            if ($ret !== null) {
                $tableEntity->setPrimaryKeys($ret);
                continue;
            }

            $ret = $this->detectPrefix(self::PREFIX_INDEX, $line);
            if ($ret !== null) {
                $tableEntity->addIndex($ret);
                continue;
            }

            $ret = $this->detectPrefix(self::PREFIX_UNIQUE_INDEX, $line);
            if ($ret !== null) {
                $tableEntity->addIndex($ret);
                continue;
            }

            $ret = $this->detectPrefix(self::PREFIX_EXTRA, $line);
            if ($ret !== null) {
                foreach (['charset', 'autoIncrement', 'engine', 'comment', 'collate'] as $key) {
                    $tableEntity->$key = $ret->$key;
                }
                $dbEntity->addTable($tableEntity);
                continue;
            }
        }
        fclose($fh);
        return $dbs;
    }

    /**
     * 根据名称找到对象
     * @param DbEntity[] $dbs
     * @param string $dbName
     * @param string $tbName
     * @param string $fieldName
     * @example
     * 找DbEntity: ->listEntity($dbs, $dbName)
     * 找TableEntity: ->listEntity($dbs, $dbName, $tbName)
     * 找FieldEntity: ->listEntity($dbs, $dbName, $tbName, $fieldName)
     * @return IEntity
     * @throws \InvalidArgumentException parser.dbNameRequired
     */
    public function findEntity($dbs, $dbName, $tbName = '', $fieldName = '')
    {
        if (!$dbName) {
            throw new \InvalidArgumentException('parser.dbNameRequired');
        }
        // 查找db
        $foundDb = null;
        foreach ($dbs as $db) {
            if ($db->name == $dbName) {
                $foundDb = $db;
                break;
            }
        }
        if (!$foundDb || !$tbName) {
            return $foundDb;
        }

        // 查找table
        $foundTb = null;
        foreach ($foundDb->tables as $table) {
            if ($table->name == $tbName) {
                $foundTb = $table;
                break;
            }
        }
        if (!$foundTb || !$fieldName) {
            return $foundTb;
        }

        // 查找field
        foreach ($foundTb->fields as $field) {
            if ($field->name == $fieldName) {
                return $field;
            }
        }
        return null;
    }
}
