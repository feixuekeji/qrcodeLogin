<?php


namespace app\http;


use think\worker\Server;
use Workerman\Lib\Timer;

class Worker extends Server
{
    protected $socket = 'websocket://0.0.0.0:2346';


    // 进程启动后设置一个每秒运行一次的定时器
    public function onWorkerStart($worker)
    {
        // 心跳间隔55秒
        define('HEARTBEAT_TIME', 55);
        Timer::add(1, function () use ($worker) {
            $time_now = time();
            foreach ($worker->connections as $connection) {
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $time_now;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
                if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
                    $connection->close();
                }
            }
        });


        // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
        $inner_text_worker = new \Workerman\Worker('text://0.0.0.0:5678');
        $inner_text_worker->onMessage = function($connection, $buffer)
        {
            // $data数组格式，里面有uid，表示向那个uid的页面推送数据
            $data = json_decode($buffer, true);
            $uid = $data['uid'];
            // 通过workerman，向uid的页面推送数据
            $ret = $this->sendMessageByUid($uid, $buffer);
            if ($ret){
                $msg['error'] = 0;
                $msg['msg'] = 'success';
            } else {
                $msg['error'] = 1;
                $msg['msg'] = 'error';
            }
            // 返回推送结果
            $connection->send(json_encode($msg));
        };
        // ## 执行监听 ##
        $inner_text_worker->listen();
    }

    public function onMessage($connection,$data)
    {
        global $worker;
        // 判断当前客户端是否已经验证,即是否设置了uid
        if(!isset($connection->uid))
        {
            // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
            $connection->uid = $data;
            /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
             * 实现针对特定uid推送数据
             */
            $worker->uidConnections[$connection->uid] = $connection;
        }
        // 给connection临时设置一个lastMessageTime属性，用来记录上次收到消息的时间
        $connection->lastMessageTime = time();
        var_dump($data);
        echo "\n";
    }


    public function onClose($connection)
    {
        global $worker;
        if (isset($connection->uid)){
            unset($worker->uidConnections[$connection->uid]);
        }
    }


    public function onError($connection,$code,$msg)
    {
        echo "error $code $msg";
    }


    // 针对uid推送数据
    function sendMessageByUid($uid, $message)
    {
        global $worker;
        if(isset($worker->uidConnections[$uid])){
            $connection = $worker->uidConnections[$uid];
            $connection->send($message);
            return true;
        }
        return false;
    }
}
