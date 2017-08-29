<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-19 17:24
 */

namespace feehi\console;

use yii;
use yii\helpers\ArrayHelper;
use feehi\web\Request;
use feehi\web\Response;
use feehi\web\Session;
use feehi\swoole\SwooleServer;
use yii\web\AssetManager;
use yii\web\Application;

class SwooleController extends \yii\console\Controller
{

    public $host = "0.0.0.0";

    public $port = 9999;

    public $mode = SWOOLE_PROCESS;

    public $socketType = SWOOLE_TCP;

    public $rootDir = "";

    public $app = "frontend";

    public $swooleConfig = [];

    public $gcSessionInterval = 60000;//启动session回收的间隔时间，单位为毫秒



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

        $config = ArrayHelper::merge(
            require($rootDir . '/common/config/main.php'),
            require($rootDir . '/common/config/main-local.php'),
            require($rootDir . $this->app . '/config/main.php'),
            require($rootDir . $this->app . '/config/main-local.php')
        );

        $this->swooleConfig = array_merge([
            'document_root' => $web,
            'enable_static_handler' => true,
        ], $this->swooleConfig);

        $server = new SwooleServer($this->host, $this->port, $this->mode, $this->socketType, $this->swooleConfig, ['gcSessionInterval'=>$this->gcSessionInterval]);

        function dump($var){
            if( is_array($var) || is_object($var) ){
                $body = print_r($var, true);
            }else{
                $body = $var;
            }
            if( isset(yii::$app->getResponse()->swooleResponse) ){
                echo "dump function must called in request period" . PHP_EOL;
            }
            yii::$app->getResponse()->swooleResponse->end($body);
        }

        /**
         * @param \swoole_http_request $request
         * @param \swoole_http_response $response
         */
        $server->runApp = function ($request, $response) use ($config, $web) {
            $aliases = [
                '@web' => $web,
                '@webroot' => $web,
            ];
            $config['aliases'] = isset($config['aliases']) ? array_merge($aliases, $config['aliases']) : $aliases;

            $requestComponent = [
                'class' => Request::className(),
                'swooleRequest' => $request,
            ];
            $config['components']['request'] = isset($config['components']['request']) ? array_merge($config['components']['request'], $requestComponent) : $requestComponent;

            $responseComponent = [
                'class' => Response::className(),
                'swooleResponse' => $response,
            ];
            $config['components']['response'] = isset($config['components']['response']) ? array_merge($config['components']['response'], $responseComponent) : $responseComponent;

            $authManagerComponent = [
                'class' => AssetManager::className(),
                'baseUrl' => '/assets'
            ];
            $config['components']['assetManager'] = isset( $config['components']['assetManager'] ) ? array_merge($authManagerComponent, $config['components']['assetManager']) : $authManagerComponent;

            $config['components']['session'] = [
                "class" => Session::className()
            ];

            try {
                $application = new Application($config);
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
            $this->stdout("server is not running!" . PHP_EOL);
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