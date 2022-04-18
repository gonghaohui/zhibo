<?php

namespace app\index\controller;
use GatewayWorker\Lib\Gateway;
use think\Cache;
use Redis;


class Events {

//    protected static $sent_name;
//    protected static $uid;
//    protected static $lid;

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $data 具体消息
     */
    public static function onMessage($client_id, $data){

        $message_data = json_decode($data,true);
        if(!$message_data){
            return;
        }
        // 设置session，标记该客户端已经登录
//       $_SESSION['uid'] = $data['uid'];

        switch($message_data['type']){
            case "say":
                //加入组
//                if($message_data['uid'] == 0){
//                    Gateway::joinGroup($client_id, $message_data['group']);
//                }
//                Gateway::joinGroup($client_id, $message_data['group']);
                $allClient = Gateway::getClientSessionsByGroup($message_data['group']);
                $data = [
                    'type' => 'text',
//                   'id'   => $client_id,
                    'data' => $message_data['data'],
                    'count' => count($allClient) + $message_data['fake_num'],
                    'sent_name' => $message_data['sent_name'],
                   'send_time' => date("Y-m-d H:i:s",time()),
                    'time' =>time(),
                    'unique_sign' => $message_data['unique_sign'],
                    'uid' => $message_data['uid']
                ];
                Gateway::sendToGroup($message_data['group'], json_encode($data));
//               Gateway::sendToAll(json_encode($data));
                return;
            case "first":
                Gateway::joinGroup($client_id, $message_data['group']);
//                if($message_data['uid'] ==0){
//                    return;
//                }
                $allClient = Gateway::getClientSessionsByGroup($message_data['group']);
                $data = [
                    'type' => 'first',
                    'count' => count($allClient) + $message_data['fake_num']
                ];
                $_SESSION['sent_name'] = $message_data['sent_name'];
                $_SESSION['uid'] = $message_data['uid'];
                $_SESSION['lid'] = $message_data['lid'];
                $_SESSION['fake_num'] = $message_data['fake_num'];
//                self::$sent_name = $message_data['sent_name'];
//                self::$uid = $message_data['uid'];
//                self::$lid = $message_data['lid'];
                //保存在线记录证明
                Cache::set('uid_'.$message_data['uid'],array(
                    'name' => $message_data['sent_name'],
                    'lid' => $message_data['lid']
                ));

                $redis = new Redis();
                $redis->connect('127.0.0.1',6379);
                //保存当前直播间在线数
                $redis->set('room_'.$message_data['lid'].'_online_count',count($allClient));

                //保存到在线会员列表
                $data_other = array(
                    'uid' => $message_data['uid'],
                    'name' => $message_data['sent_name']
                );
                $redis->sAdd('room_'.$message_data['lid'].'_online_list',json_encode($data_other));

                Gateway::sendToGroup($message_data['group'], json_encode($data));

                return;
            case 'del':
                $data_del = array(
                    'type' => 'del',
                    'unique_sign' => $message_data['unique_sign']
                );
                Gateway::sendToGroup($message_data['group'], json_encode($data_del));

                return;
        }


    }
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
//        Gateway::sendToClient($client_id,json_encode(['status'=>"success",'msg'=>"连接成功"]));
    }
    /**
     * 当连接断开时触发的回调函数
     * @param $connection
     */
    public static function onClose($client_id){
        //删除该会员在线证明
        Cache::rm('uid_'.$_SESSION['uid'] );

        //删除该会员所在直播间的在线会员列表
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);
        $data_del = array(
            'uid' => $_SESSION['uid'] ,
            'name' => $_SESSION['sent_name']
        );
        $redis->sRem('room_'.$_SESSION['lid'].'_online_list',json_encode($data_del));

        //广播给在当前直播间别的在线用户，该用户下线了
        $allClient = Gateway::getClientSessionsByGroup('room_'.$_SESSION['lid']);
        $data = [
            'type' => 'bye',
            'count' => count($allClient) + $_SESSION['fake_num']
//            'delete_id' => $_SESSION['uid']
        ];
        Gateway::sendToGroup('room_'.$_SESSION['lid'], json_encode($data));
    }
    /**
     * 当客户端的连接上发生错误时触发
     * @param $connection
     * @param $code
     * @param $msg
     */
    public static function onError($client_id, $code, $msg){
//        echo "error $code $msg\n";
    }
    /**
     * 每个进程启动
     * @param $worker
     */
    public static function onWorkerStart($worker){

    }
}