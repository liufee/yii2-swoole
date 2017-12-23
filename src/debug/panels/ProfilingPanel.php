<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-12-23 12:51
 */
namespace feehi\debug\panels;

use yii;
use yii\log\Logger;

class ProfilingPanel extends \yii\debug\panels\ProfilingPanel
{
    public function save()
    {
        $target = $this->module->logTarget;
        $messages = $target->filterMessages($target->messages, Logger::LEVEL_PROFILE);
        return [
            'memory' => memory_get_peak_usage(),
            'time' => microtime(true) - yii::$app->getLog()->yiiBeginAt,
            'messages' => $messages,
        ];
    }
}