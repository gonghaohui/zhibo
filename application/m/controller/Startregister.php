<?php
namespace app\m\controller;

require_once __DIR__ . '/../../../vendor/GatewayWorker/vendor/autoload.php';

use Workerman\Worker;
use GatewayWorker\Register;


class Startregister{

    public function __construct(){

        $register = new Register('text://0.0.0.0:1238');
        if(!defined('GLOBAL_START'))
        {
            Worker::runAll();
        }



    }






}