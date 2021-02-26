<?php

use EnjoyDb\DB;
use EnjoyDb\DbManager;
use EnjoyDb\DbTravel;
use EnjoyDb\Raw;

require dirname(__DIR__).'/vendor/autoload.php';

$dbManager = new DbManager(function ($name) {
    return [
        'host' => '127.0.0.1',
        'database' => $name,
        'user' => 'root',
        'pwd' => 'root',
    ];
});
echo '<pre>';

$db = DB::connection('ohmystock');

$builder = $db->table('base_infos')->whereBetween('pe', 1, 10);
$count = $builder->first(Raw::make('count(*) count'));
echo 'count='.$count.PHP_EOL;

$travel = new DbTravel($builder->without('columns'));
$travel->each(function ($value, $key) {
    var_dump($value);
});