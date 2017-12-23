<?php
/**
 * Author: lf
 * Blog: https://blog.feehi.com
 * Email: job@feehi.com
 * Created at: 2017-08-20 12:01
 */

namespace feehi\swoole;

use feehi\web\Session;
use swoole_http_server;

class SwooleServer
{
    public $swoole;

    public $webRoot;

    public $config = ['gcSessionInterval' => 60000];

    public $runApp;

    public function __construct($host, $port, $mode, $socketType, $swooleConfig=[], $config=[])
    {
        $this->swoole = new swoole_http_server($host, $port, $mode, $socketType);
        $this->webRoot = $swooleConfig['document_root'];
        if( !empty($this->config) ) $this->config = array_merge($this->config, $config);
        $this->swoole->set($swooleConfig);
        $this->swoole->on('request', [$this, 'onRequest']);
        $this->swoole->on('WorkerStart', [$this, 'onWorkerStart']);
    }

    public function run()
    {
        $this->swoole->start();
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    public function onRequest($request, $response)
    {
        //拦截无效请求
        //$this->rejectUnusedRequest($request, $response);

        //静态资源服务器
        //$this->staticRequest($request, $response);

        //转换$_FILE超全局变量
         $this->mountGlobalFilesVar($request);

        call_user_func_array($this->runApp, [$request, $response]);
    }

    public function onWorkerStart( $serv , $worker_id) {
        if( $worker_id == 0 ) {
            swoole_timer_tick($this->config['gcSessionInterval'], function(){//一分钟清理一次session
                (new Session())->gcSession();
            });
        }
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    private function rejectUnusedRequest($request, $response)
    {
        $uri = $request->server['request_uri'];
        $iru = strrev($uri);

        if( strpos('pam.', $iru) === 0 ){//.map后缀
            $response->status(200);
            $response->end('');
        }
    }

    /**
     * @param \swoole_http_request $request
     * @param \swoole_http_response $response
     */
    private function staticRequest($request, $response)
    {
        $uri = $request->server['request_uri'];
        $extension = pathinfo($uri, PATHINFO_EXTENSION);
        if( !empty($extension) && in_array($extension, ['js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'webp']) ){

            $web = $this->webRoot;
            rtrim($web, '/');
            $file = $web . '/' . $uri;
            if( is_file( $file )){
                $temp = strrev($file);
                if( strpos($uri, 'sj.') === 0 ) {
                    $response->header('Content-Type', 'application/x-javascript', false);
                }else if(strpos($temp, 'ssc.') === 0){
                    $response->header('Content-Type', 'text/css', false);
                }else {
                    $response->header('Content-Type', 'application/octet-stream', false);
                }
                $response->sendfile($file, 0);
            }else{
                $response->status(404);
                $response->end('');
            }
        }
    }

    /**
     * @param \swoole_http_request $request
     */
    private function mountGlobalFilesVar($request)
    {
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
        $_GET = isset($request->get) ? $request->get : [];
        $_POST = isset($request->post) ?  $request->post : [];
        $_COOKIE = isset($request->cookie) ?  $request->cookie : [];

        $server = isset($request->server) ? $request->server : [];
        $header = isset($request->header) ? $request->header : [];
        foreach ($server as $key => $value) {
            $_SERVER[strtoupper($key)] = $value;
            unset($server[$key]);
        }
        foreach ($header as $key => $value) {
            $_SERVER['HTTP_'.strtoupper($key)] = $value;
        }
        $_SERVER['SERVER_SOFTWARE'] = "swoole/" . SWOOLE_VERSION;
    }

}