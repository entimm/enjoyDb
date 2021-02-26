<?php

namespace Ohmydb;

use Exception;

/**
 * @method Builder select($columns)
 * @method Builder addSelect($column)
 * @method Builder from($table)
 * @method Builder forceIndex($indexName)
 * @method Builder where($column, $operator = null, $value = null, $logic = 'AND')
 * @method Builder whereIn($column, array $value)
 * @method Builder whereNotIn($column, array $value)
 * @method Builder whereBetween($column, $start, $end)
 * @method Builder whereNotBetween($column, $start, $end)
 * @method Builder whereNull($column)
 * @method Builder whereNotNull($column)
 * @method Builder whereLike($column, $value)
 * @method Builder whereNotLike($column, $value)
 * @method Builder orWhere($column, $operator = null, $value = null)
 * @method Builder groupBy(...$groups)
 * @method Builder having(Raw $raw)
 * @method Builder orderBy($column, $direction = 'ASC')
 * @method Builder offset($value)
 * @method Builder limit($value)
 * @method Builder page($page, $pageSize = 20)
 * @method Builder lockForUpdate()
 * @method Builder sharedLock()
 * @method increment($column, $amount = 1, array $extra = [])
 * @method decrement($column, $amount = 1, array $extra = [])
 * @method Builder when($value, callable $callback, callable $default = null)
 * @method first($columns = null)
 * @method find($id)
 * @method all()
 * @method delete()
 * @method update($data)
 * @method insert($data)
 * @method insertGetId(array $data)
 * @method replace($data)
 * @method component($name)
 * @method Builder setWhere(Condition $condition)
 * @method Builder without($components)
 * @method Builder cloneWithout($components)
 * @method Builder fillThat(self $builder)
 * @method Builder fresh()
 * @method Builder whereRaw($raw)
 * @method Builder selectRaw($raw)
 *
 * @see Builder
 */
class Model
{
    protected $dbName;
    protected $table;
    protected $prefix;

    /**
     * @var array
     */
    protected $partition;

    /**
     * @var DB
     */
    protected $db;

    /**
     * 参数控制主从读写
     */
    public function __construct($slave = false)
    {
        $this->dbName = $this->dbName ?: DbManager::getDefaultDb();

        $this->db = DB::connection($this->dbName, $slave);

        $this->table = $this->table ?: strtolower(trim(preg_replace("/[A-Z]/", "_\\0", static::class), "_"));
    }

    /**
     * 获取基于自定义表前缀的Builder, 可传value选择分表
     *
     * @return Builder
     */
    public function table($value = null)
    {
        return $this->tableWithPrefix($this->getTable($value));
    }

    /**
     * 根据分区value选定特定table，并根据value限制条件，最后得到Builder
     *
     * @return Builder
     */
    public function partition($value)
    {
        return $this->tableWithPrefix($this->getTable($value))->where($this->partition['key'], $value);
    }

    /**
     * 最近查询语句
     */
    public function getLastSql()
    {
        return $this->db->getLastSql();
    }

    /**
     * 获取基于自定义表前缀的Builder
     *
     * @return Builder
     */
    private function tableWithPrefix($table = null)
    {
        $table = $table ?: $this->table;

        if ($this->prefix) {
            return $this->db->tableNoPrefix($this->prefix.'_'.$table);
        }

        if (false === $this->prefix) {
            return $this->db->tableNoPrefix($table);
        }

        return $this->db->table($table);
    }

    /**
     * 获取模型表名
     */
    public function getTable($value = null)
    {
        /**
         * 按策略来路由分表
         */
        if ($this->partition) {
            $method = 'partition'.ucfirst($this->partition['policy']);
            $suffix = $this->$method($value);

            return "{$this->table}_{$suffix}";
        }

        return $this->table;
    }

    /**
     * db name
     */
    public function getDbName()
    {
        return $this->dbName;
    }

    /**
     * db
     *
     * @return DB
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * 重连数据库
     */
    public function reconnect()
    {
        $this->db->reconnect();

        return $this;
    }

    /**
     * 按环分表
     */
    protected function partitionRing($value)
    {
        $mod = sprintf('%u', crc32($value)) % $this->partition['size'];
        $range = $this->partition['size'] / $this->partition['num'];
        $tableNo = floor($mod / $range);

        return $tableNo * $range;
    }

    /**
     * 按模分表
     */
    protected function partitionMod($value)
    {
        $mod = $value % $this->partition['num'];

        return str_pad($mod, strlen($this->partition['num']), '0', STR_PAD_LEFT);
    }

    /**
     * 按月分表
     */
    protected function partitionMonth($value)
    {
        return date('Ym', strtotime($value));
    }

    /**
     * 截止固定前缀长度分表
     */
    protected function partitionPrefix($value)
    {
        return substr($value, 0, $this->partition['length']);
    }

    /**
     * 其他方法走Builder
     *
     * @throws Exception
     */
    public function __call($method, $arguments)
    {
        if ($this->partition) {
            throw new Exception('partition method not called!');
        }

        return call_user_func_array(array($this->table(), $method), $arguments);
    }

    /**
     * @return static
     */
    public static function instance($slave = false)
    {
        static $instances = [];

        $key = (int)$slave;
        if (!isset($instances[$key])) {
            $instances[$key] = new static($slave);
        }

        return $instances[$key];
    }
}