<?php

namespace app\m\controller;

require_once __DIR__ . '/../../../vendor/GatewayWorker/vendor/autoload.php';

use Workerman\Worker;
use GatewayWorker\Gateway;
use Workerman\Autoloader;

class Start{

    public function __construct(){
        // 自动加载类

        // gateway 进程，这里使用Text协议，可以用telnet测试
        $gateway = new Gateway("Websocket://0.0.0.0:8282");
        // gateway名称，status方便查看
        $gateway->name = 'YourAppGateway';
        // gateway进程数
        $gateway->count = 4;
        // 本机ip，分布式部署时使用内网ip
        $gateway->lanIp = '127.0.0.1';
        // 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
        // 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口
        $gateway->startPort = 2900;
        // 服务注册地址
        $gateway->registerAddress = '127.0.0.1:1238';

        //运行所有Worker;
        if(!defined('GLOBAL_START'))
        {
            Worker::runAll();
        }


    }
}

