<?php

namespace Ohmydb;

use InvalidArgumentException;
use PDO;

/**
 * sql构造器
 */
class Builder
{
    private $columns;
    private $table;
    private $forceIndex;
    private $condition;
    private $groups;
    private $having;
    private $orders;
    private $limit;
    private $offset;
    private $lock;

    private $data;

    private $db;

    public function __construct(DB $db = null)
    {
        $this->condition = new Condition;
        $this->db = $db;
    }

    /**
     * 指定select项
     */
    public function select($columns)
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * 原生的select
     */
    public function selectRaw($raw)
    {
        return $this->select(Raw::make($raw));
    }

    /**
     * 追加select项
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge($this->columns, $column);

        return $this;
    }

    /**
     * 指定table
     */
    public function from($table)
    {
        $this->table = $table;

        return $this;
    }

    /**
     * 强制索引
     */
    public function forceIndex($indexName)
    {
        $this->forceIndex = $indexName;

        return $this;
    }

    /**
     * 强大的where添加
     */
    public function where($column, $operator = null, $value = null, $logic = 'AND')
    {
        if (is_callable($column)) {
            call_user_func($column, $newBuilder = new static);
            $this->condition->add($newBuilder->condition, null, null, $logic);

            return $this;
        }

        $this->condition->add($column, $operator, $value, $logic);

        return $this;
    }

    /**
     * 原生的where
     */
    public function whereRaw($raw)
    {
        return $this->where(Raw::make($raw));
    }

    /**
     * where in 条件
     */
    public function whereIn($column, array $value)
    {
        $this->condition->add($column, 'IN', $value);

        return $this;
    }

    /**
     * where not in 条件
     */
    public function whereNotIn($column, array $value)
    {
        $this->condition->add($column, 'NOT IN', $value);

        return $this;
    }

    /**
     * where between 条件
     */
    public function whereBetween($column, $start, $end)
    {
        $this->condition->add($column, 'BETWEEN', [$start, $end]);

        return $this;
    }

    /**
     * where not between 条件
     */
    public function whereNotBetween($column, $start, $end)
    {
        $this->condition->add($column, 'NOT BETWEEN', [$start, $end]);

        return $this;
    }

    /**
     * where null 条件
     */
    public function whereNull($column)
    {
        $this->condition->add($column, 'IS NULL', false);

        return $this;
    }

    /**
     * where not null 条件
     */
    public function whereNotNull($column)
    {
        $this->condition->add($column, 'IS NOT NULL', false);

        return $this;
    }

    /**
     * where like 条件
     */
    public function whereLike($column, $value)
    {
        $this->condition->add($column, 'LIKE', $value);

        return $this;
    }

    /**
     * where not like 条件
     */
    public function whereNotLike($column, $value)
    {
        $this->condition->add($column, 'NOT LIKE', $value);

        return $this;
    }

    /**
     * or where 条件
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        $this->where($column, $operator, $value, $logic = 'OR');

        return $this;
    }

    /**
     * group by(可传多个参数)
     */
    public function groupBy(...$groups)
    {
        $this->groups = is_array(reset($groups)) ? reset($groups) : $groups;

        return $this;
    }

    /**
     * having（这里只能通过Raw方式传参）
     */
    public function having(Raw $raw)
    {
        $this->having = $raw;

        return $this;
    }

    /**
     * order by(可传递一个array[]多重排序)
     */
    public function orderBy($column, $direction = 'ASC')
    {
        if (! is_array($column)) {
            $column = [$column => $direction];
        }

        $order = [];
        foreach ($column as $key => $value) {
            $value = strtoupper($value);
            if (! in_array($value, ['ASC', 'DESC'], true)) {
                throw new InvalidArgumentException('Order direction must be "ASC" or "DESC".');
            }

            $order[] = [
                'column' => $key,
                'direction' => $value,
            ];
        }

        $this->orders = $order;

        return $this;
    }

    /**
     * 分页起始
     */
    public function offset($value)
    {
        $this->offset = max(0, $value);

        return $this;
    }

    /**
     * 分页大小
     */
    public function limit($value)
    {
        if ($value >= 0) {
            $this->limit = $value;
        }

        return $this;
    }

    /**
     * 分页
     */
    public function page($page, $pageSize = 20)
    {
        return $this->offset(($page - 1) * $pageSize)->limit($pageSize);
    }

