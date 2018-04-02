<?php
/**
 * User: lbmzorx
 * Date: 2018/4/2
 * Time: 17:09
 */
namespace feehi\web;

use Yii;
use yii\base\InvalidConfigException;
use \yii\redis\Connection;

/**
 * Class RedisSession
 *
 * you can config conponents to use RedisSession,make sure you have installed redis and composer Yii-Redis.you can learn yii-redis
 * from https://github.com/yiisoft/yii2-redis
 * config
 *  'components'=>[
 *  ...
 *      'session'=>[
 *          'class'=>'feehi\web\RedisSession',
 *          'timeout'=>'1400',
 *          'redis'=>[
 *                 'hostname' => '127.0.0.1',
 *                 'port' => 6379,
 *          ],
 *      ]
 *
 * @package feehi\web
 */
class RedisSession extends Session
{
    /**
     * @var \yii\redis\Connection|string|array the Redis [[Connection]] object or the application component ID of the Redis [[Connection]].
     * This can also be an array that is used to create a redis [[Connection]] instance in case you do not want do configure
     * redis connection as an application component.
     * After the Session object is created, if you want to change this property, you should only assign it
     * with a Redis [[Connection]] object.
     */
    public $redis;
    public $keyPrefix;
    public function init()
    {
        if (is_string($this->redis)) {
            $this->redis = Yii::$app->get($this->redis);
        } elseif (is_array($this->redis)) {
            if (!isset($this->redis['class'])) {
                $this->redis['class'] = Connection::className();
            }
            $this->redis = Yii::createObject($this->redis);
        }
        if (!$this->redis instanceof Connection) {
            throw new InvalidConfigException("Session::redis must be either a Redis connection instance or the application component ID of a Redis connection.");
        }
        if ($this->keyPrefix === null) {
            $this->keyPrefix = substr(md5(Yii::$app->id), 0, 5);
        }
        parent::init();
    }
    /**
     * Returns a value indicating whether to use custom session storage.
     * This method should be overridden to return true by child classes that implement custom session storage.
     * To implement custom session storage, override these methods: [[openSession()]], [[closeSession()]],
     * [[readSession()]], [[writeSession()]], [[destroySession()]] and [[gcSession()]].
     * @return bool whether to use custom storage.
     */
    public function getUseCustomStorage()
    {
        return false;
    }
    /**
     * swoole每隔设置的毫秒数执行此方法回收session
     */
    public function gcSession()
    {
        //redis自动回收资源
    }
    /**
     * Session read handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @return array the session data
     */
    public function readSession($id)
    {
        $data = $this->redis->executeCommand('GET', [$this->calculateKey($id)]);
        $data= ($data === false || $data === null) ? [] :\yii\helpers\Json::decode($data);
        return $data?$data:[];
    }
    /**
     * Session write handler.
     * @internal Do not call this method directly.
     * @param string $id session ID
     * @param string $data session data
     * @return bool whether session write is successful
     */
    public function writeSession($id, $data)
    {
        // exception must be caught in session write handler
        // http://us.php.net/manual/en/function.session-set-save-handler.php#refsect1-function.session-set-save-handler-notes
        return (bool) $this->redis->executeCommand('SET', [$this->calculateKey($id), $data, 'EX', $this->getTimeout()]);
    }
    /**
     * Session destroy handler.
     * Do not call this method directly.
     * @param string $id session ID
     * @return bool whether session is destroyed successfully
     */
    public function destroySession($id)
    {
        $this->redis->executeCommand('DEL', [$this->calculateKey($id)]);
        // @see https://github.com/yiisoft/yii2-redis/issues/82
        $_SESSION = [];
        return true;
    }
    /**
     * Generates a unique key used for storing session data in cache.
     * @param string $id session variable name
     * @return string a safe cache key associated with the session variable name
     */
    protected function calculateKey($id)
    {
        return $this->keyPrefix . md5(json_encode([__CLASS__, $id]));
    }
}