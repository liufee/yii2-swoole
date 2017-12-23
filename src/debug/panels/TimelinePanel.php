<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-12-23 12:53
 */

namespace feehi\debug\panels;


use yii;

class TimelinePanel extends \yii\debug\panels\TimelinePanel
{
    public function save()
    {
        return [
            'start' => yii::$app->getLog()->yiiBeginAt,
            'end' => microtime(true),
            'memory' => memory_get_peak_usage(),
        ];
    }
}