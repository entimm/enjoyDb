<?php

namespace Ohmydb;

/**
 * 可绑定的数据原始sql
 */
class Raw
{
    protected $value;
    protected $bindings;

    public function __construct($value, $bindings = [])
    {
        $this->value = $value;
        $this->bindings = $bindings;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    public function __toString()
    {
        return (string) $this->getValue();
    }

    public static function make($value, ...$bindings)
    {
        return new static($value, $bindings);
    }

}