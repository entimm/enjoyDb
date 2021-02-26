<?php

namespace Ohmydb;

/**
 * 条件构造器，可脱离Builder单独使用（只构造条件）
 */
class Condition
{
    /**
     * @var Element[]
     */
    private $elements = [];

    /**
     * 强大的条件添加
     */
    public function add($column, $operator = null, $value = null, $logic = 'AND')
    {
        if ($column instanceof Raw) {
            $this->elements[] = new Element($column, 'raw', $logic);

            return $this;
        }

        if (is_callable($column)) {
            call_user_func($column, $newCondition = new static);
            $this->elements[] = new Element($newCondition->elements, 'complex', $logic);

            return $this;
        }

        if ($column instanceof self) {
            $this->elements[] = new Element($column->elements, 'complex', $logic);

            return $this;
        }

        if (is_array($column)) {
            // 空数组不做操作
            if (!$column) {
                return $this;
            }

            $multi = [];
            foreach ($column as $k => $v) {
                if (is_numeric($k)) {
                    $multi[] = [
                        'column' => $v[0],
                        'operator' => isset($v[1]) ? $v[1] : null,
                        'value' => isset($v[2]) ? $v[2] : null,
                    ];
                } else {
                    $multi[] = [
                        'column' => $k,
                        'operator' => '=',
                        'value' => $v,
                    ];
                }
            }

            $this->elements[] = new Element($multi, 'multiple', $logic);

            return $this;
        }

        if (null === $value) {
            list($operator, $value) = ['=', $operator];
        }

        $this->elements[] = new Element([
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ], 'single', $logic);

        return $this;
    }

    /**
     * 添加and条件
     */
    public function addAnd($column, $operator = null, $value = null)
    {
        $this->add($column, $operator, $value);

        return $this;
    }

    /**
     * 添加or条件
     */
    public function addOr($column, $operator = null, $value = null)
    {
        $this->add($column, $operator, $value, 'OR');

        return $this;
    }

    /**
     * 解析自身
     */
    public function resolve()
    {
        $segments = [];
        $bindings = [[]];
        foreach ($this->elements as $item) {
            $segments[] = $item->resolve();
            $bindings[] = $item->getBindings();
        }
        $bindings = array_merge(...$bindings);

        return [
            preg_replace('/AND |OR /i', '', implode(' ', $segments), 1),
            $bindings,
        ];
    }
}