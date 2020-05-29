<?php
namespace app\index\controller;

use app\index\model\User;
use think\facade\Cache;
use think\Request;
use unit\Token;
use unit\Wechat;

class Index
{
    public function index(Request $request)
    {
        $token = $request->param('token','');
        if (!empty($token)) {
            $jwtToken = new Token();
            $checkToken = $jwtToken->checkToken($token);
            $data = (array)$checkToken['data']['data'];
            $uid = $data['userId'] ?? 0;
            $userModel = new User();
            $userInfo = $userModel->get($uid);
            var_dump($userInfo);
        }

    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }

    public function creatQrCode($uid)
    {
        $wechat = new Wechat();
        $app = $wechat->app;
        $result = $app->qrcode->temporary($uid, 60);
        $url = $app->qrcode->url($result['ticket']);
        return $url;
    }

    public function getQrCode()
    {
        $uid = make_uid();
        $url = $this->creatQrCode($uid);
        Cache::store('default')->set('login'.$uid,1,600);
        $data = array(
            'uid' => $uid,
            'url' => $url,
        );
        return view('login',$data);
    }
}
