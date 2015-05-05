<?php namespace Zwcway\Payments\Adapters;

abstract class AdapterAbstract
{
    /**
     * 支付配置
     *
     * @var  array
     */
    protected $_config = array(
        'account' => '',//商户账号
        'key' => '',//商户密钥
        'partner' => 0,//合作身份者ID
        'type' => 0,//支付网关类型
        'url' => '',//支付网关地址
        'reurl' => '',//支付返回地址
        'method' => 'POST',//支付网关方法

    );
    /**
     * 商品信息
     *
     * @var  array
     */
    protected $_product = array(
        'name' => '',//名称
        'price' => 0,//金额
        'info' => '',//信息
        'url' => '',//链接
        'currency' => 'CNY',//币种
    );
    /**
     * 订单编号
     *
     * @var  array
     */
    protected $_orderId;
    /**
     * 支付单号
     * @var string
     */
    protected $_orderNo;
    /**
     * 购买人信息
     *
     * @var  array
     */
    protected $_orderer = array(
        'name' => '',//姓名
        'address' => '',//地址
        'tel' => '',//电话
        'mobile' => '',//手机
        'email' => '',//邮箱
        'post' => '',//邮编
        'remark1' => '',//备注1
        'remark2' => ''//备注2
    );
    /**
     * 收货人信息
     *
     * @var  array
     */
    protected $_customer = array(
        'name' => '',//姓名
        'address' => '',//地址
        'tel' => '',//电话
        'mobile' => '',//手机
        'email' => '',//邮箱
        'post' => ''//邮编
    );

    protected $verified = false;

    public function verified()
    {
        return $this->verified;
    }
    /**
     * 设置配置参数.
     *
     * @param  array
     * @return $this
     */
    public function setConfig($config)
    {
        $this->_config = array_merge($this->_config, $config);
        return $this;
    }

    /**
     * 设置商品信息.
     *
     * @param  array
     * @return $this
     */
    public function setProduct($product)
    {
        $this->_product = array_merge($this->_product, $product);
        return $this;
    }

    /**
     * 设置商品信息.
     *
     * @param  array
     * @return $this
     */
    public function setProductName($name)
    {
        $this->_product['name'] = $name;
        return $this;
    }

    /**
     * 设置商品信息.
     *
     * @param  array
     * @return $this
     */
    public function setProductPrice($price)
    {
        $this->_product['price'] = $price;
        return $this;
    }

    /**
     * 设置客户信息.
     *
     * @param  array
     * @return $this
     */
    public function setCustomer($customer)
    {
        $this->_customer = array_merge($this->_customer, $customer);;
        return $this;
    }

    /**
     * 设置收货人信息.
     *
     * @param  array
     * @return $this
     */
    public function setOrderer($orderer)
    {
        $this->_orderer = array_merge($this->_orderer, $orderer);
        return $this;
    }

    /**
     * 设置订单编号.
     *
     * @param  array
     * @return $this
     */
    public function setOrderid($orderid)
    {
        $this->_orderId = $orderid;
        return $this;
    }

    public function getOrderId()
    {
        return $this->_orderId;
    }
    public function setOrderNo($no)
    {
        $this->_orderNo = $no;
        return $this;
    }
    public function getOrderNo()
    {
        return $this->_orderNo;
    }

    /**
     * 构建一个表单域，并立即提交表单至支付网关
     *
     * @param  string
     * @return string
     */
    public function render($submit = '确认')
    {
        $parameters = $this->_getParameters();
        $hiddens = array();
        foreach ($parameters as $attr_key => $attr_val) {
            $hiddens[] = '<input type="hidden" name="' . $attr_key . '" value="' . $attr_val . '" />' . "\n";
        }
        $form =
            '<form method="' . $this->_config['method'] . '" action="' . $this->_config['url'] . '" id="paymentsubmit">'
            . implode('', $hiddens)
            . '<input type="submit" value="' . $submit . '"/>'
            . '</form>'
            . '<script>document.forms["paymentsubmit"].submit();</script>';
        return $form;
    }

