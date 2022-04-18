<?php
namespace app\index\controller;

use think\Cache;
use think\cache\driver\Redis;
use think\Db;
//use think\session\driver\Redis;
use My\RedisPackage;

class Chat extends PcBase
{
    protected static $redis;

    public function __construct()
    {
        parent::__construct();
        self::$redis=RedisPackage::getInstance();
    }

    public function one_chat(){

//        $redis_1=RedisPackage::getInstance();
//        echo $redis_1->get('room_1_online_count');
//
//        $redis_2=RedisPackage::getInstance();
//        echo $redis_2->get('room_4_online_count');
//        exit;
        echo self::$redis->get('room_1_online_count');


        return $this->fetch('chat/one_chat');

    }

    public function get_value(){

        echo self::$redis->get('room_4_online_count');
    }


    public function group_chat(){

        $room = input('room');
        if($room == ''){
            $room = 'room_1';
        }

        $this->assign('room',$room);
        return $this->fetch('chat/group_chat');

    }



}