<?php

use EnjoyDb\DbManager;
use EnjoyDb\Model;

require dirname(__DIR__).'/vendor/autoload.php';

$dbManager = new DbManager(function ($name) {
    return [
        'host' => '127.0.0.1',
        'database' => $name,
        'user' => 'root',
        'pwd' => 'root',
        'prefix' => 't_gw',
    ];
});
echo '<pre>';

////////////////////////////////////////////////////////////////////////////////////////////////////

$model = new class extends Model {
    protected $dbName = 'db_htreasury';
    protected $table = 'payment_info';

    protected $partition = [
        'key' => 'expected_date',
        'policy' => 'month',
    ];
};

$data = $model->table('202006')->selectRaw('id,payment_channel')->where('p_payment_no', '12312312')->all();
echo 'sql = '.$model->getLastSql().PHP_EOL;
echo 'build_sql = '.$model->table('202006')->selectRaw('id,payment_channel')
    ->where('p_payment_no', '12312312')
    ->orderBy('id', 'desc')
    ->toSql([
        'where',
        'order',
    ]).PHP_EOL;
echo 'condition_sql = '.$model->table('202006')->selectRaw('id,payment_channel')
    ->where('p_payment_no', '12312312')
    ->orderBy('id', 'desc')
    ->getCondition()->toSql().PHP_EOL;
// var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$model = new class extends Model {
    protected $dbName = 'db_hpay';
    protected $table = 'pay_flow';

    protected $partition = [
        'key' => 'b_order_no',
        'policy' => 'ring',
        'num' => 32,
        'size' => 1024,
    ];

    public function get($orderUuid)
    {
        return $this->partition($orderUuid)->first();
    }
};

$data = $model->get('10017080915401015710020023916368');
echo 'sql = '.$model->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$model = new class extends Model {
    protected $dbName = 'db_hpay';
    protected $table = 'pay_relation';

    protected $partition = [
        'key' => 'p_pay_no',
        'policy' => 'prefix',
        'length' => 6,
    ];

    public function get($orderUuid)
    {
        return $this->partition($orderUuid)->first();
    }
};

$data = $model->get('20190905172143927341test');
echo 'sql = '.$model->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

$model = new class extends Model {
    protected $dbName = 'db_hpay';
    protected $table = 'pay_relation';

    public function get($appId)
    {
        return $this->where('app_id', $appId)->limit(10)->all();
    }
};

$data = $model->get(10004);
echo 'sql = '.$model->getLastSql().PHP_EOL;
var_dump($data);
echo PHP_EOL;

////////////////////////////////////////////////////////////////////////////////////////////////////

class Fund extends Model {
    protected $prefix = false;
    protected $dbName = 'ohmyfund';
    protected $table = 'funds';
};
$fund = Fund::instance()->where('code', '000001')->first();
echo 'sql = '.Fund::instance()->getLastSql().PHP_EOL;
var_dump($fund);
echo PHP_EOL;

echo '</pre>';