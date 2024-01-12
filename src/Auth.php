<?php

declare(strict_types=1);

namespace think;

use think\Facade;

/**
 * auth 权限检测入口
 * 
 * @see \think\auth\service\Auth
 * @package think
 * @mixin \think\auth\service\Auth
 * @method static mixed check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or') 检查权限
 * @method static mixed rules(int $uid = 0, int $type = 1) 返回用户的所有规则表
 * @method static mixed roles(int $uid = 0, string $field = '') 获取用户所有角色信息
 */
class Auth extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return mixed|string
     */
    protected static function getFacadeClass()
    {
        return 'think\auth\service\Auth';
    }
}
