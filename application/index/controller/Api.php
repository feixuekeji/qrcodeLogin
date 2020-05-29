<?php
namespace app\index\controller;

use app\index\model\User;
use think\facade\Cache;
use think\response\Redirect;
use unit\Token;
use unit\Wechat;

class Api
{
    public function __construct(Redirect $request)
    {
        if (!empty($_GET['echostr'])){
            $wechat = new Wechat();
            if ($wechat->checkSignature())
                header('content-type:text');
            echo $_GET['echostr'];
            exit;
        }
    }

    public function index()
    {
        $wechat = new Wechat();
        $app = $wechat->app;
        $app->server->push(function ($message) {
            switch ($message['MsgType']) {
                case 'event':
                    return $this->eventMessage($message);
                    break;
                case 'text':
                    return '收到文字消息';
                    break;
                case 'image':
                    return '收到图片消息';
                    break;
                case 'voice':
                    return '收到语音消息';
                    break;
                case 'video':
                    return '收到视频消息';
                    break;
                case 'location':
                    return '收到坐标消息';
                    break;
                case 'link':
                    return '收到链接消息';
                    break;
                case 'file':
                    return '收到文件消息';
                // ... 其它消息
                default:
                    return '收到其它消息';
                    break;
            }

            // ...
        });
        $response = $app->server->serve();
        $response->send();
    }

    public function eventMessage($message)
    {
        switch ($message['Event']) {
            case 'subscribe':
                if (!empty($message['EventKey'])){
                    $message['EventKey'] = substr($message['EventKey'],8);
                    $res = $this->loginEvent($message);
                }
                return '欢迎关注';
                break;
            case 'SCAN':
                return  $this->loginEvent($message);
                break;
            case 'unsubscribe':
                return '欢迎关注';
                break;
            case 'CLICK':
                return '点击';
                break;
            default:
                return '收到其他事件';
                break;

        }
    }

    public function loginEvent($message)
    {
        $uid = $message['EventKey'];
        $userId = $this->addUser($message['FromUserName']);
        $jwtToken = new Token();
        $tokenData = [
            'userId' => $userId,
        ];
        $token = $jwtToken->createToken($tokenData,24 * 60 * 60)['token'];
        $timeOut =  Cache::store('default')->get('login'.$uid);
        if (empty($timeOut))
            return '二维码过期，请重新登录';
        $data = array(
            'uid' => $uid,
            'token' => $token,
        );
        $res = $this->sendSocket($data);
        if ($res)
            return '登陆成功';
        else
            return '登陆异常';

    }

    public function addUser($openid)
    {
        $userModel = new User();
        $userInfo = $userModel->getInfoByOpenId($openid);
        if (empty($userInfo)){
            $wechat = new Wechat();
            $detail = $wechat->app->user->get($openid);
            $data = [
                'openid' => $openid,
                'nickname' => $detail['nickname'] ?? '',
                'gender' => $detail['sex'] ?? '',
                'avatar' => $detail['headimgurl'] ?? '',
            ];
            $userId = $userModel->addUser($data);
        } else {
            $userId = $userInfo->id;
        }
        return $userId;

    }

    public function sendSocket($data)
    {
        try {
            $client = stream_socket_client('tcp://127.0.0.1:5678', $errno, $errmsg, 1);

            fwrite($client, json_encode($data) . "\n");

            $res = fread($client, 8192);
            fclose($client);
            $res = json_decode($res, true);
            if ($res['error'] == 0)
                return true;
            else
                return false;

        } catch (\Exception $e) {
            return false;
        }

    }

}
