<?php

use EnjoyDb\DB;
use EnjoyDb\DbManager;
use EnjoyDb\Raw;

require dirname(__DIR__).'/vendor/autoload.php';

$dbManager = new DbManager(function ($name) {
    return [
        'host' => '127.0.0.1',
        'database' => $name,
        'user' => 'root',
        'pwd' => 'root',
        'charset' => 'utf8',
        'prefix' => 't',
    ];
});
echo '<pre>';

$db = DB::connection('test');

////////////////////////////////////////////////////////////////////////////////////////////////////

$createTableSql = <<<EOT
CREATE TABLE IF NOT EXISTS `t_index` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `age` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_age` (`age`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
EOT;
$db->exec($createTableSql);

$affectNum = $db->exec('truncate t_index');
echo 'sql = '.$db->getLastSql().PHP_EOL;
echo 'num = '.$affectNum.PHP_EOL;
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$affectNum = $db->table('index')->insert([
    ['name' => '韩梅梅', 'age' => 20],
    ['name' => '李蕾', 'age' => 25],
    ['name' => 'jerry', 'age' => 25],
    ['name' => 'lucy', 'age' => 27],
    ['name' => 'lily', 'age' => 28],
    ['name' => '张三', 'age' => 30],
    ['name' => '李四', 'age' => 32],
    ['name' => '王五', 'age' => 32],
    ['name' => 'jim', 'age' => 32],
    ['name' => '小明', 'age' => 15],
    ['name' => '小花', 'age' => 15],
    ['name' => '小强', 'age' => 42],
    ['name' => 'zero', 'age' => 0],
    ['name' => 'one', 'age' => 1],
    ['name' => 'two', 'age' => 2],
]);
echo 'sql = '.$db->getLastSql().PHP_EOL;
echo 'num = '.$affectNum.PHP_EOL;
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$affectNum = $db->table('index')->insert(['name' => '赵丽颖', 'age' => 30]);
echo 'sql = '.$db->getLastSql().PHP_EOL;
echo 'num = '.$affectNum.PHP_EOL;
echo 'insertId = '.$db->lastInsertId().PHP_EOL;
echo PHP_EOL;

$id = $db->table('index')->insertGetId(['name' => '刘亦菲', 'age' => 32]);
echo 'sql = '.$db->getLastSql().PHP_EOL;
echo 'insertId = '.$id.PHP_EOL;
echo 'insertId = '.$db->lastInsertId().PHP_EOL;
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$affectNum = $db->table('index')->where('name', 'lily')->update(['age' => 21]);
echo 'sql = '.$db->getLastSql().PHP_EOL;
echo 'num = '.$affectNum.PHP_EOL;
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$withYoung = true;
$data = $db->table('index')
->select('name', 'age')->addSelect('id')
->where(Raw::make('age > ?', 15))
->where([
    ['age', '<', Raw::make('20 + ?', 10)],
    ['age', '>', Raw::make('20 - ?', 10)],
])->orWhere(function ($query) {
    $query->whereIn('name', ['韩梅梅', 'lucy', 'jim', '小明'])->where('age', '<', 30)
          ->whereNotBetween('age', 30, 40)
          ->whereNotLike('name', '小%');
})->orWhere(function ($query) {
    $query->where('name', 'jerry');
})->orWhere(function ($query) {
    $query->where([
        'name' => 'jim',
        'age' => 30,
    ])->orWhere(function ($query) {
        $query->where('name', 'lilei');
    });
})->orWhere(function ($query) {
    $query->where('id', '>', Raw::make('`age` - ?', 10))
          ->where([['id', '<', Raw::make('`age` + ?', 10)]])
          ->where(Raw::make('6 = 3 * 2'));
})->when($withYoung, function ($query) {
    $query->orWhere('age', '<', 20);
})
->orderBy('id', 'DESC')
->limit(10)
->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->where('age', false)->where('age', true)->first();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->where([
    ['age', 'in', [20, 25, 30]],
    ['age', 'between', [20, 30]],
    ['name', 'is not null'],
    'age' => 25
])->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->select('*')->find(10);
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->where('age', '>=', 20)->first();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->select('age', Raw::make('count(*) as count'))->groupBy('age')->having(Raw::make('count(*) > ?', 1))->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$data = $db->table('index')->forceIndex('idx_age')->select('age')->groupBy(['age', 'id'])->orderBy(['age' => 'ASC', 'id' => 'DESC'])->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$db->transaction(function (DB $db) {
    echo $db->inTransaction() ? 'in transaction' : 'not in transaction', PHP_EOL;
    $db->table('index')->insert( ['name' => 't1', 'age' => 100]);
    $db->table('index')->insert( ['name' => 't2', 'age' => 100]);
});
$result = $data = $db->table('index')->where('age', 100)->all();
var_dump($result);

try {
    $db->transaction(function (DB $db) {
        $db->table('index')->where('name', 't1')->update( ['age' => 120]);
        $db->table('index')->where('name', 't2')->update( ['age' => 120]);
        throw new Exception('error happen！');
    });
} catch (Exception $e) {
    $result = $data = $db->table('index')->whereIn('age', [100, 120])->all();
    echo $e->getMessage().PHP_EOL;
    var_dump($result);
}

////////////////////////////////////////////////////////////////////////////////////////////////////

$name = "lucy' or 1 = 1#";
$result = $db->query("SELECT * FROM `t_index` WHERE `name` = '{$name}' AND `age` = 27");
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($result);

$result = $db->tableNoPrefix('t_index')->where('name', $name)->where('age', 27)->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
var_dump($result);

////////////////////////////////////////////////////////////////////////////////////////////////////

$builder = $db->table('index');
$builder->where('age', '10')->where('name', 'test1')->orderBy('id');

$builder->deepClone()->where('age', '20')->where('name', 'test2')->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;

$builder->where('age', '30')->where('name', 'test3')->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;

echo '</pre>';