    /**
     * 模拟 http 请求
     *
     * @param  array
     * @param  string
     * @return string
     */
    protected function _makeRequest($url, $time_out = "60")
    {
        $urlarr = parse_url($url);
        $errno = "";
        $errstr = "";
        $transports = "";

        if ($urlarr["scheme"] == "https") {
            $transports = "ssl://";
            $urlarr["port"] = "443";
        } else {
            $transports = "tcp://";
            $urlarr["port"] = "80";
        }

        $fp = @fsockopen($transports . $urlarr['host'], $urlarr['port'], $errno, $errstr, $time_out);

        if (!$fp) {
            die("ERROR: {$errno} - {$errstr}<br />\n");
        } else {
            fputs($fp, "POST " . $urlarr["path"] . " HTTP/1.1\r\n");
            fputs($fp, "Host: " . $urlarr["host"] . "\r\n");
            fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
            fputs($fp, "Content-length: " . strlen($urlarr["query"]) . "\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            fputs($fp, $urlarr["query"] . "\r\n\r\n");
            while (!feof($fp)) {
                $info[] = @fgets($fp, 1024);
            }
            fclose($fp);
            $info = implode(",", $info);
            return $info;
        }
    }

    /**
     * 模拟 https 请求
     * @param $url
     * @param $cacert_url
     * @param $para
     * @param string $input_charset
     * @return string
     */
    protected function _makeHttpsRequestPOST($url, $cacert_url, $para, $input_charset = '')
    {
        if (trim($input_charset) != '') {
            $url = $url."_input_charset=".$input_charset;
        }
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_POST,true); // post传输数据
        curl_setopt($curl, CURLOPT_POSTFIELDS,$para);// post传输数据
        $responseText = curl_exec($curl);
//        var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
        curl_close($curl);

        return $responseText;
    }

    /**
     * 远程获取数据，GET模式
     * 注意：
     * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
     * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
     * @param string $url 指定URL完整路径地址
     * @param string $cacert_url 指定当前工作目录绝对路径
     * @return string 远程输出的数据
     */
    protected function _makeHttpsRequestGET($url, $cacert_url) {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO,$cacert_url);//证书地址
        $responseText = curl_exec($curl);
        //var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
        curl_close($curl);

        return $responseText;
    }


    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param array $keyValues 需要拼接的数组
     * @param boolean $urlEncode 是否对字符串做 urlencode 编码
     * @return string 拼接完成以后的字符串
     */
    protected function createUrlHash(array $keyValues, $urlEncode = false)
    {
        $hashs = array();
        foreach ($keyValues as $key => $value) {
            $hashs[] = $key . '=' . ($urlEncode ? urlencode($value) : $value);
        }
        $hash = implode('&', $hashs);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $hash = stripslashes($hash);
        }

        return $hash;
    }
    /**
     * 根据数组构建xml树
     * @param array $params
     * @return string
     */
    protected function build_xml(array $params)
    {
        $result = '';
        foreach ($params as $tag => $param) {
            $result .= "<{$tag}>";
            $result .= is_array($param) ? $this->build_xml($param) : $param;
            $result .= "</{$tag}>";
        }
        return $result;
    }

    /**
     * 根据数组构建xml树
     * @param string $xml
     * @return array
     */
    protected function load_xml($xml)
    {
        /* @var \DOMElement $element */
        $result = array();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        return $this->_xml_children($dom->documentElement);
    }

    protected function _xml_children(\DOMElement $element)
    {
        $array = array();
        if ($element->childNodes->length > 1) {
            $array[$element->nodeName] = array();
            foreach ($element->childNodes as $child) {
                $array[$element->nodeName] = array_merge($array[$element->nodeName], $this->_xml_children($child));
            }
        } else {
            $array[$element->nodeName] = $element->nodeValue;
        }
        return $array;
    }

    /**
     * 对数组排序
     * @param array $para 排序前的数组
     * @return array 排序后的数组
     */
    protected function _argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }

    /**
     * 对数组按给定顺序排序
     * @param array $params 排序前的数组
     * @param array $keys 按值的顺序排序
     * @return array 排序后的数组
     */
    protected function _keySort($params, $keys)
    {
        $result = array();
        foreach ($keys as $key){
            array_key_exists($key, $params) AND $result[$key] = $params[$key];
        }
        return $result;
    }
    /**
     * 支付返回结果通知
     *
     * @param array $result
     * @return array
     */
    abstract public function receive($result);

    /**
     * 支付返回响应
     * @param array $result
     *
     */
    abstract public function response($result);

    /**
     * 支付表单参数
     */
    abstract protected function _getParameters();

    /**
     * 验证返回数据是否合法
     * @return mixed
     */
    abstract public function verify($result);
}