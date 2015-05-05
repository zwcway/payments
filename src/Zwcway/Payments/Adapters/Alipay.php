<?php namespace Zwcway\Payments\Adapters;

use Config;
use Illuminate\Support\Facades\Log as Log;
use Zwcway\Signs\RSA;

/**
 * Class Alipay
 * @package App\Payments\Adapters
 */
class Alipay extends AdapterAbstract
{

    /**
     * 构建支付适配器
     *
     * @access public
     * @param  array $config (default: array())
     */
    public function __construct($config = array())
    {
        $this->_config['request_id'] = Config::get('payments::alipay.request_id', date('Ymdhis'));
        $this->_config['cacert'] = Config::get('payments::alipay.cacert', '');
        $this->_config['partner'] = Config::get('payments::alipay.partner', '');
        $this->_config['private_key_path'] = Config::get('payments::alipay.private_key_path', '');
        $this->_config['public_key_path'] = Config::get('payments::alipay.public_key_path', '');

        if (!empty($config)) $this->setConfig($config);

//        if ($this->_config['type'] == 1) $this->_config['service'] = 'alipay.wap.auth.authAndExecute';
//        elseif ($this->_config['type'] == 2) $this->_config['service'] = 'create_direct_pay_by_user';
//        else $this->_config['service'] = 'create_partner_trade_by_buyer';

        $this->_config['seller_email'] = Config::get('payments::alipay.account', '');
        $this->_config['sign_type'] = strtoupper(Config::get('payments::alipay.sign_type', 'MD5'));
        $this->_config['format'] = strtolower(Config::get('payments::alipay.format', 'xml'));
        $this->_config['version'] = Config::get('payments::alipay.version', '2.0');
        $this->_config['notify_url'] = Config::get('payments::alipay.notify_url', '');
        $this->_config['callback_url'] = Config::get('payments::alipay.callback_url', '');
        $this->_config['merchant_url'] = Config::get('payments::alipay.merchant_url', '');
        $this->_config['gateway'] = Config::get('payments::alipay.gateway', '');
        $this->_config['key'] = Config::get('payments::alipay.key', '');

        $this->_config['charset'] = strtolower(Config::get('app.encoding', 'UTF-8'));
        $this->_config['url'] = $this->_config['gateway'] . '?_input_charset=' . $this->_config['charset'];
        $this->_config['method'] = 'GET';
        $this->_config['https_verify_url'] = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';
        $this->_config['http_verify_url'] = 'http://notify.alipay.com/trade/notify_query.do?';
        $this->_config['transport'] = Config::get('payments::alipay.transport', 'http');

    }

    /**
     * 初始化创建服务的参数
     *
     * @access public
     * @*param  array $params (default: array())
     * @return array
     */
    protected function _getCreateParameters()
    {
        //请求的参数数组
        $passParameters = array(
            '_input_charset' => $this->_config['charset'],//编码
            'partner' => $this->_config['partner'],//订单编号
            'service' => \Config::get('payments::alipay.service_create', ''),
            'sec_id' => $this->_config['sign_type'],//
            'format' => $this->_config['format'],//
            'req_id' => $this->_config['request_id'],//
            'v' => $this->_config['version'],//订单编号
        );

        if ($this->_config['format'] === 'xml') {
            $passParameters['req_data'] = $this->_getTokenRequestXmlData();
        }

        return $this->_buildRequestParams($passParameters);
    }

    protected function _getExecuteParameters($token)
    {
        $params = array(
            "service" => \Config::get('payments::alipay.service_execute', ''),
            "partner" => $this->_config['partner'],
            "sec_id" => $this->_config['sign_type'],
            "format" => $this->_config['format'],
            "v" => $this->_config['version'],
            "req_id" => $this->_config['request_id'],
            "_input_charset" => $this->_config['charset'],
            'req_data' => ''
        );
        switch ($this->_config['format'] ) {
            case 'xml':
                $params['req_data'] = $this->_getExecuteRequestXmlData($token);
                break;
        }
        return $this->_buildRequestParams($params);
    }

