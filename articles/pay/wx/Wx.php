<?php

/**
 * @link https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1445241432 公众号开发文档
 * @link https://pay.weixin.qq.com/wiki/doc/api/index.html 支付文档
 * Class Wx
 */
class Wx
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
            $data['transactionId'] = $transactionId;

            // 返回数据
            $return['data'] = $data;
            $return['code'] = 0;
            $return['msg'] = '支付成功';
        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }

        return $return;
    }

    /**
     * 根据订单号统一下单
     * @return mixed
     */
    public function pay()
    {
        $payType = $_REQUEST['payType'];
        $transactionId = $_REQUEST['transactionId'];
        try {

            $payTypeArr = [
                'APP' => [
                    'body' => '商家名称-APP支付',
                    'tradeType' => 'APP',
                ],
                'JSAPI' => [
                    'body' => '商家名称-公众号支付',
                    'tradeType' => 'JSAPI',
                ],
                'MWEB' => [
                    'body' => '商家名称-H5支付',
                    'tradeType' => 'MWEB',
                ]
            ];

            // 判断参数
            if (empty($payType) || !in_array($payType, array_keys($payTypeArr)) || empty($transactionId)) {
                throw new Exception('参数错误', -1);
            }

            // 获取订单信息
            $pdo = $this->pdo;
            $sql = 'select * from `order` where transaction_id = ? limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('transaction_id' => $transactionId));
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($order === false) {
                throw new Exception('订单不存在', -3);
            }
            if ($order['status'] == 1) {
                throw new Exception('订单已经完成', -3);
            }

            // 请求微信统一下单接口
            $input = new WxPayUnifiedOrder();
            $input->SetBody($payTypeArr[$payType]['body']); // 商品简单描述
            $input->SetOut_trade_no($transactionId); // 商户系统内部订单号
            $input->SetTotal_fee($order['price'] * 100); //订单总金额，单位为分
            $input->SetNotify_url($this->getHost() . "/wx/notify"); // 异步接收微信支付结果通知的回调地址
            $input->SetTrade_type($payTypeArr[$payType]['type']); //交易类型
            if ($payType == 'JSAPI') {
                $input->SetOpenid($this->getOpenId()); // 微信公众号支付需要提供openId
            }
            $result = WxPayApi::unifiedOrder($input);

            $data = array();
            if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS' ) {
                if ($payType == 'APP') {
                    // 生成签名供客户端调用微信服务器进行支付
                    // 参与签名字段：appid，partnerid，prepayid，noncestr，timestamp，package
                    // SDK没有提供APP支付生成签名，自己按照规则生成签名
                    $time = time();
                    $nonceStr = WxPayApi::getNonceStr();

                    // 参加签名字符串
                    $signArr = array(
                        'appid' => WxPayConfig::APPID,
                        'partnerid' => WxPayConfig::MCHID,
                        'prepayid' => $result['prepay_id'],
                        'noncestr' => $nonceStr,
                        'timestamp' => $time,
                        'package' => 'Sign=WXPay'
                    );

                    $sign = $this->makeSign($signArr);
                    $signArr['sign'] = $sign;

                    $data = $signArr;
                } else if ($payType == 'JSAPI') {
                    // 生成签名供客户端调用微信服务器进行支付
                    // 参与签名字段：appId, timeStamp, nonceStr, package, signType
                    $tools = new JsApiPay();
                    try {
                        $jsApiParameters = $tools->GetJsApiParameters($result);
                        $data = json_decode($jsApiParameters, true); // 返回的json字符串，不要重复json_encode
                    } catch (Exception $e) {
                        throw new Exception("签名错误");
                    }
                } else if ($payType == 'MWEB') {
                    // H5支付添加回调地址，回调地址加上订单号参数供查询结果使用
                    $link = $result['mweb_url'];
                    $redirectUrl = $this->getHost() . "/wx/wxWebRedirect.html?transactionId={$transactionId}";
                    $link = $link . "&redirect_url=" . urlencode($redirectUrl);
                    $data['link'] = $link;
                    // 302跳转(使用网页js跳转，php的Location不会传递referer)
                }
            } else {
                throw new Exception('统一下单失败', -3);
            }

            // 返回数据
            $return['data'] = $data;
            $return['code'] = 0;
            $return['msg'] = '创建订单成功';

        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }
        return $return;
    }


    /**
     * 微信回调通知
     * @return bool
     */
    public function notify()
    {
        try {
            require_once 'WxpayAPI/lib/WxPay.Api.php';
            WxPayApi::notify(array($this, 'handler'), $msg);
            if (!empty($msg)) {
                throw new Exception($msg);
            }

            // 返回微信成功表示标示
            echo "<xml>
<return_code><![CDATA[SUCCESS]]></return_code>
<return_msg><![CDATA[OK]]></return_msg>
</xml>";
        } catch (Exception $e) {
            // 记录日志todo...
            // 返回http状态400，微信服务器会重试
            header('HTTP/1.1 400');
            return false;
        }
    }

    /**
     * 处理业务
     * @param $postData
     * @return bool
     * @throws Exception
     */
    public function handle($postData)
    {
        if ($postData['return_code'] == 'SUCCESS' && $postData['result_code'] == 'SUCCESS') {
            $transactionId = $postData['out_trade_no'];

            // 获取订单信息
            $pdo = $this->pdo;
            $sql = 'select * from `order` where transaction_id = ? limit 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array('transaction_id' => $transactionId));
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
        } else {
            throw new Exception("不合法订单信息", -1);
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
                // 手动查询订单
                require_once 'WxpayAPI/lib/WxPay.Api.php';
                $input = new WxPayOrderQuery();
                $input->SetOut_trade_no($transactionId);
                $result = WxPayApi::orderQuery($input);
                if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS' && $result['trade_state'] == 'SUCCESS') {
                    // 处理业务todo...
                } else {
                    throw new Exception('支付失败', -4);
                }
            }

            $return['code'] = 0;
            $return['msg'] = '支付成功';
        } catch (Exception $e) {
            $return['code'] = $e->getCode();
            $return['msg'] = $e->getMessage();
        }

        return $return;
    }

    private function getHost()
    {
        return "http://" . $_SERVER['host'];
    }


    /**
     * 获取openID
     * @return string
     */
    private function getOpenId()
    {
        // todo...
        return "";
    }

    /**
     * 签名
     * @param array $paramsArr
     * @return string
     */
    private function makeSign($paramsArr = array())
    {
        //签名步骤一：按字典序排序参数
        ksort($paramsArr);
        $string = $this->ToUrlParams($paramsArr);
        //签名步骤二：在string后加入KEY
        $string = $string . '&key='.WxPayConfig::KEY;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 格式化参数格式化成url参数
     * @param $paramsArr
     * @return string
     */
    private function ToUrlParams($paramsArr)
    {
        $buff = '';
        foreach ($paramsArr as $k => $v)
        {
            if ($k != 'sign' && $v != '' && !is_array($v)) {
                $buff .= $k . '=' . $v . '&';
            }
        }

        $buff = trim($buff, '&');
        return $buff;
    }
}