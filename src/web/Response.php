<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-19 10:52
 */

namespace feehi\web;


use Yii;
use yii\base\InvalidConfigException;
use yii\web\CookieCollection;

class Response extends \yii\web\Response
{

    /* @var $swooleResponse \swoole_http_response */
    public $swooleResponse;

    protected function sendHeaders()
    {
        if (headers_sent()) {
            return;
        }
        $headers = $this->getHeaders();
        if ($headers) {
            foreach ($headers as $name => $values) {
                $name = str_replace(' ', '-', ucwords(str_replace('-', ' ', $name)));
                foreach ($values as $value) {
                    $this->swooleResponse->header($name, $value, false);
                }
            }
        }
        $statusCode = $this->getStatusCode();
        $this->swooleResponse->status($statusCode);
        $this->sendCookies();
    }

    protected function sendContent()
    {

        $this->swooleResponse->end($this->content);
    }

    private $_cookies;

    public function getCookies()
    {
        if ($this->_cookies === null) {
            $this->_cookies = new CookieCollection;
        }
        return $this->_cookies;
    }

    protected function sendCookies()
    {
        if ($this->_cookies === null) {
            return;
        }
        $request = Yii::$app->getRequest();
        if ($request->enableCookieValidation) {
            if ($request->cookieValidationKey == '') {
                throw new InvalidConfigException(get_class($request) . '::cookieValidationKey must be configured with a secret key.');
            }
            $validationKey = $request->cookieValidationKey;
        }
        foreach ($this->getCookies() as $cookie) {
            $value = $cookie->value;
            if ($cookie->expire != 1  && isset($validationKey)) {
                $value = Yii::$app->getSecurity()->hashData(serialize([$cookie->name, $value]), $validationKey);
            }
            $this->swooleResponse->cookie($cookie->name, $value, $cookie->expire, $cookie->path, $cookie->domain, $cookie->secure, $cookie->httpOnly);
        }
    }

}