<?php

namespace EnjoyDb;

use PDOException;

trait DbRetry
{
    /**
     * 让匿名函数中的操作连接断开重试
     */
    function retryIfDisconnect(callable $callback, $times = 3, $sleep = 0)
    {
        do {
            $times--;

            try {
                return $callback($this);
            } catch (PDOException $e) {
                if ($times <= 0 || $e->getCode() != 2006) {
                    throw $e;
                }

                if ($sleep) {
                    usleep($sleep * 1000);
                }

                $this->reconnect();
            }
        } while (true);
    }
}