<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\service\Auth;
use app\common\library\Y;
use think\exception\ValidateException;

class PublicController extends BaseController
{
    //登录
    public function login()
    {
        if ($this->request->isPost()) {
            $post = $this->request->only(['username', 'password', 'captcha'], 'post');
            try {
                $this->validate($post, [
                    'username|用户名' => 'require|max:32',
                    'password|密码'  => 'require|length:6,16',
                    'captcha|验证码'  => 'require|length:5|captcha',
                ]);
            } catch (ValidateException $e) {
                return Y::echo(0, $e->getError());
            }

            $admin = Admin::where('username', $post['username'])->find();
            if ($admin->isEmpty() || !password_verify($post['password'], $admin['password'])) {
                return Y::echo(0, "用户名或密码错误");
            }

            if ($admin['status'] < 1) {
                return Y::echo(0, "该用户已被禁用，无法登陆");
            }

            $admin['login_times']     = $admin['login_times'] + 1;
            $admin['last_login_ip']   = $this->request->ip();
            $admin['last_login_time'] = time();
            if ($admin->save() && Auth::ins()->login($admin['id'])) {
                cookie('username', $admin['username']);
                return Y::echo(1, "登录成功");
            }
            return Y::echo(0, "登录失败");
        } else {
            return view('login');
        }
    }


    //获取验证码
    public function captcha()
    {
        return captcha();
    }
}
