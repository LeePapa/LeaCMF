<?php

namespace app\common\library;
class Y
{
    /**
     * @param $code
     * @param $msg
     * @param $data
     * @return \think\response\Json
     */
    public static function echo($code, $msg = "", $data = [])
    {
        return json([
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

}