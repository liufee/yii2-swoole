<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-19 17:24
 */

namespace feehi\console;

use yii;

/**
 * Class SwooleController
 *
 * @package feehi\console\controllers
 *
 * @description
 *
 * 支持的命令
 *
 * ./yii swoole/start 启动前台swoole
 * ./yii swoole/stop 关闭前台swoole
 * ./yii swoole/restart 重启前台swoole
 *
 *  ./yii swoole-backend/start 启动后台swoole
 * ./yii swoole-backend/stop 关闭后台swoole
 * ./yii swoole-backend/restart 重启后台swoole
 *
 *
 * 配置示例
 'controllerMap'=>[
     ...
     'swoole' => [
            'class' => feehi\console\SwooleController::className(),
            'rootDir' => str_replace('console/config', '', __DIR__ ),//yii2项目根路径
            'app' => 'frontend',//app目录地址
            'host' => '127.0.0.1',//监听地址
            'port' => 9999,//监听端口
            'swooleConfig' => [//标准的swoole配置项都可以再此加入
                'reactor_num' => 2,
                'worker_num' => 4,
                'daemonize' => false,
                'log_file' => __DIR__ . '/../../frontend/runtime/logs/swoole.log',
                'log_level' => 0,
                'pid_file' => __DIR__ . '/../../frontend/runtime/server.pid',
            ],
    ],
    'swoole-backend' => [
            'class' => feehi\console\SwooleController::className(),
            'rootDir' => str_replace('console/config', '', __DIR__ ),//yii2项目根路径
            'app' => 'backend',
            'host' => '127.0.0.1',
            'port' => 9998,
            'swooleConfig' => [
            'reactor_num' => 2,
            'worker_num' => 4,
            'daemonize' => false,
            'log_file' => __DIR__ . '/../../backend/runtime/logs/swoole.log',
            'log_level' => 0,
            'pid_file' => __DIR__ . '/../../backend/runtime/server.pid',
        ],
    ]
    ...
 ]
 *
 * nginx 配置示列 //虽然swoole从1.9.8开始支持静态资源，但是性能很差，线上环境务必搭配nginx使用
 *
 * 前台
 *
 server {
    set $web /www/cms-swoole/frontend/web;
    root $web;
    server_name swoole.cms.test.docker;

    location ~* .(ico|gif|bmp|jpg|jpeg|png|swf|js|css|mp3) {
    root  $web;
    }

    location ~ timthumb\.php$ {//若部分功能仍需要使用php-fpm则做类似配置，否则删除此段
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

    location / {
        proxy_http_version 1.1;
        proxy_set_header Connection "keep-alive";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Host http://swoole.cms.test.docker;
        proxy_pass http://127.0.0.1:9999;
    }
 }
 *
 后台
 server {
    set $web /www/cms-swoole/backend/web;
    root $web;
    server_name swoole-admin.cms.test.docker;

    location ~* .(ico|gif|bmp|jpg|jpeg|png|swf|js|css|mp3) {
        root  $web;
    }

    location / {
        proxy_http_version 1.1;
        proxy_set_header Connection "keep-alive";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Host http://swoole-admin.cms.test.docker;
        proxy_pass http://127.0.0.1:9998;
    }
 }
 */

class SwooleController extends \yii\console\Controller
{

    public $host = "0.0.0.0";

    public $port = 9999;

    public $rootDir = "";

    public $app = "frontend";

    public $swooleConfig = [];



