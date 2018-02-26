<?php

/**
 * GooglePay.php
 *
 * @author  Mr.litt<MMr.litt@gmail.com>
 * @date    18-02-24
 */

class GooglePay
{
    /** @var PDO */
    private $pdo;

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
     * rsa公钥校验 @see https://developer.android.com/google/play/billing/billing_integrate.html#billing-security
     * google api 校验 sdk @see https://developers.google.com/api-client-library/
     * google api 接口 api @see https://developers.google.com/android-publisher/api-ref/purchases/products
     *
     * @return mixed
     */
    public function notify()
    {
        $uid = $_REQUEST['uid'];
        $mParsedJson = $_REQUEST['mParsedJson']; // 订单信息
        $mSignature = $_REQUEST['mSignature']; // 签名

        try {

            // 参数检测
            if (empty($mParsedJson) || empty($mSignature) || empty($uid)) {
                throw new Exception('参数错误', -1);
            }

            // rsa公钥校验
            $google_public_key = ''; // 在应用台获取公钥
            $public_key = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($google_public_key, 64, "\n") . "-----END PUBLIC KEY-----";
            $public_key_handle = openssl_get_publickey($public_key);
            $result = openssl_verify($mParsedJson, base64_decode($mSignature), $public_key_handle, OPENSSL_ALGO_SHA1);
            if ($result !== 1) { // 验证未通过
                throw new Exception("无效订单", -2);
            }

            // 获取上传的订单数据
            $purchaseData = json_decode($mParsedJson, true);
            if (empty($purchaseData) || empty($purchaseData['orderId']) || empty($purchaseData['productId'])) {
                throw new Exception("订单数据错误", -3);
            }
            $transactionId = $purchaseData['orderId'];
            $productId = $purchaseData['productId'];

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