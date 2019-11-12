yii2 swoole
===============================

让yii2运行在swoole上，不用修改一句代码。

 
演示站点
----------------
可以参考cms系统[FeehiCMS](http://www.github.com/liufee/cms)

前置说明
---------------
1. 有yii2-advanced-app的使用经验
2. 了解并使用过swoole, 如果没有请先阅读swoole的doc
3. 强烈建议先按照这篇文章阅读并实践理解yii2和swoole结合的两种方式
    https://www.jianshu.com/p/9c2788ccf3c0
4. 当你做完123 并且使用过yii2-swoole之后
    可以去了解一下swoft 对比一下为协程而设计的框架和yii2这种的区别
 
安装
---------------
1. 使用composer
     composer的安装以及国内镜像设置请点击[此处](https://developer.aliyun.com/composer)
     
     ```bash
     $ cd /path/to/yii2-app
     $ composer require "feehi/yii2-swoole"
     $ composer install -vvv
     ```
 

配置yii2
-------------
打开console/config/main.php，在顶层配置中加入如下配置。（注意：并不是配置在components里面，而应该在最外层，即与components同级）。[完整示例](https://github.com/liufee/cms/blob/master/console/config/main.php)

```bash
 'id' => 'app-console',
 ...//其他配置
'controllerMap'=>[
     ...//其他配置项
    'swoole-backend' => [
            'class' => feehi\console\SwooleController::class,
            'rootDir' => str_replace('console/config', '', __DIR__ ),//yii2项目根路径
            'app' => 'backend',
            'host' => '127.0.0.1',
            'port' => 9998,
            'web' => 'web',//默认为web。rootDir app web目的是拼接yii2的根目录，如果你的应用为basic，那么app为空即可。
            'debug' => true,//默认开启debug，上线应置为false
            'env' => 'dev',//默认为dev，上线应置为prod 
            'swooleConfig' => [
                'reactor_num' => 2,
                'worker_num' => 4,
                'daemonize' => false,
                'log_file' => __DIR__ . '/../../backend/runtime/logs/swoole.log',
                'log_level' => 0,
                'pid_file' => __DIR__ . '/../../backend/runtime/server.pid',
            ],
    ]
    ...//其他配置
 ]
 ...//其他配置
```


启动命令
-------------
    * 启动 /path/to/php /path/to/yii swoole-backend/start
    * 关闭 /path/to/php /path/to/yii swoole-backend/stop
    * 重启 /path/to/php /path/to/yii swoole-backend/restart
    
    
使用systemd管理yii2-swoole的启动关闭
---------------------------
像管理apache一样使用service httpd start和service httpd stop以及service httpd restart来启动、关闭、重启yii2 swoole服务了。

    1. 复制feehi.service和feehi-backend.service到/etc/systemd/system目录
    2. 分别修改feehi.service和feehi-backend.service中[Service]部分的 /path/to/yii2app为你的目录，/path/to/php为你的php命令绝对路径
    3. 运行systemctl daemon-reload

    
加入开机自动启动
---------------------------   
 
   方法一 
   
        1. 使用systemd管理服务
        2. 运行systemctl enable feehi以及systemctl enable feehi-backend设置开机自动启动
        
   方法二
   
        在/etc/rc.local中加入
        /path/to/php /path/to/yii2app/yii swoole-backend/start
  

Nginx配置
-------------
虽然swoole从1.9.17版本以后底层支持作为静态资源web服务器，但毕竟没有完全实现http协议，强烈推荐配合nginx使用，把swoole仅作为应用服务器。

```bash
 *
 * 后台
 *
 server {
    set $web /www/cms-swoole/backend/web;
    root $web;
    server_name swoole-admin.cms.test.docker;

    location ~* .(ico|gif|bmp|jpg|jpeg|png|swf|js|css|mp3)$ {
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
```


调试
-------------

   debug

       var_dump、echo都是输出到控制台，不方便调试。可以使用\feehi\swoole\Util::dump()，输出数组、对象、字符串、布尔值到浏览器

   log

       已经修复
       关于logger为何要替换的原因参见这篇文章详解: https://zguoqiang.com/2018/12/17/swoole%E5%9F%BA%E7%A1%80-%E4%B8%8E%E4%BC%A0%E7%BB%9FMVC%E6%A1%86%E6%9E%B6%E7%9A%84%E6%95%B4%E5%90%88/
