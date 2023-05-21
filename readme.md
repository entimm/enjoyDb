# EnjoyDb

EnjoyDb 是一个 PHP 数据库操作库，提供了 SQL 构造器和 PDO 封装，支持链式调用和参数绑定，让数据库操作更加简单和安全。

该组件包含以下重要组件：

- Builder：用于构建 SQL 查询的构建器。
- Compile：用于将查询条件编译成 SQL 语句。
- Condition：用于构建查询条件的条件对象。
- DB：数据库连接对象，用于执行数据库操作。
- DbManager：管理数据库连接的对象。
- DbRetry：重试执行数据库操作的对象。
- DbTravel：遍历数据库记录的对象。
- Element：构建 SQL 语句的元素对象。
- Model：基于数据库表的模型对象，提供了便捷的查询方法。
- Raw：用于构建原始 SQL 片段的对象。

## 功能和特点

- 提供了 Builder 类，可以方便地构造 SQL 语句，支持链式调用和参数绑定。
- 提供了 DB 类，封装了 PDO 的常用操作，包括查询、插入、更新、删除等。
- 支持事务和连接池，可以提高数据库操作的性能和可靠性。
- 代码简洁、易于扩展和维护，适合用于中小型项目的数据库操作。

## 快速上手

```php
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
$db = DB::connection('test');

$affectNum = $db->table('index')->insert([
    ['name' => 'jerry', 'age' => 25],
    ['name' => 'lucy', 'age' => 27],
    ['name' => 'lily', 'age' => 28],
]);

$affectNum = $db->table('index')->where('name', 'lily')->update(['age' => 21]);

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

$data = $db->table('index')->where([
    ['age', 'in', [20, 25, 30]],
    ['age', 'between', [20, 30]],
    ['name', 'is not null'],
    'age' => 25
])->all();

$db->table('index')->select('age', Raw::make('count(*) as count'))->groupBy('age')->having(Raw::make('count(*) > ?', 1))->all();

$db->transaction(function (DB $db) {
    echo $db->inTransaction() ? 'in transaction' : 'not in transaction', PHP_EOL;
    $db->table('index')->insert( ['name' => 't1', 'age' => 100]);
    $db->table('index')->insert( ['name' => 't2', 'age' => 100]);
});
$result = $data = $db->table('index')->where('age', 100)->all();

$builder = $db->table('index');
$builder->where('age', '10')->where('name', 'test1')->orderBy('id');

$builder->deepClone()->where('age', '20')->where('name', 'test2')->all();
echo 'sql = '.$db->getLastSql().PHP_EOL;
```

## 更多示例代码

sql构建查询: [/example/index.php](/example/index.php)

Model使用: [/example/model.php](/example/model.php)

数据遍历: [/example/travel.php](/example/travel.php)