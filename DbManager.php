<?php

namespace Ohmydb;

use Closure;
use PDO;

/**
 * 连接管理器
 */
class DbManager
{
    /**
     * DB实例数组
     *
     * @var DB[]
     */
    protected $connections = [];

    /**
     * 配置获取匿名函数
     *
     * @var Closure
     */
    private $configResolver;

    /**
     * 单例
     *
     * @var self
     */
    protected static $instance;

    /**
     * 默认数据库
     */
    protected static $defaultDb;

    /**
     * PDO连接选项
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    public function __construct(Closure $configResolver, $defaultDb = null)
    {
        $this->configResolver = $configResolver;

        static::$instance = $this;
        static::$defaultDb = $defaultDb;
    }

    /**
     * 连接数据库、获取数据库实例
     *
     * @return DB
     */
    public function connect($name)
    {
        $name = strtolower($name);
        if (! isset($this->connections[$name])) {
            $config = $this->config($name);
            $this->connections[$name] = new DB($config, $name);
        }

        return $this->connections[$name];
    }

    /**
     * 获取数据库配置
     */
    protected function config($name)
    {
        static $config = [];
        if (isset($config[$name])) {
            return $config[$name];
        }

        list($dbname, $slave) = $this->parseName($name);

        $config[$name] = array_merge([
            'port' => 3306,
            'charset' => 'utf8',
            'prefix' => null,
        ], array_filter(call_user_func($this->configResolver, $dbname, $slave)));

        foreach ($this->options as $k => $v) {
            $config[$name]['options'][$k] = isset($config[$name]['options'][$k]) ? $config[$name]['options'][$k] : $v;
        }

        return $config[$name];
    }

    /**
     * 解析name
     */
    protected function parseName($name)
    {
        $segments = explode('@', $name);
        $slave = isset($segments[1]) ? 'slave' === $segments[1] : false;

        return [$segments[0], $slave];
    }

    /**
     * 获取默认数据库
     */
    public static function getDefaultDb()
    {
        return static::$defaultDb;
    }

    /**
     * 全局访问db实例
     *
     * @return DB
     */
    public static function connection($db)
    {
        return self::$instance->connect($db);
    }
}
