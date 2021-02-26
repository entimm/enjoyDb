<?php

namespace EnjoyDb;

/**
 * 数据库遍历器
 */
class DbTravel
{
    private $builder;

    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * 按limit分片遍历表数据，并传递匿名函数处理分片数据
     */
    public function chunk(callable $callback, $count = 200)
    {
        $page = 1;

        do {
            $clone = $this->builder->deepClone();

            $results = $clone->page($page, $count)->all();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * 按id分片遍历表数据，并传递匿名函数处理分片数据
     */
    public function chunkById(callable $callback, $count = 200, $sort = 'ASC')
    {
        $lastId = null;

        do {
            $clone = $this->builder->deepClone();

            if ('ASC' === $sort) {
                $lastId = $lastId ?: 0;
                $clone->where('id', '>', $lastId)->orderBy('id');
            } else {
                $lastId = $lastId ?: PHP_INT_MAX;
                $clone->where('id', '<', $lastId)->orderBy('id', 'DESC');
            }

            $results = $clone->limit($count)->all();
            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $lastId = end($results)['id'];

            unset($results);
        } while ($countResults == $count);

        return true;
    }

    /**
     * 按limit分片遍历表数据，并传递匿名函数处理单个数据
     */
    public function each(callable $callback, $count = 1000)
    {
        return $this->chunk(function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        }, $count);
    }

    /**
     * 按id分片遍历表数据，并传递匿名函数处理单个数据
     */
    public function eachById(callable $callback, $count = 1000, $sort = 'ASC')
    {
        return $this->chunkById(function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        }, $count, $sort);
    }
}