    /**
     * @param array $params
     * @return string
     */
    protected function _getVerifyParameters($params)
    {
        if($this->_config['transport'] == 'https') {
            $veryfy_url = $this->_config['https_verify_url'];
        }
        else {
            $veryfy_url = $this->_config['http_verify_url'];
        }
        $partner = trim($this->_config['partner']);

        $result = 'true';
        if (! empty($params['notify_id'])) {
            $result = $this->_makeHttpsRequestGET(
                $veryfy_url . "partner=" . $partner . "&notify_id=" . $params['notify_id'],
                $this->_config['cacert']
            );
        }
        return preg_match("/true$/i", $result);
    }

    public function _getParameters()
    {
        $tokenParameters = $this->_getCreateParameters();

        $tokenData = $this->_makeHttpsRequestPOST(
            $this->_config['url'],
            $this->_config['cacert'],
            $tokenParameters
        );

        $tokenData = urldecode($tokenData);

        $respone = $this->_parseResponse($tokenData);
        $token = $this->_getTokenRespone($respone);

        return $this->_getExecuteParameters($token);
    }

    /**
     * GET接收数据，同步通知
     * 状态码说明  （1 交易完成 0 交易失败）
     *
     * @param $result
     * @return string
     */
    public function receive($result)
    {
        if ($this->verify($result)) {
            if ($result['result'] == 'success') {
                $this->setOrderid($result['out_trade_no']);
                $this->setOrderNo($result['trade_no']);
                return true;
            }
        }
        return false;
    }

    /**
     * POST异步通知
     * @param $result
     * @return boolean
     */
    public function response($result)
    {
        if ($data = $this->verify($result, false)) {
            if ($data['trade_status'] == 'TRADE_FINISHED'
            || $data['trade_status'] == 'TRADE_SUCCESS') {
                $this->setOrderid($data['out_trade_no']);
                $this->setOrderNo($data['trade_no']);
                return true;
            }
        }
        return false;
    }

    /**
     * @param $result
     * @param bool $sync
     * @return bool|array
     */
    public function verify($result, $sync = true)
    {
        $success = true;
        $data = true;

        if (! $sync) {
            $notifyData = $this->_decryptSign($result['notify_data']);
            $result['notify_data'] = $notifyData;

            $data = $this->load_xml($notifyData);
            if (isset ($data['notify'])) {
                $data = $data['notify'];
            } else {
                return false;
            }

            $success = $this->_getVerifyParameters($data);
        }
        $receiveSign = isset($result['sign']) ? $result['sign'] : '';
        $params = $this->_formatParameters($result, $sync);
        $prestr = $this->_getSignString($params);

        return  $success && $this->_verifySign($prestr, $receiveSign) ? $data : false;

    }

    /**
     * 生成签名结果
     * @param string $prestr 要加密的字符串
     * @return string 签名结果字符串
     */
    protected function _build_mysign($prestr)
    {
        $sign_type = $this->_config['sign_type'];
        $mysgin = '';

        //把最终的字符串加密，获得签名结果
        switch ($sign_type) {
            case 'MD5':
                $mysgin = md5($prestr.$this->_config['key']);
                break;
            case 'DSA':
                //DSA 签名方法待后续开发
                break;
            case 'RSA':
            case '0001':
                $mysgin = RSA::rsaSign($prestr, $this->_config['private_key_path']);
                break;
            default:
                break;
        }
        return $mysgin;
    }

    protected function _getSignString(array $array)
    {
        //把拼接后的字符串再与安全校验码直接连接起来
        return $this->createUrlHash($array);
    }

    protected function _verifySign($prestr, $sign)
    {
        $sign_type = $this->_config['sign_type'];
        switch ($sign_type) {
            case 'MD5':
                $this->verified = $sign == md5($prestr . $this->_config['key']);
                break;
            case 'DSA':
                //DSA 签名方法待后续开发
                break;
            case 'RSA':
            case '0001':
                $this->verified = RSA::rsaVerify($prestr, $this->_config['private_key_path'], $sign);
                break;
            default:
                break;
        }
        return $this->verified;
    }

