<?php

namespace EnjoyDb\bridge;

use EnjoyDb\DB as CoreDb;

class Db
{
    /**
     * @var CoreDb
     */
    private $db;

    public function __construct(CoreDb $db)
    {
        $this->db = $db;
    }

    public function startTrans()
    {
        return $this->db->beginTransaction();
    }

    public function rollback()
    {
        return $this->db->rollBack();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function getDb()
    {
        return $this->db;
    }

    public static function __callStatic($funcName, $args)
    {
        static $dbInstances = [];

        $db = substr($funcName, 3, -8);

        $slave = !empty($args[0]);
        $name = $slave ? $db.'@slave' : $db;
        $name = strtolower($name);

        if (!isset($dbInstances[$name])) {
            $dbInstances[$name] = new self(CoreDb::connection($db, $slave));
        }

        return $dbInstances[$name];
    }
}
