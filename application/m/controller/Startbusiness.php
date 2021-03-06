<?php

namespace app\m\controller;

require_once __DIR__ . '/../../../vendor/GatewayWorker/vendor/autoload.php';

use Workerman\Worker;
use GatewayWorker\BusinessWorker;
use Workerman\Autoloader;


class Startbusiness{

    public function __construct(){

        // bussinessWorker 进程
        $worker = new BusinessWorker();
        // worker名称
        $worker->name = 'YourAppBusinessWorker';
        // bussinessWorker进程数量
        $worker->count = 4;
        // 服务注册地址
        $worker->registerAddress = '127.0.0.1:1238';

        //设置处理业务的类,此处制定Events的命名空间
        $worker->eventHandler = 'app\index\controller\Events';

        // 如果不是在根目录启动，则运行runAll方法
        if(!defined('GLOBAL_START'))
        {
            Worker::runAll();
        }




    }





}



