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
        'prefix' => 't',
    ];
});
echo '<pre>';

$db = DB::connection('test');

$sql = <<<EOT
DROP TABLE IF EXISTS `t_travel`;
CREATE TABLE IF NOT EXISTS `t_travel` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `age` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_age` (`age`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `t_travel` (`name`, `age`) VALUES 
    ('Rolande', 21),
    ('Gloria', 11),
    ('Herma', 19),
    ('Eugenia', 30),
    ('Zackary', 5),
    ('Vicente', 32),
    ('Reed', 21),
    ('Lilla', 19),
    ('Trenton', 14),
    ('Shavonda', 19),
    ('Tamala', 28),
    ('Keven', 41);
EOT;
$db->exec($sql);
$builder = $db->table('travel')->whereBetween('age', 15, 21);
$count = $builder->first(Raw::make('count(*) count'));
echo 'count='.$count.PHP_EOL;

$travel = new DbTravel($builder->without('columns'));
$travel->each(function ($value, $key) {
    var_dump($value);
});

echo '</pre>';