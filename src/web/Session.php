<?php

namespace feehi\web;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\web\SessionIterator;

class Session extends Component implements \IteratorAggregate, \ArrayAccess, \Countable
{

    /* @description $savePath session存储目录，执行swoole的用户必须对目录有读和写的权限 */
    public $savePath = "/tmp/";

    /* @description $lifeTime session有效时间（秒） */
    public $lifeTime = 1400;

    public $flashParam = '__flash';

    private $_started = false;

    private $_cookieParams = [
        'lifetime' => 1400,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
    ];

    private $_prefix = "feehi_";

    public function init()
    {
        parent::init();
        if ($this->getIsActive()) {
            Yii::warning('Session is already started', __METHOD__);
            $this->updateFlashCounters();
        }
    }

    public function getSessionFullName()
    {
        return $this->getSavePath() . $this->_prefix . $this->getId();
    }

    public function persist()
    {
        $this->open();
        file_put_contents($this->getSessionFullName(), json_encode($_SESSION));
    }

    public function gcSession()
    {
        $handle = opendir( $this->getSavePath() );
        while (false !== ($file = readdir($handle)))
        {
            if ($file != "." && $file != ".." && (strpos($file, $this->_prefix) === 0) && is_file($this->getSavePath() . $file)) {
                $lastUpdatedAt = filemtime($this->getSavePath() . $file);
                if( time() - $lastUpdatedAt > $this->lifeTime ){
                    unlink($this->getSavePath() . $file);
                }
            }
        }
    }

    public function open()
    {
        if ($this->getIsActive()) {
            return;
        }
        $file = $this->getSessionFullName();
        if( file_exists($file) && is_file($file) ) {
            $data = file_get_contents($file);
            $_SESSION = json_decode($data, true);
        }else{
            $_SESSION = [];
        }
        $this->_started = true;
    }

    public function getCookieParams()
    {
        return $this->_cookieParams;
    }

    public function setCookieParams(array $config){
        $this->_cookieParams;
    }

    public function destroy()
    {
        if ($this->getIsActive()) {
            $_SESSION = [];
        }
    }

    public function getIsActive()
    {
        return $this->_started;
    }

    private $_hasSessionId;

    public function getHasSessionId()
    {
        if ($this->_hasSessionId === null) {
            $name = $this->getName();
            $request = Yii::$app->getRequest();
            if (!empty($_COOKIE[$name]) && ini_get('session.use_cookies')) {
                $this->_hasSessionId = true;
            } elseif (!ini_get('session.use_only_cookies') && ini_get('session.use_trans_sid')) {
                $this->_hasSessionId = $request->get($name) != '';
            } else {
                $this->_hasSessionId = false;
            }
        }
        return $this->_hasSessionId;
    }

    public function setHasSessionId($value)
    {
        $this->_hasSessionId = $value;
    }

    public function getId()
    {
        if( isset($_COOKIE[$this->getName()]) ){
            $id = $_COOKIE[$this->getName()];
        }else{
            $id = uniqid();
        }
        return $id;
    }

    public function regenerateID($deleteOldSession = false)
    {
    }

    public function getName()
    {
        return "feehi_session";
    }

    public function getSavePath()
    {
        if( strrpos( $this->savePath, '/') !==0 ){
            $this->savePath .= '/';
        }
        if( !is_readable($this->savePath) ){
            throw new InvalidConfigException("SESSION saved path {$this->savePath} is not readable");
        }
        if( !is_writable($this->savePath) ){
            throw new InvalidConfigException("SESSION saved path {$this->savePath} is not writable");
        }
        return $this->savePath;
    }

    public function setSavePath($value)
    {
        $this->savePath = $value;
    }

    public function getIterator()
    {
        $this->open();
        return new SessionIterator();
    }

    public function getCount()
    {
        $this->open();
        return count($_SESSION);
    }

    public function count()
    {
        return $this->getCount();
    }

    public function get($key, $defaultValue = null)
    {
        $this->open();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
    }

    public function set($key, $value)
    {
        $this->open();
        $_SESSION[$key] = $value;
    }

    public function remove($key)
    {
        $this->open();
        if (isset($_SESSION[$key])) {
            $value = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $value;
        }
        return null;
    }

    public function removeAll()
    {
        $this->open();
        foreach (array_keys($_SESSION) as $key) {
            unset($_SESSION[$key]);
        }
    }

    public function has($key)
    {
        $this->open();
        return isset($_SESSION[$key]);
    }

    protected function updateFlashCounters()
    {
        $counters = $this->get($this->flashParam, []);
        if (is_array($counters)) {
            foreach ($counters as $key => $count) {
                if ($count > 0) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($count == 0) {
                    $counters[$key]++;
                }
            }
            $_SESSION[$this->flashParam] = $counters;
        } else {
            // fix the unexpected problem that flashParam doesn't return an array
            unset($_SESSION[$this->flashParam]);
        }
    }

    public function getFlash($key, $defaultValue = null, $delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        if (isset($counters[$key])) {
            $value = $this->get($key, $defaultValue);
            if ($delete) {
                $this->removeFlash($key);
            } elseif ($counters[$key] < 0) {
                // mark for deletion in the next request
                $counters[$key] = 1;
                $_SESSION[$this->flashParam] = $counters;
            }
            return $value;
        }
        return $defaultValue;
    }

    public function getAllFlashes($delete = false)
    {
        $counters = $this->get($this->flashParam, []);
        $flashes = [];
        foreach (array_keys($counters) as $key) {
            if (array_key_exists($key, $_SESSION)) {
                $flashes[$key] = $_SESSION[$key];
                if ($delete) {
                    unset($counters[$key], $_SESSION[$key]);
                } elseif ($counters[$key] < 0) {
                    // mark for deletion in the next request
                    $counters[$key] = 1;
                }
            } else {
                unset($counters[$key]);
            }
        }
        $_SESSION[$this->flashParam] = $counters;
        return $flashes;
    }

    public function setFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $_SESSION[$key] = $value;
        $_SESSION[$this->flashParam] = $counters;
    }

    public function addFlash($key, $value = true, $removeAfterAccess = true)
    {
        $counters = $this->get($this->flashParam, []);
        $counters[$key] = $removeAfterAccess ? -1 : 0;
        $_SESSION[$this->flashParam] = $counters;
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = [$value];
        } else {
            if (is_array($_SESSION[$key])) {
                $_SESSION[$key][] = $value;
            } else {
                $_SESSION[$key] = [$_SESSION[$key], $value];
            }
        }
    }

    public function removeFlash($key)
    {
        $counters = $this->get($this->flashParam, []);
        $value = isset($_SESSION[$key], $counters[$key]) ? $_SESSION[$key] : null;
        unset($counters[$key], $_SESSION[$key]);
        $_SESSION[$this->flashParam] = $counters;
        return $value;
    }

    public function removeAllFlashes()
    {
        $counters = $this->get($this->flashParam, []);
        foreach (array_keys($counters) as $key) {
            unset($_SESSION[$key]);
        }
        unset($_SESSION[$this->flashParam]);
    }

    public function hasFlash($key)
    {
        return $this->getFlash($key) !== null;
    }

    public function offsetExists($offset)
    {
        $this->open();
        return isset($_SESSION[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->open();
        return isset($_SESSION[$offset]) ? $_SESSION[$offset] : null;
    }

    public function offsetSet($offset, $item)
    {
        $this->open();
        $_SESSION[$offset] = $item;
    }

    public function offsetUnset($offset)
    {
        $this->open();
        unset($_SESSION[$offset]);
    }
}