    protected function _decryptSign($string)
    {
        $sign_type = $this->_config['sign_type'];
        switch ($sign_type) {
            case 'MD5':
                return $string;
            case 'DSA':
                //DSA 签名方法待后续开发
                break;
            case 'RSA':
            case '0001':
                return RSA::rsaDecrypt($string, $this->_config['private_key_path']);
            default:
                break;
        }
        return '';
    }
    protected function _getTokenRequestXmlData()
    {
        return $this->build_xml(array(
            'direct_trade_create_req' => array(
                'notify_url' => $this->_config['notify_url'],
                'call_back_url' => $this->_config['callback_url'],
                'seller_account_name' => $this->_config['seller_email'],
                'out_trade_no' => $this->_orderId,
                'subject' => $this->_product['name'],
                'total_fee' => $this->_product['price'],
                'merchant_url' => $this->_config['merchant_url']
            )
        ));
    }

    protected function _getExecuteRequestXmlData($token)
    {
        return $this->build_xml(array(
            'auth_and_execute_req' => array(
                'request_token' => $token
            )
        ));
    }

    protected function _getTokenRespone($respone)
    {
        return isset($respone['request_token']) ? $respone['request_token'] : '';
    }

    /**
     * 返回字符过滤
     * @param array $parameter
     * @return array
     */
    private function _filterParameter($parameter)
    {
        $para = array();
        foreach ($parameter as $key => $value) {
            if ('sign' == $key || 'sign_type' == $key || '' == $value || 'code' == $key) {
                continue;
            } else {
                $para[$key] = $value;
            }
        }
        return $para;
    }

    /**
     * 生成要请求给支付宝的参数数组
     * @param array $params 请求前的参数数组
     * @return array 要请求的参数数组
     */
    private function _buildRequestParams($params)
    {
        $para_sort = $this->_formatParameters($params);
        $prestr = $this->_getSignString($para_sort);
        //生成签名结果
        $mysign = $this->_build_mysign($prestr);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mysign;
        if ($para_sort['service'] != 'alipay.wap.trade.create.direct' && $para_sort['service'] != 'alipay.wap.auth.authAndExecute') {
            $para_sort['sign_type'] = $this->_config['sign_type'];
        }

        return $para_sort;
    }

    protected function _formatParameters($params, $sync = true)
    {
        //除去待签名参数数组中的空值和签名参数
        $para = $this->_filterParameter($params);

        //对待签名参数数组排序
        if ($sync) {
            $para = $this->_argSort($para);
        } else {
            $para = $this->_keySort($para, array('service', 'v', 'sec_id', 'notify_data'));
        }

        return $para;
    }

    /**
     * 解析远程模拟提交后返回的信息
     * @param string $str_text 要解析的字符串
     * @return array 解析结果
     */
    protected function _parseResponse($str_text)
    {
        //以“&”字符切割字符串
        $para_split = explode('&', $str_text);
        //把切割后的字符串数组变成变量与数值组合的数组
        foreach ($para_split as $item) {
            //获得第一个=字符的位置
            $nPos = strpos($item, '=');
            //获得字符串长度
            $nLen = strlen($item);
            //获得变量名
            $key = substr($item, 0, $nPos);
            //获得数值
            $value = substr($item, $nPos + 1, $nLen - $nPos - 1);
            //放入数组中
            $para_text[$key] = $value;
        }

        if (isset($para_text['res_error'])) {
            \Log::error('alipay error', $para_text);
            return '';
        }

        if (!empty ($para_text['res_data'])) {
            //解析加密部分字符串
            if ($this->_config['sign_type'] == '0001') {
                $para_text['res_data'] = RSA::rsaDecrypt($para_text['res_data'], $this->_config['private_key_path']);
            }

            //token从res_data中解析出来（也就是说res_data中已经包含token的内容）
            $doc = new \DOMDocument();
            $doc->loadXML($para_text['res_data']);
            $para_text['request_token'] = $doc->getElementsByTagName("request_token")->item(0)->nodeValue;
        }


        return $para_text;
    }
}
