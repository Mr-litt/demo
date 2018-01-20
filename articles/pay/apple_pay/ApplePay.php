<?php

/**
 * ApplePay.php
 *
 * @author  Mr.litt<MMr.litt@gmail.com>
 * @date    18-1-20
 */

class ApplePay
{
    /** @var PDO */
    private $pdo;

    const APPLE_PAY_SANDBOX_URL = 'https://sandbox.itunes.apple.com/verifyReceipt';
    const APPLE_PAY_URL = 'https://buy.itunes.apple.com/verifyReceipt';
    const CLIENT_BUNDLE_ID = 'com.test.ios.live';

    /**
     * 获取商品列表
     * @return array
     */
    public function goods()
    {
        $sql = 'SELECT * FROM `goods`';
        $pdo = $this->pdo;
        $stmt = $pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $list;
    }

    /**
     * 支付回调（客户端发起）
     * @return mixed
     */
    public function notify()
    {
        $receipt = $_REQUEST['receipt'];
        $uid = $_REQUEST['uid'];

        try {

            // 参数检测
            if (empty($receipt) || empty($uid)) {
                throw new Exception('参数错误', -1);
            }

            // 确定校验模式(提审的时候是使用沙盒模式)
            $isSandBox = true; //是否使用沙盒模式，比如审核的时候用沙盒模式
            if ($isSandBox) {
                $endpoint = self::APPLE_PAY_SANDBOX_URL; // 'https://sandbox.itunes.apple.com/verifyReceipt';
            } else {
                $endpoint = self::APPLE_PAY_URL; // 'https://buy.itunes.apple.com/verifyReceipt';
            }

            // 请求校验
            $postData = json_encode(array('receipt-data' => $receipt));
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20); //超时时间
            curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);  //这两行一定要加，不加会报SSL 错误
            curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno) {
                throw new Exception($error, -2);
            }
            $result = json_decode($response, true);

            if (empty($result) || $result['status'] != 0) {
                throw new Exception('订单验证失败', -3);
            }

            // 判断是否是本应用订单，防止跨app攻击
            $bundleId = $result['receipt']['bundle_id'];
            if ($bundleId != self::CLIENT_BUNDLE_ID) {
                throw new Exception('非本应用订单', -4);
            }

            $allOrder = $result['receipt']['in_app'];
            if (empty($allOrder)) {
                throw new Exception('订单列表为空', -5);
            }

            $newOrder = end($allOrder);
            $transactionId = $newOrder['transaction_id'];
            $productId = $newOrder['product_id'];

            // 判断是否存在订单，防止重复验证
            $pdo = $this->pdo;
            $sql = 'select * from `order` where transaction_id = ? limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('transaction_id' => $transactionId));
            if ($order = $stmt->fetch(PDO::FETCH_ASSOC) !== false) {
                throw new Exception('重复验证', 1);
            }

            // 获取商品详细信息用于业务处理
            $sql = 'SELECT * FROM `goods` where goods_id = ? limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('goods_id' => $productId));
            if ($good = $stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new Exception('商品信息错误', -6);
            }

            // 插入订单表
            $sql = 'insert into `order` (`transaction_id`, `uid`, `price`, `goods_id`, `goods_money`) value (?, ?, ?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array($transactionId, $uid, $good['price'], $good['goods_id'], $good['goods_money']));
            if (empty($result)) {
                throw new Exception('插入订单失败', -7);
            }

            // 修改用户余额
            $sql = 'update FROM `user` set money = ? where uid = ?';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array($good['goods_money'], $uid));
            if (empty($result)) {
                throw new Exception('修改余额错误', -8);
            }

            $return['code'] = 0;
            $return['msg'] = '支付成功';
        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }

        return $return;
    }
}