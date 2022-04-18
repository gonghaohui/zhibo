<?php
/**
 * linux workerman例子测试
 * 需要在Linux系统控制台进行启动，启动文件位于根目录的start.php文件中
 * Windows无法进行同时启动多个协议
 * 由于PHP-CLI在windows系统无法实现多进程以及守护进程，所以windows版本Workerman建议仅作开发调试使用。
 */
namespace app\m\controller;

require_once __DIR__ . '/../../../vendor/GatewayWorker/vendor/autoload.php';

use Workerman\Worker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use GatewayWorker\BusinessWorker;
class Gate
{
    /**
     * 构造函数
     * @access public
     */
    public function __construct(){

        //初始化各个GatewayWorker
        //初始化register register 服务必须是text协议
        $register = new Register('text://0.0.0.0:1238');

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
        Worker::runAll();
    }
}