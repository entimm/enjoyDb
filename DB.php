<?php

namespace Ohmydb;

use Closure;
use DateTimeInterface;
use Exception;
use PDO;
use PDOException;

class DB
{
    /**
     * @var PDO
     */
    private $pdo;

    private $prefix;
    private $config;
    private $name;

    /**
     * 总的重连次数
     */
    private $reconnectTimes = 0;

    /**
     * sql执行回调
     */
    private static $sqlCallback;

    /**
     * sql执行回调
     */
    private static $reconnectCallback;

    /**
     * 单次操作的最大重连次数
     */
    private static $maxReconnectTimes = 1;

    protected $sqlLog;

    /**
     * 是否事务进行中
     */
    protected $transactionLevel = 0;

    public function __construct(array $config, $name)
    {
        $this->config = $config;
        $this->prefix = isset($config['prefix']) ? $config['prefix'] : null;

        $this->pdo = $this->makePdo($this->config);

        $this->name = $name;
    }

    /**
     * 获取带前缀的table的Builder
     *
     * @return Builder
     */
    public function table($table)
    {
        if ($this->prefix) {
            $table = $this->prefix . '_' . $table;
        }

        return (new Builder($this))->from($table);
    }

    /**
     * 获取不带前缀的table的Builder
     *
     * @return Builder
     */
    public function tableNoPrefix($table)
    {
        return (new Builder($this))->from($table);
    }

    /**
     * 直接执行sql，并返回影响行数
     */
    public function exec($sql)
    {
        return $this->run(function ($sql) {
            return $this->pdo->exec($sql);
        }, $sql);
    }

    /**
     * 直接执行sql，并返回结果集
     */
    public function query($sql)
    {
        return $this->run(function ($sql) {
            $statement = $this->pdo->query($sql);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }, $sql);
    }

    public function ping()
    {
        try {
            $this->pdo->query('SELECT 1');
        } catch (PDOException $e) {
            $this->reconnect();
        }
    }

    /**
     * 参数预处理后执行DB操作
     */
    public function prepareThenExec($prepareSql, array $bindings = [])
    {
        return $this->run(function ($prepareSql, $bindings) {
            $statement = $this->pdo->prepare($prepareSql);

            foreach ($bindings as $key => $value) {
                if ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                } elseif (is_bool($value)) {
                    $value = (int) $value;
                }

                $statement->bindValue(
                    is_string($key) ? $key : $key + 1,
                    $value,
                    is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
                );
            }

            $statement->execute();

            return $statement;
        }, $prepareSql, $bindings);
    }

    protected function run(callable $callback, $sql, array $bindings = [])
    {
        $start = microtime(true);

        // 单次操作的重试次数
        $retryTimes = 0;
START:
        try {
            return $callback($sql, $bindings);
        } catch (PDOException $e) {
            if ($this->isLostConnection($e)) {
                if ($retryTimes < self::$maxReconnectTimes && !$this->transactionLevel) {
                    $retryTimes++;
                    $this->reconnect();
                    goto START;
                }
            }
            throw $e;
        } catch (Exception $e) {
            throw $e;
        } finally {
            $this->sqlLog($sql, $bindings, round((microtime(true) - $start) * 1000, 2));
        }
    }

    protected function isLostConnection(Exception $e)
    {
        return 2006 == $e->getCode() || strpos($e->getMessage(), 'MySQL server has gone away') !== false;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function getName()
    {
        return $this->name;
    }

    protected function sqlLog($sql, $bindings = [], $time = 0)
    {
        $this->sqlLog = compact('sql', 'bindings', 'time');

        if (self::$sqlCallback) {
            call_user_func(self::$sqlCallback, $this);
        }
    }

    /**
     * 获取sql执行详情
     */
    public function getSqlLog()
    {
        return $this->sqlLog;
    }

    /**
     * 获取上一条执行的sql
     */
    public function getLastSql()
    {
        $log = $this->sqlLog;

        if (! $log) {
            return null;
        }

        return Compile::replaceBindings($log['sql'], $log['bindings']);
    }

    /**
     * 匿名函数中执行事务
     *
     * @throws Exception
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();
            } catch (Exception $e) {
                $this->rollBack();
                if ($this->isLostConnection($e)) {
                    $this->reconnect();
                    continue;
                }
                throw $e;
            }

            return $result;
        }
    }

    /**
     * 获取上条插入id
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 重连数据库
     */
    public function reconnect()
    {
        $this->reconnectTimes++;

        $this->disconnect();

        if (self::$reconnectCallback) {
            call_user_func(self::$reconnectCallback, $this);
        }

        $this->pdo = $this->makePdo($this->config);

        return $this;
    }

    public function getReconnectTimes()
    {
        return $this->reconnectTimes;
    }

    /**
     * 断开连接
     */
    public function disconnect()
    {
        $this->pdo = null;
    }

    /**
     * 生成PDO
     */
    private function makePdo($config)
    {
        return new PDO($this->getHostDsn($config), $config['user'], $config['pwd'], $config['options']);
    }

    /**
     * 获取数据库连接所用的Dsn
     */
    private function getHostDsn(array $config)
    {
        return isset($config['port'])
            ? "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}"
            : "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
    }

    public function beginTransaction()
    {
        $this->transactionLevel++;

        $this->sqlLog('transaction-begin-'.$this->transactionLevel);

        return 1 === $this->transactionLevel ? $this->pdo->beginTransaction() : true;
    }

    public function commit()
    {
        $this->sqlLog('transaction-commit-'.$this->transactionLevel);

        $ret = false;
        if (1 === $this->transactionLevel) {
            $ret = $this->pdo->commit();
        }

        $this->transactionLevel = max(0, $this->transactionLevel - 1);

        return $ret;
    }

    public function rollBack()
    {
        if ($this->transactionLevel) {
            $this->sqlLog('transaction-rollback-'.$this->transactionLevel);
            $this->transactionLevel = 0;

            return $this->pdo->rollBack();
        }

        return false;
    }

    /**
     * 其他方法走PDO
     */
    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->pdo, $method), $arguments);
    }

    /**
     * 全局访问db实例
     *
     * @return DB
     */
    public static function connection($name = null, $slave = false)
    {
        $name = $name ?: DbManager::getDefaultDb();
        $name = $slave ? $name.'@slave' : $name;

        return DbManager::connection($name);
    }

    /**
     * 全局访问从库db实例
     *
     * @return DB
     */
    public static function slaveConnection($name = null)
    {
        return DB::connection($name, true);
    }

    /**
     * sql执行回调注册
     */
    public static function registerSqlCallback(callable $callback)
    {
        self::$sqlCallback = $callback;
    }

    /**
     * 数据库重连执行回调注册
     */
    public static function registerReconnectCallback(callable $callback)
    {
        self::$reconnectCallback = $callback;
    }

    /**
     * 设置数据库重连最大次数
     */
    public static function setMaxReconnectTimes($times)
    {
        self::$maxReconnectTimes = $times;
    }
}