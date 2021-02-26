<?php

namespace EnjoyDb;

/**
 * 条件的组成部件，可自我嵌套
 */
class Element
{
    private $type;

    /**
     * @var array[]|static[]|Raw
     */
    private $element;
    private $logic;
    private $bindings;

    public function __construct($element, $type, $logic = 'AND')
    {
        $this->element = $element;
        $this->type = $type;
        $this->logic = $logic;
        $this->bindings = [];
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * 根据条件部件类型路由解析算法
     */
    public function resolve()
    {
        /**
         * 防止克隆时出现bug
         */
        $this->bindings = [];

        $method = 'resolve'.ucfirst($this->type);

        return $this->$method();
    }

    /**
     * 解析原始sql块
     */
    protected function resolveRaw()
    {
        if ($this->element->getBindings()) {
            $this->bindings = $this->element->getBindings();
        }

        return $this->logic.' '.$this->element;
    }

    /**
     * 解析二维数组构成的组合
     */
    protected function resolveMultiple()
    {
        $arr = [];
        foreach ($this->element as $item) {
            $arr[] = $this->compile($item);
        }

        return $this->logic.' '.implode(' AND ', $arr);
    }

    /**
     * 解析嵌套组合，这个时候，$this->element中的项都是Element对象, 将嵌套
     */
    protected function resolveComplex()
    {
        $segments = [];
        $bindings = [[]];
        foreach ($this->element as $item) {
            $segments[] = $item->resolve();
            $bindings[] = $item->getBindings();
        }
        $this->bindings = array_merge(...$bindings);

        return $this->logic.' ('.preg_replace('/AND |OR /i', '', implode(' ', $segments), 1).')';
    }

    /**
     * 最基础的解析
     */
    protected function resolveSingle()
    {
        return $this->logic.' '.$this->compile($this->element);
    }

    /**
     * 编译条件部件
     */
    protected function compile(array $element)
    {
        $element['operator'] = strtoupper($element['operator']);
        $arr = explode(' ', $element['operator']);
        $type = end($arr);

        if ($type === 'BETWEEN') {
            return $this->compileBetween($element['column'], $element['operator'], $element['value']);
        }
        if ($type === 'NULL') {
            return $this->compileNull($element['column'], $element['operator']);
        }

        return $this->compileBase($element['column'], $element['operator'], $element['value']);
    }

    /**
     * 编译基础条件
     */
    protected function compileBase($column, $operator, $value)
    {
        return "`$column`".' '.$operator.' '.$this->parameterize($value);
    }

    /**
     * 编译between条件
     */
    protected function compileBetween($column, $operator, $value)
    {
        return "`$column`".' '.$operator.' '.$this->parameterize($value[0]).' AND '.$this->parameterize($value[1]);
    }

    /**
     * 编译null条件
     */
    protected function compileNull($column, $operator)
    {
        return "`$column`".' '.$operator;
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