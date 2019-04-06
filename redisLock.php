<?php

/**
 * Class Lock_Service 单据锁服务
 */
include "vendor/autoload.php";

class Lock_Service
{
    /**
     * 单据锁redis key模板
     */
    const REDIS_LOCK_KEY_TEMPLATE = 'order_lock_%s';

    /**
     * 单据锁默认超时时间（秒）
     */
    const REDIS_LOCK_DEFAULT_EXPIRE_TIME = 30;

    /**
     * 加单据锁
     * @param int $intOrderId 单据ID
     * @param int $intExpireTime 锁过期时间（秒）
     * @return bool|int 加锁成功返回唯一锁ID，加锁失败返回false
     */
    public static function addLock($intOrderId, $intExpireTime = self::REDIS_LOCK_DEFAULT_EXPIRE_TIME)
    {
        //参数校验
        if (empty($intOrderId) || $intExpireTime <= 0) {
            return false;
        }

        //获取Redis连接
        $objRedisConn = self::getRedisConn();

        //生成唯一锁ID，解锁需持有此ID
        $intUniqueLockId = self::generateUniqueLockId();

        //根据模板，结合单据ID，生成唯一Redis key（一般来说，单据ID在业务中系统中唯一的）
        $strKey = sprintf(self::REDIS_LOCK_KEY_TEMPLATE, $intOrderId);

        //加锁（通过Redis setnx指令实现，从Redis 2.6.12开始，通过set指令可选参数也可以实现setnx，同时可原子化地设置超时时间）
        $bolRes = $objRedisConn->set($strKey, $intUniqueLockId, 'EX',$intExpireTime,'NX');

        //加锁成功返回锁ID，加锁失败返回false
        return $bolRes ? $intUniqueLockId : $bolRes;
    }

    /**
     * 解单据锁
     * @param int $intOrderId 单据ID
     * @param int $intLockId 锁唯一ID
     * @return bool
     */
    public static function releaseLock($intOrderId, $intLockId)
    {
        //参数校验
        if (empty($intOrderId) || empty($intLockId)) {
            return false;
        }

        //获取Redis连接
        $objRedisConn = self::getRedisConn();

        //生成Redis key
        $strKey = sprintf(self::REDIS_LOCK_KEY_TEMPLATE, $intOrderId);

        //监听Redis key防止在【比对lock id】与【解锁事务执行过程中】被修改或删除，提交事务后会自动取消监控，其他情况需手动解除监控
        $objRedisConn->watch($strKey);
        if ($intLockId == $objRedisConn->get($strKey)) {
            $objRedisConn->multi();
            $objRedisConn->del($strKey);
            $objRedisConn->exec();
            return true;
        }
        $objRedisConn->unwatch();
        return false;
    }

    /**
     * Redis配置：IP
     */
    const REDIS_CONFIG_HOST = '127.0.0.1';

    /**
     * Redis配置：端口
     */
    const REDIS_CONFIG_PORT = 6379;

    /**
     * 获取Redis连接（简易版本，可用单例实现）
     * @return \Predis\Client
     */
    public static function getRedisConn()
    {
        $objRedis = new \Predis\Client();
        $objRedis->connect(['scheme' => 'tcp',
            'host'   => 'localhost',
            'port'   => 6379,]);
        return $objRedis;
    }

    /**
     * 用于生成唯一的锁ID的redis key
     */
    const REDIS_LOCK_UNIQUE_ID_KEY = 'lock_unique_id';

    /**
     * 生成锁唯一ID（通过Redis incr指令实现简易版本，可结合日期、时间戳、取余、字符串填充、随机数等函数，生成指定位数唯一ID）
     * @return mixed
     */
    public static function generateUniqueLockId()
    {
        return self::getRedisConn()->incr(self::REDIS_LOCK_UNIQUE_ID_KEY);
    }
}

//test
$res1 = Lock_Service::addLock('666666');
var_dump($res1);//返回lock id，加锁成功
$res2 = Lock_Service::addLock('666666');
var_dump($res2);//false，加锁失败
$res3 = Lock_Service::releaseLock('666666', $res1);
var_dump($res3);//true，解锁成功
$res4 = Lock_Service::releaseLock('666666', $res1);
var_dump($res4);//false，解锁失败