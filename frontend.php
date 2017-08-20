<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-18 23:55
 */
$rootDir = "/path/to/project";

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require($rootDir . '/vendor/autoload.php');
require($rootDir . '/vendor/yiisoft/yii2/Yii.php');
require($rootDir . '/common/config/bootstrap.php');
require($rootDir . '/backend/config/bootstrap.php');

$config = yii\helpers\ArrayHelper::merge(
    require($rootDir . '/common/config/main.php'),
    require($rootDir . '/common/config/main-local.php'),
    require($rootDir . '/backend/config/main.php'),
    require($rootDir . '/backend/config/main-local.php')
);

function dump($var)
{
    if( is_array($var) ){
        $temp = "(array){<br>";
        foreach ($var as $k => $v){
            $temp .= $k . '=>' . $v . "<br>";
        }
        $temp .= "}<br>";
        $var = $temp;
    }
    yii::$app->get('response')->swooleResponse->end($var);
}

$web = $rootDir . "/frontend/web/";

$server = new \swoole_http_server("0.0.0.0", 9999);

$server->set([
    'document_root' => $web,
    'enable_static_handler' => true,
]);

$server->on('request', function ($request, $response)use ($config, $web){
    if( isset($request->files) ) {
        $files = $request->files;
        foreach ($files as $k => $v) {
            if( isset($v['name']) ){
                $_FILES = $files;
                break;
            }
            foreach ($v as $key => $val) {
                $_FILES[$k]['name'][$key] = $val['name'];
                $_FILES[$k]['type'][$key] = $val['type'];
                $_FILES[$k]['tmp_name'][$key] = $val['tmp_name'];
                $_FILES[$k]['size'][$key] = $val['size'];
                if(isset($val['error'])) $_FILES[$k]['error'][$key] = $val['error'];
            }
        }
    }

    $aliases = [
        '@web' => $web,
        '@webroot' => $web,
    ];
    $config['aliases'] = isset($config['aliases']) ? array_merge($aliases, $config['aliases']) : $aliases;
    $config['components']['request'] = [
        'class' => feehi\web\Request::className(),
        'swooleRequest' => $request,
        'cookieValidationKey' => 'KaNMPF6oZegCr0bhED4JHYnhOse7UhrS',
        'enableCsrfValidation' => true,
    ];
    $config['components']['response'] = [
        'class' => feehi\web\Response::className(),
        'swooleResponse' => $response,
    ];
    $config['components']['assetManager'] = [
        'class' => yii\web\AssetManager::className(),
        'baseUrl' => '/assets'
    ];
    $application = new yii\web\Application($config);
    yii::$app->setAliases($aliases);
    $application->run();
});

$server->start();