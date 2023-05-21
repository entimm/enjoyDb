<?php

/**
 * 使用model示例
 */

use EnjoyDb\DB;
use EnjoyDb\DbManager;
use EnjoyDb\Model;

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

$sql = <<<EOT
DROP TABLE IF EXISTS `t_partition_logs_202002`;
CREATE TABLE IF NOT EXISTS `t_partition_logs_202002` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `code` varchar(255) NOT NULL DEFAULT '',
  `price` int(10) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `t_partition_logs_202002` (`date`, `code`, `price`) VALUES 
    ('2020-02-21', 'sh600519', '96.45'),
    ('2020-02-26', 'sh600276', '85.10'),
    ('2020-02-28', 'sz000661', '73.56'),
    ('2020-02-24', 'sh603087', '97.55'),
    ('2020-02-14', 'sh600436', '70.83'),
    ('2020-02-07', 'sh601888', '66.73'),
    ('2020-02-11', 'sz002594', '64.87'),
    ('2020-02-12', 'sz300750', '50.87'),
    ('2020-02-25', 'sh601012', '93.47'),
    ('2020-02-12', 'sz300274', '85.98'),
    ('2020-02-15', 'sh600438', '54.81'),
    ('2020-02-17', 'sz002714', '12.63'),
    ('2020-02-09', 'sh601899', '37.20'),
    ('2020-02-05', 'sz000333', '72.28'),
    ('2020-02-08', 'sh601318', '78.23');

DROP TABLE IF EXISTS `t_funds`;
CREATE TABLE IF NOT EXISTS `t_funds` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `t_funds` (`code`, `name`) VALUES 
    ('sh600519', '贵州茅台'),
    ('sh600276', '恒瑞医药'),
    ('sz000661', '长春高新'),
    ('sh603087', '甘李药业'),
    ('sh600436', '片仔癀'),
    ('sh601888', '中国中免'),
    ('sz002594', '比亚迪'),
    ('sz300750', '宁德时代'),
    ('sh601012', '隆基股份'),
    ('sz300274', '阳光电源'),
    ('sh600438', '通威股份'),
    ('sz002714', '牧原股份'),
    ('sh601899', '紫金矿业'),
    ('sz000333', '美的集团'),
    ('sh601318', '中国平安');
EOT;
DB::connection('test')->exec($sql);
////////////////////////////////////////////////////////////////////////////////////////////////////

$model = new class extends Model {
    protected $dbName = 'test';
    protected $table = 'partition_logs';

    protected $partition = [
        'key' => 'date',
        'policy' => 'month',
    ];

    public function get($date)
    {
        return $this->partition($date)->all();
    }
};

$data = $model->table('202002')->selectRaw('id,code')->where('code', 'sh600519')->all();
echo 'sql = '.$model->getLastSql().PHP_EOL;
echo 'build_sql = '.$model->table('202002')->selectRaw('id,code')
    ->where('code', 'sh600519')
    ->orderBy('id', 'desc')
    ->toSql([
        'where',
        'order',
    ]).PHP_EOL;
echo 'condition_sql = '.$model->table('202002')->selectRaw('id,code')
    ->where('code', 'sh600519')
    ->orderBy('id', 'desc')
    ->getCondition()->toSql().PHP_EOL;
print_r($model->get('2020-02-12')).PHP_EOL;
echo 'sql = '.$model->getLastSql().PHP_EOL;
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

class Fund extends Model {
    protected $dbName = 'test';
    protected $table = 'funds';

    public function get($code)
    {
        return $this->where('code', $code)->limit(3)->all();
    }
};
$fund = Fund::instance()->where('code', 'sh600519')->first();
echo 'sql = '.Fund::instance()->getLastSql().PHP_EOL;
var_dump($fund);
echo PHP_EOL;

$data = Fund::instance()->get('sh600519');
echo 'sql = '.Fund::instance()->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

echo '</pre>';