    public function actionStart()
    {
        $rootDir = $this->rootDir;
        $web = $rootDir . $this->app . '/web';;


        defined('YII_DEBUG') or define('YII_DEBUG', true);
        defined('YII_ENV') or define('YII_ENV', 'dev');

        require($rootDir . '/vendor/autoload.php');
        //require($rootDir . '/vendor/yiisoft/yii2/Yii.php');
        require($rootDir . '/common/config/bootstrap.php');
        require($rootDir . $this->app .  '/config/bootstrap.php');

        $config = yii\helpers\ArrayHelper::merge(
            require($rootDir . '/common/config/main.php'),
            require($rootDir . '/common/config/main-local.php'),
            require($rootDir . $this->app . '/config/main.php'),
            require($rootDir . $this->app . '/config/main-local.php')
        );

        $this->swooleConfig = array_merge([
            'document_root' => $web,
            'enable_static_handler' => true,
        ], $this->swooleConfig);

        $server = new \feehi\swoole\SwooleServer($this->host, $this->port, $this->swooleConfig);

        /**
         * @param \swoole_http_request $request
         * @param \swoole_http_response $response
         */
        $server->runApp = function ($request, $response) use ($config, $web) {
            /*$uri = $request->server['request_uri'];
            if (strpos($uri, 'timthumb')) {
                $image = new \feehi\components\PicFilter();
                $image->initialize([
                    'source_image' => $web . "/uploads/article/thumb/5998ec3c119ea_a6.jpg",
                    'width'        => 200,
                    'height'       => 200,
                ]);
                $image->resize();
                exit;
            }*/
            $aliases = [
                '@web' => $web,
                '@webroot' => $web,
            ];
            $config['aliases'] = isset($config['aliases']) ? array_merge($aliases, $config['aliases']) : $aliases;

            $requestComponent = [
                'class' => \feehi\web\Request::className(),
                'swooleRequest' => $request,
            ];
            $config['components']['request'] = isset($config['components']['request']) ? array_merge($config['components']['request'], $requestComponent) : $requestComponent;

            $responseComponent = [
                'class' => \feehi\web\Response::className(),
                'swooleResponse' => $response,
            ];
            $config['components']['response'] = isset($config['components']['response']) ? array_merge($config['components']['response'], $responseComponent) : $responseComponent;

            $authManagerComponent = [
                'class' => yii\web\AssetManager::className(),
                'baseUrl' => '/assets'
            ];
            $config['components']['assetManager'] = isset( $config['components']['assetManager'] ) ? array_merge($authManagerComponent, $config['components']['assetManager']) : $authManagerComponent;

            $config['components']['session'] = [
                "class" => \feehi\web\Session::className()
            ];

            try {
                $application = new \yii\web\Application($config);
                yii::setAlias('@web', $web);
                yii::$app->setAliases($aliases);
                $application->run();
            }catch (\Exception $e){
                yii::$app->getErrorHandler()->handleException($e);
            }
        };

        $this->stdout("server is running, listening {$this->host}:{$this->port}" . PHP_EOL);
        $server->run();
    }

    public function actionStop()
    {
        $this->sendSignal(SIGTERM);
        $this->stdout("server is stopped, stop listening {$this->host}:{$this->port}" . PHP_EOL);
    }

    public function actioReloadTask()
    {
        $this->sendSignal(SIGUSR2);
    }

    public function actionRestart()
    {
        $pid = $this->sendSignal(SIGTERM);
        $time = 0;
        while (posix_getpgid($pid) && $time <= 10) {
            usleep(100000);
            $time++;
        }
        if ($time > 100) {
            $this->stdout( 'timeout' . PHP_EOL );
            exit(1);
        }
        $this->actionStart();
        $this->stdout("server restart success, listening {$this->host}:{$this->port}" . PHP_EOL);
    }

    public function actionReload()
    {
        $this->actionRestart();
    }

    private function sendSignal($sig)
    {
        if ($pid = $this->getPid()) {
            posix_kill($pid, $sig);
        } else {
            $this->stdout("not running!" . PHP_EOL);
            exit(1);
        }
    }


    private function getPid()
    {
        $pid_file = $this->swooleConfig['pid_file'];
        if (file_exists($pid_file)) {
            $pid = file_get_contents($pid_file);
            if (posix_getpgid($pid)) {
                return $pid;
            } else {
                unlink($pid_file);
            }
        }
        return false;
    }

}