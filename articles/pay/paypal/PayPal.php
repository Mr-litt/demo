<?php

/**
 * PayPal.php
 *
 * @author  Mr.litt<MMr.litt@gmail.com>
 * @date    18-1-20
 */

class PayPal
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
     * 创建订单
     * @return mixed
     */
    public function createOrder()
    {
        $uid = $_REQUEST['uid'];
        $goodsId = $_REQUEST['goodsId'];
        try {

            // 判断参数
            if (empty($uid) || empty($goodsId)) {
                throw new Exception('参数错误', -1);
            }

            // 查询商品信息
            $pdo = $this->pdo;
            $sql = 'SELECT * FROM `goods` WHERE `goods_id` = ? LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array($goodsId));
            if ($goods = $stmt->fetch(PDO::FETCH_ASSOC) === false) {
                throw new Exception('商品信息错误', -2);
            }

            // 生成订单号插入订单表
            $transactionId = uniqid();
            $sql = 'insert into `order` (`transaction_id`, `uid`, `price`, `goods_id`, `goods_money`, `status`) value (?, ?, ?, ?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute(array($transactionId, $uid, $goods['price'], $goods['goods_id'], $goods['goods_money'], 0));
            if (empty($result)) {
                throw new Exception('插入订单失败', -3);
            }
            $return['transactionId'] = $transactionId;

            // 返回数据
            $return['data'] = $return;
            $return['code'] = 0;
            $return['msg'] = '支付成功';
        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }

        return $return;
    }

    /**
     * PayPal通过即时付款通知 (IPN)机制通知
     * 即时付款通知 @see https://www.paypal.com/en/cgi-bin/webscr?cmd=p/acc/ipn-info-outside
     * @return bool
     */
    public function notify()
    {
        try {

            // 判断环境
            define("USE_SANDBOX", true); // 是否使用沙盒环境
            if (USE_SANDBOX) {
                $endpoint = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
                $receiverEmail = array( // 收款邮箱
                    'MMr.litt-test@gmail.com'
                );
            } else {
                $endpoint = 'https://www.paypal.com/cgi-bin/webscr';
                $receiverEmail = array(
                    'MMr.litt@gmail.com'
                );
            }

            // 获取PayPal通过IPN机制通知的订单数据
            $input = file_get_contents("php://input");
            if (empty($input)) {
                throw new Exception("获取内容为空", -1);
            }

            // 添加验证命令回调PayPal验证订单是否合法
            $cmd = 'cmd=_notify-validate';
            $postData = $cmd . "&" .$input;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $output = curl_exec($ch);
            if(curl_errno($ch)) {
                throw new Exception("CURL请求失败，错误码：" . curl_error($ch), -2);
            }

            // 判断验证订单是否合法
            if (strcmp ($output, "VERIFIED") == 0) { // 验证通过
                parse_str($postData, $postDataArr);

                // 检查支付状态（payment_status）是否是完成（Completed）
                if ($postDataArr['payment_status'] != 'Completed') {
                    throw new Exception("订单状态未完成");
                }

                // 检查收款账号是否自己的账号
                if (!in_array($postDataArr['receiver_email'], $receiverEmail)) {
                    throw new Exception("收款账号不是自己的账号");
                }

                // 获取订单信息
                $pdo = $this->pdo;
                $sql = 'select * from `order` where transaction_id = ? limit 1';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array('transaction_id' => $postDataArr['invoice']));
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($order === false) {
                    throw new Exception('订单不存在', -3);
                }

                // 判断订单是否支付完成(支付完成直接返回成功)
                if ($order['status'] == 1) {
                    return true;
                }

                // 修改订单表
                $sql = 'update `order` set `status` = ? where `transaction_id` = ?';
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute(array(1, $order['transaction_id']));
                if (empty($result)) {
                    throw new Exception('订单修改失败', -4);
                }

                // 修改用户余额
                $sql = 'update FROM `user` set money = ? where uid = ?';
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute(array($order['goods_money'], $order['uid']));
                if (empty($result)) {
                    throw new Exception('修改余额错误', -5);
                }

                return true;
            } else if (strcmp ($output, "INVALID") == 0) { // 验证失败
                throw new Exception("验证失败", -6);
            } else {
                throw new Exception("未知错误，返回内容：" . $output, -7);
            }
        } catch (Exception $e) {
            // 记录日志todo...
            // 返回http状态400，PayPal收到非200状态码会重试
            header('HTTP/1.1 400');
            return false;
        }
    }

    /**
     * 客户端使用，查看订单是否处理成功
     * @return mixed
     */
    public function orderStatus()
    {
        $transactionId = $_REQUEST['transaction_id'];

        try {
            // 判断参数
            if (empty($transactionId)) {
                throw new Exception('参数错误', -1);
            }

            // 获取订单信息
            $pdo = $this->pdo;
            $sql = 'select * from `order` where transaction_id = ? limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('transaction_id' => $transactionId));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order === false) {
                throw new Exception('订单不存在', -2);
            }

            // 查看订单状态
            if ($order['status'] != 1) {
                throw new Exception('订单正在处理', -4);
            }

            $return['code'] = 0;
            $return['msg'] = '订单处理成功';
        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }

        return $return;
    }

}