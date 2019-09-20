<?php

namespace app\admin\service;

use app\admin\model\Admin;
use app\admin\model\AuthGroup;
use app\admin\model\AuthRule;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

class Auth
{

    const CACHE_TAG = "sys:auth";

    /**
     * @var object 对象实例
     */
    private static $ins;


    /**
     * 用户
     * @var
     */
    public $identity;


    /**
     * 有权限的ids
     * @var
     */
    protected $auth_rule_ids = [];

    /**
     * 有权限的规则
     * @var
     */
    protected $auth_rules = [];


    /**
     * 有权限的角色
     * @var array
     */
    protected $auth_roles = [];

    /**
     * 生成实例
     * @param int $uid
     * @return $this
     */
    public static function ins($uid = -1)
    {
        if (!isset(self::$ins[$uid])) {
            self::$ins[$uid] = new self($uid);
        }
        return self::$ins[$uid];
    }


    /**
     * 实例化
     * Auth constructor.
     * @param $uid
     */
    public function __construct(int $uid)
    {
        if ($uid < 0) {
            $uid = (int)session('admin_id');
        }
        $key   = "sys:admin:{$uid}";
        $admin = cache($key);
        if (!$admin) {
            $admin = (new Admin())->findOrEmpty($uid);
            cache($key, $admin, 300, self::CACHE_TAG);
        }
        $this->identity = $admin;
    }


    /**
     * 防止克隆
     */
    private function __clone()
    {
    }


    /**
     * 获取用户
     * @return array
     */
    public function user()
    {
        return $this->identity;
    }

    /**
     * 获取用户id
     * @return int
     */
    public function getId(): int
    {
        return $this->identity['id'] ?? 0;
    }

    /**
     * 登录
     * @param  $uid
     * @return bool
     */
    public function login(int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        session('admin_id', $uid);
        return true;
    }

    /**
     * 退出登录
     * @return bool
     */
    public function logout(): bool
    {
        session('admin_id', null);
        $this->identity = null;
        return true;
    }


    /**
     * 获取有权限的规则id
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAuthRuleIds(): array
    {
        if (!$this->auth_rule_ids) {
            $admin = $this->identity;
            if (!isset($admin['id'])) {
                return [];
            }
            $key       = "sys:ruleids:{$admin['id']}";
            $rules_ids = cache($key);
            if (!$rules_ids) {
                if ($admin['auth_group_ids']) {
                    $rules     = (new AuthGroup())->select(explode(',', $admin['auth_group_ids']))->toArray();
                    $rules_ids = array_unique(explode(',', implode(',', array_column($rules, 'rules'))));
                    if (in_array("*", $rules_ids)) {
                        $rules_ids = ["*"];
                    }
                } else {
                    $rules_ids = [];
                }
                cache($key, $rules_ids, 300, 'admin:auth');
            }
            $this->auth_rule_ids = $rules_ids;
        }
        return $this->auth_rule_ids;
    }

    /**
     * 获取有权限的规则
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getAuthRules(): array
    {
        if (!$this->auth_rules) {
            $key  = "sys:crumb:" . $this->getId();
            $list = cache($key);
            if (!$list) {
                $rule_ids = $this->getAuthRuleIds();
                $list     = (new AuthRule())->when($rule_ids[0] != "*", function ($query) use ($rule_ids) {
                    $query->where('id', 'in', $rule_ids);
                })->where('status', 1)->order('pid asc,sort asc,id asc')->select()->toArray();
                $tmp      = [];
                foreach ($list as $val) {
                    $tmp[$val['name']] = $val;
                }
                $list = $tmp;
                cache($key, $list, 300, self::CACHE_TAG);
            }
            $this->auth_rules = $list;
        }
        return $this->auth_rules;
    }

    /**
     * 检查是否有权限
     * @param $path
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function check($path)
    {
        $rule_ids = $this->getAuthRuleIds();
        if (empty($rule_ids)) {
            return false;
        }
        if ($rule_ids[0] == '*') {
            return true;
        }
        $list = $this->getAuthRules();
        $self = $list[$path] ?? [];
        if ($self && in_array($self['id'], $rule_ids)) {
            return true;
        }
        return false;
    }

    /**
     * 判断角色
     * @param $role
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function isRole($role): bool
    {
        if (!$this->auth_roles) {
            $admin = $this->user();
            $key   = "sys:crumb:" . $admin['id'] ?? 0;
            $roles = cache($key);
            if (!$roles) {
                if (!isset($admin['auth_group_ids']) || empty($admin['auth_group_ids'])) {
                    $roles = [];
                } else {
                    $roles = (new AuthGroup())->select(explode(',', $admin['auth_group_ids']))->toArray();
                    $roles = array_column($roles, 'name');
                }
                cache($key, $roles, 300, self::CACHE_TAG);
            }
            $this->auth_rules = $roles;
        }
        return in_array($role, $this->auth_rules);
    }

    /**
     * 获取菜单
     * @return array|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getMenu()
    {
        $key  = "sys:menu:{$this->getId()}";
        $menu = cache($key);
        if (!$menu) {
            $rule_ids = $this->getAuthRuleIds();
            $menu     = (new AuthRule())->when($rule_ids[0] != "*", function ($query) use ($rule_ids) {
                $query->where('id', 'in', $rule_ids);
            })->where('status', 1)->order('pid asc,sort asc,id asc')->select()->toArray();
            $menu     = $this->list_to_tree($menu);
            cache($key, $menu, 300, self::CACHE_TAG);
        }
        return $menu;
    }


    /**
     * 获取面包屑导航
     * @param $path
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getCrumb($path)
    {
        //所有菜单rules
        $list = $this->getAuthRules();
        $self = $list[$path] ?? [];
        if (!$self) {
            return [];
        }

        $parents = $this->getParents($list, $self['id']);

        return $parents;
    }

    /**
     * 把返回的数据集转换成Tree
     * @param        $list
     * @param string $pk
     * @param string $pid
     * @param string $child
     * @param int    $root
     * @return array
     */
    public function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
    {
        // 创建Tree
        $tree = [];
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = [];
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = &$list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] = &$list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent           = &$refer[$parentId];
                        $parent[$child][] = &$list[$key];
                    } else {
                        $tree[] = &$list[$key];
                    }
                }
            }
        }
        return $tree;
    }

    /**
     * 获取父级元素
     * @param $list
     * @param $id
     * @return array
     */
    public function getParents($list, $id)
    {
        $arr = [];
        foreach ($list as $v) {
            if ($v['id'] == $id) {
                $arr[] = $v;
                $arr   = array_merge($this->getParents($list, $v['pid']), $arr);
            }
        }
        return $arr;
    }
}