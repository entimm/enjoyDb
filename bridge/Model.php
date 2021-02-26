<?php

namespace EnjoyDb\bridge;

use Exception;
use EnjoyDb\Model as Base;

/**
 * 所有model的基类
 */
class Model extends Base
{
    private $fields = '*';

    public function select($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * 获取结果集
     */
    public function find($condition, $mapping = null)
    {
        return $this->tableByMapping($mapping)->selectRaw($this->fields)->whereRaw($condition)->all();
    }

    /**
     * 获取单条结果
     */
    public function findOne($condition, $mapping = null)
    {
        $all =  $this->tableByMapping($mapping)->selectRaw($this->fields)->whereRaw($condition)->all();

        return reset($all);
    }

    /**
     * 新增或更新数据
     */
    public function save($data, $where = '', $mapping = null)
    {
        if ($where) {
            return $this->tableByMapping($mapping)->whereRaw($where)->update($data);
        }

        return $this->tableByMapping($mapping)->insertGetId($data);
    }

    /**
     * 删除数据
     */
    public function delete($condition, $mapping = false)
    {
        return $this->tableByMapping($mapping)->whereRaw($condition)->delete();
    }

    /**
     * 选择分表
     */
    protected function tableByMapping($mapping)
    {
        $value = null;
        if ($this->partition) {
            $value = $mapping[$this->partition['key']];
        }

        return $this->table($value);
    }

    /**
     * 关联查询的id 转换  转换成in 用的字符串
     * @throws Exception
     */
    public function praseIdsToQueryString($rows, $key)
    {
        $idsArr = [];
        foreach ($rows as $row) {
            if (!isset($row[$key])) {
                throw new Exception('错误的数据结构');
            }
            $idsArr[] = "'$row[$key]'";
        }
        return implode(',', $idsArr);
    }

    public function startTrans()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollback();
    }
}
