<?php
/**
 * Created by IntelliJ IDEA.
 * User: yuanchang
 * Date: 2018/2/12
 * Time: 下午7:31
 */

namespace Services\UserService;


class UserServiceHandler implements UserServiceIf
{
    /**
     * 获取用户信息
     * @param $uidList
     * @return array
     */
    private function getUserByUidArr($uidList)
    {
        $allUser = array(
            1 => array(
                'uid' => 1,
                'name' => '用户NO1',
                'sex' => 1,
                'age' => 18,
                'nick' => '用户昵称1'
            ),
            2 => array(
                'uid' => 2,
                'name' => '用户NO2',
                'sex' => 2,
                'age' => 19,
                'nick' => '用户昵称2'
            ),
        );

        $userArr = array();
        foreach ($uidList as $uid) {
            isset($allUser[$uid]) && $userArr[] = $allUser[$uid];
        }

        return $userArr;
    }

    /**
     * @param \Services\UserService\UserListReq $req
     * @return \Services\UserService\UserListResp
     * @throws \Services\UserService\ApiErrorException
     */
    public function userList(\Services\UserService\UserListReq $req)
    {
        try {
            $uidList = $req->uidList;
            if (empty($uidList)) {
                throw new \Exception('参数错误', \Services\UserService\ErrCodeEnum::PARAM_ERROR);
            }
            $userArr = $this->getUserByUidArr($uidList);
            if (empty($userArr)) {
                throw new \Exception('服务器错误', \Services\UserService\ErrCodeEnum::SERVER_ERROR);
            }

            $list = array();
            foreach ($userArr as $user) {
                $userInfo = new \Services\UserService\UserInfo($user);
                $list[] =  $userInfo;
            }

            $userList['lists'] = $list;
            $result = new \Services\UserService\UserListResp($userList);
            return $result;
        } catch (\Exception $e) {
            $errInfo = array(
                'errCode' => $e->getCode(),
                'errMsg' => $e->getMessage()
            );
            throw new \Services\UserService\ApiErrorException($errInfo); // 抛出自定义错误
        }
    }
}