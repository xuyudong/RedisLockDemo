<?php
/**
 * Created by PhpStorm.
 * User: dongisking
 * Date: 2019/4/6
 * Time: 22:54
 */
include "vendor/autoload.php";

class test_redis
{
    public static function getRedisConn()
    {
        $objRedis = new \Predis\Client();
        $objRedis->connect(['scheme' => 'tcp',
            'host'   => '10.0.0.1',
            'port'   => 6379,]);
        return $objRedis;
    }

}
$redis_client = test_redis::getRedisConn();
$redis_client->set('name','xyd2235','EX',30,'NX');
//$redis_client->incr('age');