<?php namespace Zwcway\Payments;

use Exception;

class Payment
{

    /**
     * 初始化.
     *
     * @param  string $adapter
     * @param  array $arguments
     * @return \Zwcway\Payments\Adapters\AdapterAbstract
     * @throws Exception
     */
    public static function create($adapter, $arguments = array())
    {
        /*
        static $pays;

        if(isset($pays[$adapter]))
        {
            return $pays[$adapter];
        }
        */
        //首字母大写
        $adapter = ucwords($adapter);

        //适配器路径
        $adapterName = __NAMESPACE__ . '\\Adapters\\' . $adapter;

        // 检测是否含有该适配器.
        if (!class_exists($adapterName)) {
            throw new Exception('Can\'t load payment adapter "' . $adapterName . '"', 0);
        }
        // 返回适配器是实例.
        return new $adapterName($arguments);
    }
}
