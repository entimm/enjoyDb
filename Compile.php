<?php

namespace Ohmydb;

/**
 * 编译组装sql
 */
class Compile
{
    private $bindings = [];

    protected $queryComponents = [
        'select' => 'columns',
        'from' => 'table',
        'forceIndex' => 'forceIndex',
        'where' => 'condition',
        'group' => 'groups',
        'having' => 'having',
        'order' => 'orders',
        'limit' => 'limit',
        'offset' => 'offset',
        'lock' => 'lock',
    ];

    protected $updateComponents = [
        'update' => 'table',
        'set' => 'data',
        'where' => 'condition',
    ];

    protected $insertComponents = [
        'insert' => 'table',
        'columnRecords' => 'data',
    ];

    protected $replaceComponents = [
        'replace' => 'table',
        'columnRecords' => 'data',
    ];

    protected $deleteComponents = [
        'delete' => 'table',
        'where' => 'condition',
    ];

    /**
     * 解析查询语句
     */
    public function resolveQuery(Builder $builder)
    {
        return $this->resolve($this->queryComponents, $builder);
    }

    /**
     * 解析更新语句
     */
    public function resolveUpdate(Builder $builder)
    {
        return $this->resolve($this->updateComponents, $builder);
    }

    /**
     * 解析删除语句
     */
    public function resolveDelete(Builder $builder)
    {
        return $this->resolve($this->deleteComponents, $builder);
    }

    /**
     * 解析插入语句
     */
    public function resolveInsert(Builder $builder)
    {
        return $this->resolve($this->insertComponents, $builder);
    }

    /**
     * 解析替换语句
     */
    public function resolveReplace(Builder $builder)
    {
        return $this->resolve($this->replaceComponents, $builder);
    }

    /**
     * 核心解析方法
     */
    protected function resolve(array $components, Builder $builder)
    {
        $segments = [];
        foreach ($components as $method => $name) {
            $componentValue = $builder->component($name);
            if (null === $componentValue) {
                continue;
            }

            $segments[] = $this->$method($componentValue);
        }

        return $this->implode($segments);
    }

    /**
     * 获取绑定参数
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * 编译select
     */
    protected function select(array $component)
    {
        return 'SELECT '.implode(', ', array_map(function($item) {
            if ('*' === $item) {
                return $item;
            }

            return $this->columnize($item);

        }, $component));
    }

    /**
     * 编译from
     */
    protected function from($component)
    {
        return 'FROM '.$this->columnize($component);
    }

    /**
     * 编译force index
     */
    protected function forceIndex($component)
    {
        return 'FORCE INDEX ('.$this->columnize($component).')';
    }

    /**
     * 编译where
     */
    protected function where(Condition $component)
    {
        list($condition, $bindings) = $component->resolve();

        $this->bindings = array_merge($this->bindings, $bindings);

        return $condition ? 'WHERE '.$condition : '';
    }

    /**
     * 编译group
     */
    protected function group(array $component)
    {
        return 'GROUP BY '.implode(', ', array_map(function($item) {
                return $this->columnize($item);
            }, $component));
    }

    /**
     * 编译having
     */
    protected function having(Raw $component)
    {
        $this->bindings = array_merge($this->bindings, $component->getBindings());

        return 'HAVING '.$component;
    }

    /**
     * 编译order by
     */
    protected function order(array $component)
    {
        $orders = array_map(function ($order) {
            return $this->columnize($order['column']).' '.$order['direction'];
        }, $component);

        return 'ORDER BY '.implode(', ', $orders);
    }

    /**
     * 编译limit
     */
    protected function limit($component)
    {
        return 'LIMIT '.(int) $component;
    }

    /**
     * 编译offset
     */
    protected function offset($component)
    {
        return 'OFFSET '.(int) $component;
    }

    /**
     * 编译lock
     */
    protected function lock($value)
    {
        if (! is_string($value)) {
            return $value ? 'for update' : 'lock in share mode';
        }

        return $value;
    }

    /**
     * 编译update
     */
    protected function update($component)
    {
        return 'UPDATE '.$this->columnize($component);
    }

    /**
     * 编译set
     */
    protected function set($component)
    {
        array_walk($component, function (&$value, $key) {
            $value = $this->columnize($key).' = '.$this->parameterize($value);
        });
        $sets = implode(', ', $component);

        return 'SET '.$sets;
    }

    /**
     * 编译delete
     */
    protected function delete($component)
    {
        return 'DELETE FROM '.$this->columnize($component);
    }

    /**
     * 编译insert
     */
    protected function insert($component)
    {
        return 'INSERT INTO '.$this->columnize($component);
    }

    /**
     * 编译replace
     */
    protected function replace($component)
    {
        return 'REPLACE INTO '.$this->columnize($component);
    }

    /**
     * 编译insert\replace的列值部分
     */
    protected function columnRecords($component)
    {
        if (! is_array(reset($component))) {
            $component = [$component];
        } else {
            // 保证键值的顺序一致
            foreach ($component as $key => $value) {
                ksort($value);

                $component[$key] = $value;
            }
        }

        $columns = implode(', ', array_map(function($item) {
            return $this->columnize($item);
        }, array_keys(reset($component))));

        $parameters = array_map(function ($record) {
            return $this->parameterize($record);
        }, $component);
        $parameters = implode(',', $parameters);

        return "($columns) values $parameters";
    }

    /**
     * 合并组件
     */
    protected function implode($segments)
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * 列名添加反引号
     */
    protected function columnize($column)
    {
        if ($column instanceof Raw) {
            return $column->getValue();
        }

        return "`$column`";
    }

    /**
     * 参数值处理
     */
    protected function parameterize($value)
    {
        if (is_array($value)) {
            $values = implode(', ', array_map([$this, __FUNCTION__], $value));

            return "({$values})";
        }

        if ($value instanceof Raw) {
            $this->bindings = array_merge($this->bindings, $value->getBindings());

            return $value->getValue();
        }

        $this->bindings[] = $value;

        return '?';
    }
}