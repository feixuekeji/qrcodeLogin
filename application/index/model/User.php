<?php


namespace app\index\model;


use think\Model;

class User extends Model
{
    /**添加用户
     * @param $data
     * @return int|string
     */
    public function addUser($data)
    {
        $saveData = array(
          'nickname' =>   $data['nickname'],
            'avatar' => $data['avatar'],
            'gender' => $data['gender'],
            'openid' => $data['openid'],
            'create_time' => time()
        );
        $id = $this->insertGetId($saveData);
        return $id;
    }


    /**
     * 获取用户信息
     * @param $openid
     * @return array|\PDOStatement|string|Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getInfoByOpenId($openid)
    {
        $res = $this->where('openid',$openid)
            ->find();
        return $res;
    }


}
