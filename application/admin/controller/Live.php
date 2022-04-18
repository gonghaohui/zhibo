<?php

namespace app\admin\controller;

use app\admin\model\LiveForbiddenWordsModel;
use app\admin\model\LiveHouseModel;
use think\Db;
use Redis;

class Live extends Base
{

    /**
     * 直播房间列表
     */
    public function index(){
        $web = Db::name('value')->find(1);
        if(request()->isAjax ()){
            extract(input());
            $map = [];
            if(isset($lid) && $lid!=""){
                $map['lid'] = $lid;
            }
            if(isset($live_name)&&$live_name!=""){
                $map['live_name'] = ['like',"%" . $live_name . "%"];
            }
            if(isset($status) && $status!=""){
                $map['status'] = $status;
            }
            $Nowpage = input('get.page') ? input('get.page'):1;
            $limits = input("limit")?input("limit"):10;
            $count = Db::name('live_house')->where($map)->count();//计算总页面
            $liveHouseModel = new LiveHouseModel();
            $od="lid asc";
            $lists = $liveHouseModel->getDatasByWhere($map, $Nowpage, $limits,$od);
//            print_r($lists);
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            foreach ($lists as $key => $list){
                $num = $redis->get('room_'.$list['lid'].'_online_count');
                $lists[$key]['online_num'] = $num?$num:0;
                $lists[$key]['live_link'] = $web['value'].'/live/'.$list['lid'].'.html';
            }

            return json(['code'=>220,'msg'=>'','count'=>$count,'data'=>$lists]);
        }
        $this->assign('website',$web['value']);
        return $this->fetch("live/index");


    }

