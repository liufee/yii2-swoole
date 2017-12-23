<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-12-23 12:40
 */

namespace feehi\debug;


use yii;
use yii\helpers\Url;

class Module extends \yii\debug\Module
{
    public function init()
    {
        parent::init();
        $this->setViewPath('@vendor/yiisoft/yii2-debug/views');
    }

    public function setDebugHeaders($event)
    {
        if (!$this->checkAccess()) {
            return;
        }
        $url = Url::toRoute(['/' . $this->id . '/default/view',
            'tag' => $this->logTarget->tag,
        ]);
        $event->sender->getHeaders()
            ->set('X-Debug-Tag', $this->logTarget->tag)
            ->set('X-Debug-Duration', number_format((microtime(true) - yii::$app->getLog()->yiiBeginAt) * 1000 + 1))
            ->set('X-Debug-Link', $url);
    }
}