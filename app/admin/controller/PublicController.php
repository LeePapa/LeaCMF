<?php

namespace app\admin\controller;

use think\facade\View;
use think\Request;

class PublicController extends BaseController
{

    /**
     * 登录
     * @return \think\response\View
     */
    public function login()
    {
        if ($this->request->isPost()) {
            $post     = $this->request->only(['username', 'password', 'captcha'], 'post');
            $validate = $this->validate($post, [
                'username|用户名' => 'require|max:32',
                'password|密码'  => 'require|length:6,16',
                'captcha|验证码'  => 'require|captcha',
            ]);


        } else {
            return view('login');
        }
    }


    /**
     * 获取验证码
     * @return \think\Response
     */
    public function captcha()
    {
        return captcha();
    }
}
