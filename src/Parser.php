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
use Phpple\Altable\Table\TableEntity;
use Phpple\Altable\Table\ExtraEntity;
use Phpple\Altable\Table\IndexEntity;
use Phpple\Altable\Table\FieldEntity;

class Parser
{
    public $dbFilters = [];

    const DB_PREFIX = 'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `';

    const PREFIX_DB = 'db';
    const PREFIX_TABLE = 'table';
    const PREFIX_FIELD = 'field';
    const PREFIX_PK = 'pk';
    const PREFIX_INDEX = 'index';
    const PREFIX_UNIQUE_INDEX = 'uniqueIndex';
    const PREFIX_EXTRA = 'extra';

    const FIELD_PARSE_EXP = '#([a-zA-Z][a-zA-Z\_\-0-9]+)`' .    // 1:字段名称
    '\s+(.+)\s+' .                                              // 2:字段类型
    '((?:NOT\s*)?\s+NULL)' .                                    // 3:是否允许为null
    '(\s+AUTO_INCREMENT)?' .                                    // 4:是否自增
    '(?:\s+DEFAULT\s+\'?([0-9a-zA-Z\_\-]+)\')?' .               // 5:默认值
    '(?:\s+COMMENT\s+\'(.+)\')?,$#'                           // 6:字段注释
    ;

    const EXTRA_PARSE_EXP = '#ENGINE=([^\s+])' . // 1:Engine
    '(?:\s+AUTO_INCREMENT=(\d+))?' .             // 2:autoIncrement
    '(?:\s+DEFAULT CHARSET=(\w+))?' .            // 3:charset
    '(?:COMMENT=\'(.+)\')?;#';                  // 4:comment

    /**
     * 特殊的前缀
     * @var array
     */
    private static $specialPrefixes = [
        self::PREFIX_DB => 'CREATE DATABASE /*!32312 IF NOT EXISTS*/ `',
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
     * @param $key
     * @param $line
     * @return mixed|null
     */
    private function detectPrefix($key, $line)
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
     * @param $line
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
     * @param $line
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
     * @param $line
     * @return FieldEntity|null
     */
    public function parseField($line)
    {
        if (preg_match(self::FIELD_PARSE_EXP, $line, $ms)) {
            $fieldEntity = new FieldEntity();
            $fieldEntity->name = $ms[1];
            $fieldEntity->type = $ms[2];
            $fieldEntity->notNull = strpos($ms[3], 'NOT') !== false;
            $fieldEntity->autoIncrement = !empty($ms[4]);
            $fieldEntity->default = $ms[5] ?? null;
            $fieldEntity->comment = $ms[6] ?? '';
            return $fieldEntity;
        }
        return null;
    }

    /**
     * 解析主键
     * @example PRIMARY KEY (`tag_id`,`topic_id`,`type`)
     * @param $line
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

        if (strpos(substr($line, $rightPos + 1), ' BTREE') !== false) {
            $indexEntity->type = 'btree';
        }
        $indexEntity->unique = $unique;
        return $indexEntity;
    }

    /**
     * 解析唯一索引
     * @see Parser::parseIndex()
     */
    public function parseUniqueIndex($line)
    {
        return $this->parseIndex($line, true);
    }

    /**
     * 解析额外的信息，如engine、charset等
     * @example ) ENGINE=InnoDB AUTO_INCREMENT=210 DEFAULT CHARSET=utf8;
     * @param $line
     */
    public function parseExtra($line)
    {
        if (preg_match(self::EXTRA_PARSE_EXP, $line, $ms)) {
            $extraEntity = new ExtraEntity();
            $extraEntity->engine = $ms[1] ?? '';
            $extraEntity->autoIncrement = $ms[2] ?? 0;
            $extraEntity->charset = $ms[3] ?? '';
            $extraEntity->comment = $ms[4] ?? '';
        }
    }


    /**
     * @param $file
     * @return DbEntity[]
     */
    public function parse($file)
    {
        $fh = fopen($file, 'r');
        $dbname = '';
        $tbName = '';
        $dbs = [];
        $dbEntity = null;
        $tableEntity = null;

        $ignoreDb = false;
        $ignoreTb = false;

        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line);
            // 过滤掉注释内容
            if (!$line || strncmp($line, '/*!', 3) === 0 || strncmp($line, '--', 2) === 0) {
                continue;
            }

            //记录库名
            $ret = $this->detectPrefix(self::PREFIX_DB, $line);
            if ($ret !== null) {
                // 如果在需要被过滤的db名称，则不予处理
                // 如果值为null，表示此库全部过滤
                if ($dbname
                    && isset($this->dbFilters[$dbname])
                    && $this->dbFilters[$dbname] === null) {
                    $ignoreDb = true;
                    continue;
                } else {
                    $ignoreDb = false;
                }

                $dbEntity = new DbEntity();
                $dbEntity->name = $ret;
                $dbs[] = $dbEntity;
                continue;
            }
            if ($ignoreDb) {
                continue;
            }

            //记录表名
            $ret = $this->detectPrefix(self::PREFIX_TABLE, $line);
            if ($ret !== null) {
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
                $tableEntity->table = $tbName = $ret;
                $tableEntity = new TableEntity();
                $tableEntity->name = $ret;
                $dbEntity->addTable($tableEntity);
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
                foreach (['charset', 'autoIncrement', 'engine', 'comment'] as $key) {
                    $tableEntity->$key = $ret->$key;
                }
                $dbs[] = $tableEntity;
                $tableEntity = new TableEntity();
                continue;
            }
        }
        fclose($fh);
        return $dbs;
    }
}
