<?php

namespace app\admin\middleware;

use app\admin\service\Auth;
use think\Request;

class CheckAuth
{
    const NO_NEED_LOGIN = [
        "/public/*",
    ];

    public function handle(Request $request, \Closure $next)
    {
        //注入auth
        bind("auth", Auth::ins());
        $url = strtolower('/' . $request->controller() . "/" . $request->action());
        if ($this->isUrl($url, self::NO_NEED_LOGIN)) {
            return $next($request);
        }

        //未登录，去登录
        $identity = Auth::ins()->user();
        if (!isset($identity['id'])) {
            return redirect('public/login');
        }

        //检查权限
        if (!Auth::ins()->check($url)) {
            if ($request->isAjax()) {
                return json([
                    'code' => -1,
                    'msg'  => "无权限",
                ]);
            } else {
                return abort(401, "您无权限访问该页面");
            }
        }
        return $next($request);
    }


    /**
     * 检测url
     * @param $url
     * @param $urls
     * @return bool
     */
    public function isUrl($url, $urls)
    {
        foreach ($urls as $pattern) {
            $pattern = strtolower($pattern);
            if ($pattern == $url) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');
            $pattern = str_replace('\*', '.*', $pattern) . '\z';
            if (preg_match('#^' . $pattern . '#', $url)) {
                return true;
            }
        }
        return false;
    }

}