    /**
     * 添加直播间
     */
    public function liveHouseAdd(){
        if(request()->isPost()){
            extract(input());
            $param = input('post.');
            $param['create_time'] = time();
            $liveHouseModel = new LiveHouseModel();
            $flag = $liveHouseModel->insertLiveHouse($param);
            if($flag['code'] == 200){
                //生成假在线用户列表，最多99个，因为前台列表最多显示100个会员
                $redis = new Redis();
                $redis->connect('127.0.0.1',6379);
                $create_num = $param['fake_online_user_num'] > 99 ? 99 : $param['fake_online_user_num'];
                if($create_num > 0){
                    $string = '';
                    for($i=0;$i<$create_num;$i++)
                    {
                        $string = $string.$this->getChar(rand(2,5)).',';
                    }
                    $string = rtrim($string,',');
                    $redis->set('fake_room_'.$flag['lid'].'_user_list',$string);
                }
            }
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch('live/livehouseadd');
    }

    /**
     * [live_house_state 房间状态]
     */
    public function live_house_state(){
        extract(input());
        $liveHouseModel = new LiveHouseModel();
        $flag = $liveHouseModel->houseState($id,$num);

        if($num == 0){
            //关闭直播间时删除该直播间的聊天记录
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $total_message_num = $redis->lLen('room_'.$id.'_message');
            if($total_message_num != 0){
                for ($i=0;$i<$total_message_num;$i++){
                    $redis->lPop('room_'.$id.'_message');
                }
            }
        }

        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * 删除房间
     */
    public function del_house(){
        $lid = input('id');

        $res = Db::name('live_house')->find($lid);
        if($res['status'] == 1){
            return json(['code' => 100, 'data' => '', 'msg' => '删除该房间前请先禁用该直播间']);
        }


        $result = Db::name('live_house')->delete($lid);
        if($result){
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->del('fake_room_'.$lid.'_user_list');
            return json(['code' => 200, 'data' => '', 'msg' => '删除成功']);
        }else{
            return json(['code' => 100, 'data' => '', 'msg' => '删除失败']);
        }
    }

    /**
     * 修改房间信息
     */
    public function edit_house(){
        if(request()->isPost()){
            $param = input();
            $liveHouseModel = new LiveHouseModel();
            $res = $liveHouseModel->update($param);
            if($res){
                //生成假在线用户列表，最多99个，因为前台列表最多显示100个会员
                $redis = new Redis();
                $redis->connect('127.0.0.1',6379);
                $create_num = $param['fake_online_user_num'] > 99 ? 99 : $param['fake_online_user_num'];
                if($create_num > 0){
                    $string = '';
                    for($i=0;$i<$create_num;$i++)
                    {
                        $string = $string.$this->getChar(rand(2,5)).',';
                    }
                    $string = rtrim($string,',');
                    $redis->set('fake_room_'.$param['lid'].'_user_list',$string);
                }
                if($create_num == 0){
                    $redis->del('fake_room_'.$param['lid'].'_user_list');
                }

                return ['code' => 200, 'data' => '', 'msg' => '修改成功'];
            }else{
                return ['code' => 100, 'data' => '', 'msg' => '修改失败'];
            }
        }
        $lid = input('id');
        $data = Db::name('live_house')->find($lid);
        $this->assign('data',$data);
        return $this->fetch('live/live_house_edit');
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
     * 管理直播间
     */
    public function manage_house(){
        $lid = input('id');
        $house = Db::name('live_house')->find($lid);
        $this->assign('house',$house);
        return $this->fetch('live/live_house_manage');
    }


    /**
     * 聊天禁词列表
     */
    public function forbidden(){

        if(request()->isAjax ()){
            extract(input());
            $map = [];
            if(isset($fid) && $fid!=""){
                $map['fid'] = $fid;
            }
            if(isset($word)&&$word!=""){
                $map['word'] = ['like',"%" . $word . "%"];
            }
            if(isset($status) && $status!=""){
                $map['status'] = $status;
            }
            $Nowpage = input('get.page') ? input('get.page'):1;
            $limits = input("limit")?input("limit"):10;
            $count = Db::name('forbidden_words')->where($map)->count();//计算总页面
            $forbiddenWordsModel = new LiveForbiddenWordsModel();
            $od="fid desc";
            $lists = $forbiddenWordsModel->getDatasByWhere($map, $Nowpage, $limits,$od);

            return json(['code'=>220,'msg'=>'','count'=>$count,'data'=>$lists]);
        }

        return $this->fetch("live/forbidden");

    }

    /**
     * editField 快捷编辑
     * @return \think\response\Json
     */
    public function editField(){
        extract(input());

        $res = Db::name($table)->where(['fid' => $id ])->setField($field , $value);
        if($res){
            writelog('更新字段成功',200);
            return json(['code' => 200,'data' => '', 'msg' => '更新字段成功']);
        }else{
            writelog('更新字段失败',100);
            return json(['code' => 100,'data' => '', 'msg' => '更新字段失败']);
        }
    }

    /**
     * 删除禁词
     */
    public function del_word(){
        $fid = input('id');

        $result = Db::name('forbidden_words')->delete($fid);
        if($result){
            $this->save_words_to_redis();
            return json(['code' => 200, 'data' => '', 'msg' => '删除成功']);
        }else{
            return json(['code' => 100, 'data' => '', 'msg' => '删除失败']);
        }
    }

    /**
     * [forbidden_word_state 禁词状态]
     */
    public function forbidden_word_state()
    {
        extract(input());
        $forbiddenWordsModel = new LiveForbiddenWordsModel();
        $flag = $forbiddenWordsModel->wordState($id,$num);
        if($flag['code'] == 200){
            $this->save_words_to_redis();
        }
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

    /**
     * [forbiddenWordAdd 添加禁词]
     */
    public function forbiddenWordAdd()
    {
        if(request()->isPost()){
            extract(input());
            $param = input('post.');
            $forbiddenWordsModel = new LiveForbiddenWordsModel();
            $flag = $forbiddenWordsModel->insertWord($param);
            if($flag['code'] == 200 and $status == 1){
                $this->save_words_to_redis();
            }
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        return $this->fetch('live/wordadd');
    }

    /**
     * checkForbiddenWord 验证禁词的唯一性
     */
    public function checkForbiddenWord(){
        extract(input());
        $forbiddenWordsModel = new LiveForbiddenWordsModel();
        $flag = $forbiddenWordsModel->checkForbiddenWord ($word);
        return json(['code' => $flag['code'], 'msg' => $flag['msg']]);
    }

    /**
     * batchDelForbiddenWord 批量删除禁词
     * @return \think\response\Json
     */
    public function batchDelForbiddenWord(){
        extract(input());
        if(empty($ids)){
            return json(['code'=>100,'msg'=>'请选择要删除的禁词！']);
        }
        $ids = explode(',',$ids);
        $ids = array_merge($ids);
        $forbiddenWordsModel = new LiveForbiddenWordsModel();
        $flag = $forbiddenWordsModel->batchDelWord($ids);
        if($flag['code'] == 200){
            $this->save_words_to_redis();
        }
        return json(['code' => $flag['code'], 'msg' => $flag['msg']]);
    }

    /**
     * 把禁词保存在redis中
     */
    private function save_words_to_redis(){
        $redis = new Redis();
        $redis->connect('127.0.0.1',6379);
        $allWordsArray = Db::name('forbidden_words')->where('status',1)->order('fid','ASC')->column('word');
        $redis->set('forbidden_words',json_encode($allWordsArray));
    }

    /**
     * usingForbiddenWord 批量启用禁词
     * @return \think\response\Json
     */
    public function usingForbiddenWord(){
        extract(input());
        if(empty($ids)){
            return json(['code'=>100,'msg'=>'请选择要删除的禁词！']);
        }
        $list = [];
        if($ids){
            $ids = explode(',',$ids);
            for($i=0;$i<count($ids);$i++){
                $param = [
                    'fid'=>$ids[$i],
                    'status'=>1
                ];
                $list[] = $param;
            }
        }
        $forbiddenWordsModel = new LiveForbiddenWordsModel();
        $flag = $forbiddenWordsModel->usingWord($list);
        return json(['code' => $flag['code'], 'msg' => $flag['msg']]);
    }

    /**
     * banForbiddenWord 批量禁用禁词
     * @return \think\response\Json
     */
    public function banForbiddenWord(){
        extract(input());
        if(empty($ids)){
            return json(['code'=>100,'msg'=>'请选择要删除的禁词！']);
        }
        $list = [];
        if($ids){
            $ids = explode(',',$ids);
            $ids = array_merge($ids);
            for($i=0;$i<count($ids);$i++){
                $param = [
                    'fid'=>$ids[$i],
                    'status'=>2
                ];
                $list[] = $param;
            }
        }
        $forbiddenWordsModel = new LiveForbiddenWordsModel();
        $flag = $forbiddenWordsModel->banWord($list);
        return json(['code' => $flag['code'], 'msg' => $flag['msg']]);
    }

    /**
     * 修改前端域名
     */
    public function edit_domain(){
        if(request()->isAjax()){
            $domain = input('domain');
            if($domain == ''){
                return json(['code' => 100, 'msg' => '域名不能为空']);
            }
            $res = Db::name('value')->where('id',1)->update([
                'value' => $domain
            ]);
            if($res){
                return json(['code' => 200, 'msg' => '域名修改成功']);
            }else{
                return json(['code' => 100, 'msg' => 'Error']);
            }
        }
    }

    //----------------------------------------聊天
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

            return json(['code'=>200,'messages'=>$messages]);
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

            $time = time();
            $unique_sign = 's'.$time.$uid;
            $data = array(
                'type' => 0,  //0:admin 1:会员
                'message' => $message,
                'name' => $username,
                'unique_sign' => $unique_sign,
                'time' => date("Y-m-d H:i:s",$time)
            );
            $string = json_encode($data);
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->rPush('room_'.$lid.'_message',$string);

            return json(['code'=>200,'unique_sign'=>$unique_sign,'message'=>$message]);

        }
    }

    /**
     * 删除消息
     */
    public function del_message(){
        if(request()->isPost()){
            $lid = input('lid');
            $type = input('type');
            $message= input('message');
            $name = input('name');
            $unique_sign = input('unique_sign');
            $time = input('time');


            $del_data = array(
                'type' => (int)$type,
                'message' => $message,
                'name' => $name,
                'unique_sign' => $unique_sign,
                'time' => $time,
            );

            echo json_encode($del_data);
            $redis = new Redis();
            $redis->connect('127.0.0.1',6379);
            $redis->lRem('room_'.$lid.'_message',json_encode($del_data),1);

            return json(['code'=>200]);
        }
    }


}