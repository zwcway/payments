# payments
在laravel 4中使用的支付宝（Alipay）等的支付插件

# 使用方法
## 支付宝
    $pay = Payment::create('alipay');
    $pay->setOrderid('订单 ID')
        ->setProductName('产品名')
        ->setProductPrice(0.01);
    return Response::make($pay->render());
### 同步通知
    $alipay = Payment::create('alipay');
    $verified = $alipay->receive(Input::all());
    
    // 验证参数
    if (!$alipay->verified()) {
        Log::error("支付宝异步通知验证失败\n" . json_encode(Input::all()));
        return View::make('pay.fail');
    }
    
    // 获取订单号
    $orderNo = $alipay->getOrderId();
    
    if (!$order->isPaid()) {
        if (!$verified) {
            Log::warning('支付宝支付失败。');
            ...
        } else {
            Log::warning('支付宝支付成功。');
            ...
        }
    }
    ...
### 异步通知
    $alipay = Payment::create('alipay');
    $verified = $alipay->response(Input::all());
    
    // 验证参数
    if (!$alipay->verified()) {
        Log::error("支付宝异步通知被异常调用\n" . json_encode(Input::all()));
        return Response::make('fail');
    }
    $orderNo = $alipay->getOrderId();

    if (!$verified) {
        // 支付失败
        return Response::make('fail');
    }
    
    return Response::make('success');
# 待增功能
    