    /**
     * 锁
     */
    protected function lock($value = true)
    {
        $this->lock = $value;

        return $this;
    }

    /**
     * 独占锁
     */
    public function lockForUpdate()
    {
        return $this->lock(true);
    }

    /**
     * 共享锁
     */
    public function sharedLock()
    {
        return $this->lock(false);
    }

    /**
     * 字段自增
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to increment method.');
        }

        $raw = Raw::make("`$column` + $amount");
        $data = array_merge([$column => $raw], $extra);

        return $this->update($data);
    }

    /**
     * 字段自减
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Non-numeric value passed to decrement method.');
        }

        $raw = Raw::make("`$column` - $amount");
        $data = array_merge([$column => $raw], $extra);

        return $this->update($data);
    }

    /**
     * $value为真时才执行$callback中的查询
     */
    public function when($value, callable $callback, callable $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }

        if ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * 获取一条记录（可直接获取其中的一个字段的值）
     */
    public function first($columns = null)
    {
        if ($columns) {
            $this->select($columns);
        }
        $all = $this->limit(1)->all();

        $record = (array)reset($all);

        return count($record) > 1 ? $record : reset($record);
    }

    /**
     * 根据id获取记录（可获取多个）
     */
    public function find($id)
    {
        if (is_array($id)) {
            $this->whereIn('id', $id)->all();
        }

        return $this->where('id', $id)->first();
    }

    /**
     * 查询获取记录数组
     */
    public function all()
    {
        $compile = new Compile;

        $statement = $this->db->prepareThenExec($compile->resolveQuery($this), $compile->getBindings());

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 执行删除
     *
     * @return int 影响函数
     */
    public function delete()
    {
        $compile = new Compile;

        $statement = $this->db->prepareThenExec($compile->resolveDelete($this), $compile->getBindings());

        return $statement->rowCount();
    }

    /**
     * 执行更新
     *
     * @return int 影响函数
     */
    public function update(array $data)
    {
        $this->data = $data;

        $compile = new Compile;

        $statement = $this->db->prepareThenExec($compile->resolveUpdate($this), $compile->getBindings());

        return $statement->rowCount();
    }

    /**
     * 执行插入(可批量)
     *
     * @return int 影响函数
     */
    public function insert(array $data)
    {
        $this->data = $data;

        $compile = new Compile;

        $statement = $this->db->prepareThenExec($compile->resolveInsert($this), $compile->getBindings());

        return $statement->rowCount();
    }

    /**
     * 执行插入并获取插入ID
     *
     * @return int 影响函数
     */
    public function insertGetId(array $data)
    {
        $this->data = $data;

        $compile = new Compile;

        $this->db->prepareThenExec($compile->resolveInsert($this), $compile->getBindings());

        return $this->db->lastInsertId();
    }

    /**
     * 执行替换
     *
     * @return int 影响函数
     */
    public function replace(array $data)
    {
        $this->data = $data;

        $compile = new Compile;

        $statement = $this->db->prepareThenExec($compile->resolveReplace($this), $compile->getBindings());

        return $statement->rowCount();
    }

    /**
     * 获取组件
     */
    public function component($name)
    {
        if ('columns' === $name && null === $this->$name) {
            return ['*'];
        }

        return isset($this->$name) ? $this->$name : null;
    }

    /**
     * 从外部注入替换where组件
     */
    public function setWhere(Condition $condition)
    {
        $this->where = $condition;

        return $this;
    }

    /**
     * 忽略部分组件数据
     */
    public function without($components)
    {
        $components = (array) $components;

        foreach ($components as $component) {
            switch ($component) {
                case 'condition':
                    $this->condition = new Condition;
                    break;
                default:
                    $this->$component = null;
            }
        }

        return $this;
    }

    /**
     * 完全克隆
     */
    public function deepClone()
    {
        $clone = clone $this;

        $clone->condition = clone $this->condition;

        return $clone;
    }

    /**
     * 使用现有Builder填充另一个Builder
     *
     * @return Builder
     */
    public function fillThat(self $builder)
    {
        $builder->db = $this->db;
        $builder->table = $this->table;

        return $builder;
    }

    /**
     * 获得统一DB实例的全新的Builder
     *
     * @return Builder
     */
    public function fresh()
    {
        return (new self($this->db))->from($this->table);
    }

    /**
     * 转换成遍历对象
     *
     * @return DbTravel
     */
    public function travel()
    {
        return new DbTravel($this);
    }
}