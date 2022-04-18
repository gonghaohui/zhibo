<?php
namespace app\index\controller;

use Redis;
use think\Db;


class Live extends PcBase
{

    public function index(){

        $lid = input('lid');

        $res = Db::name('live_house')->find($lid);
        if(empty($res)){
            echo "没有该直播间";exit;
        }

        //如果直播间没有开启
        if($res['status'] == 0){
            $res['live_source'] = '';
        }


        $this->assign('lid',$lid);
        $this->assign('data',$res);

        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);
        $online_count = $redis->get('room_'.$lid.'_online_count');
        $online_count = $online_count?$online_count:1;
        $this->assign('online_count',$online_count);
        return $this->fetch('live/index');
    }

    public function enter_house(){
        $lid = input('lid');
        if(!$lid){
            $houseData = Db::name('live_house')->where('status',1)->order('lid','asc')->find();
            if(!empty($houseData)){
                $lid = $houseData['lid'];
            }else{
                $lid = 0;
            }
        }

        $this->assign('lid',$lid);
        return $this->fetch('live/enter_house');
    }

    public function check_house_pwd(){
        $lid = input('lid',0);
        $pwd = input('pwd');
        $res = Db::name('live_house')->where('lid',$lid)->find();
        if($lid == 0){
            return json(['code'=>100,'info'=>'error']);
        }
        if(empty($res)){
            return json(['code'=>100,'info'=>'没有该直播间']);
        }
        if($res['status'] == 0){
            return json(['code'=>100,'info'=>'该直播间已关闭']);
        }
        if($pwd == $res['live_pwd']){
            return json(['code'=>200,'lid'=>$lid]);
        }else{
            return json(['code'=>100,'info'=>'密码错误']);
        }

    }

    /**
     * 检测直播间状态
     * @return \think\response\Json
     */
    public function check_zhibo_status(){
        if(request()->isPost()){
            $lid = input('lid');
            if(!$lid){
                return json(['code'=>100,'info'=>'error']);
            }

            $res = Db::name('live_house')->where('lid',$lid)->find();
            if(empty($res)){
                return json(['code'=>100,'info'=>'none house']);
            }
            if($res['status'] == 0){
                //直播已关闭
                return json(['code'=>300,'info'=>'','url'=>'','online_user'=> 1]);

            }else{
                //正在直播当中
//                $times = Cache::get('times');
//                if(!$times){
//                    $times = 1;
//                }

//                $online_num = $this->online_info_save_redis($lid)*$times;
                return json(['code'=>200,'info'=>'','url'=>$res['live_source'],'online_num'=> 1]);
            }
        }
    }

    /**
     * 保存用户发送的消息到redis,并调用禁词，返回处理后的消息
     * @return \think\response\Json
     */
    public function save_message(){
        if(request()->isPost()){
            $lid = input('lid');
            $message = input('message');
            $username = input('username');
            $uid = input('uid');

            $user = Db::name('users')->where('uid',$uid)->find();
            if($user['chat_status'] == 0){
                //已禁言
                return json(['code'=>300,'msg'=>'你已经被禁言了！','chat_status'=>0]);
            }else{
                $redis = new Redis();
                $redis->connect('127.0.0.1',6379);
                //消息禁词处理
                $forbiddenWords = $redis->get('forbidden_words');
                if($forbiddenWords){
                    $words = json_decode($forbiddenWords,true);
                    foreach ($words as $word){
                        $word_len = mb_strlen($word);
                        switch ($word_len){
                            case 1:
                                $stars = '*';
                                break;
                            case 2:
                                $stars = '**';
                                break;
                            case 3:
                                $stars = '***';
                                break;
                            case 4:
                                $stars = '****';
                                break;
                            case 5:
                                $stars = '*****';
                                break;
                            default:
                                $stars = '**';
                        }
                        $message = str_replace($word,$stars,$message);
                    }
                }

                $time = time();
                $unique_sign = 's'.$time.$uid;
                $data = array(
                    'type' => 1,  //0:admin 1:会员
                    'message' => $message,
                    'name' => $username,
                    'unique_sign' => $unique_sign,
                    'time' => date("Y-m-d H:i:s",$time)
                );
                $string = json_encode($data);
                $redis->rPush('room_'.$lid.'_message',$string);

                return json(['code'=>200,'unique_sign'=>$unique_sign,'message'=>$message]);
            }

        }
    }

    /**
     * 保存当前直播间的在线人数
     */
    public function save_room_online_count(){
        if(request()->isPost()){
            $lid = input('lid');
            $count = input('count');
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->set('room_'.$lid.'_online_count',$count);
            return json(['code'=>200]);
        }
    }

    /**
     * 获取当前房间的聊天记录
     * @return \think\response\Json
     */
    public function get_message_list(){
        if(request()->isPost()){
            $lid = input('lid');
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $messages = $redis->lRange('room_'.$lid.'_message',0,-1);
//            print_r($messages);

            return json(['code'=>200,'messages'=>$messages]);
        }
    }

    /**
     * 加载当前直播间会员列表
     */
    public function load_users(){
        if(request()->isPost()){
            $lid = input('lid');
            $total_num = input('num');
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $userList = $redis->sMembers('room_'.$lid.'_online_list');
            $fakeUserList = array();

            $realOnlineUserCount = count($userList);
            //前台最多显示100个
            if($realOnlineUserCount < 100){
                $left_num = $total_num - $realOnlineUserCount;
                if($left_num > 0){
                    $userString = $redis->get('fake_room_'.$lid.'_user_list');
                    $fakeUserList = explode(',',$userString);

                }
            }

            return json(['code'=>200,'userList'=>$userList,'fakeUserList'=>$fakeUserList]);
        }

    }

    /**
     * 自动创建用户
     * @return \think\response\Json
     */
    public function auto_create_new_user(){
        $userSign = $this->create_rand_user_sign();
        //入库
        $inputData = array(
            'name' => $this->getChar(rand(2,5)),
            'explorer_key' => $userSign,
            'ip' => $this->get_real_ip(),
            'create_time' => time());
        $uid = Db::name('users')->insertGetId($inputData);
        $json = json_encode(
            array(
                'uid' => $uid,
                'name' => $inputData['name'],
                'sign' => $userSign
            )
        );
        return json(['code'=>200,'sign'=>$json]);

    }

    /**
     * 随机生成汉字
     * @return string
     */
    private function getChar($num)  // $num为生成汉字的数量
    {
        $b = '';
        for ($i=0; $i<$num; $i++) {
            // 使用chr()函数拼接双字节汉字，前一个chr()为高位字节，后一个为低位字节
            $a = chr(mt_rand(0xB0,0xD0)).chr(mt_rand(0xA1, 0xF0));
            // 转码
            $b .= iconv('GB2312', 'UTF-8', $a);
        }
        return $b;
    }

    /**
     * 生成用户唯一标识
     * @return string
     */
    private function create_rand_user_sign(){

        $length = rand(5,9);
        $str='abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len=strlen($str)-1;
        $randstr='';
        for($i=0;$i<$length;$i++){
            $num=mt_rand(0,$len);
            $randstr .= $str[$num];
        }
        return md5($randstr);
    }

    /**
     * 获取用户ip
     * @return string
     */
    private function get_real_ip(){
        if(!empty($_SERVER['HTTP_CLIENT_IP'])){
            $cip = $_SERVER['HTTP_CLIENT_IP'];
        }
        else if(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $cip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        }
        else if(!empty($_SERVER["REMOTE_ADDR"])){
            $cip = $_SERVER["REMOTE_ADDR"];
        }else{
            $cip = '';
        }
        preg_match("/[\d\.]{7,15}/", $cip, $cips);
        $cip = isset($cips[0]) ? $cips[0] : 'unknown';
        unset($cips);
        return $cip;
    }


}