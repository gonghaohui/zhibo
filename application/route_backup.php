<?php
use think\Route;

Route::domain('127.0.0.2',function(){




    //聊天
    Route::get('one_chat', 'index/chat/one_chat');
    Route::get('group_chat', 'index/chat/group_chat');

    //live
//    Route::get('live', 'index/live/index');
    Route::any('live/:lid', 'index/live/index',[],['lid'=>'\d+']);


    Route::bind('index');
});


Route::domain('127.0.0.3','admin');




