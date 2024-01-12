<?php

declare(strict_types=1);

namespace think\auth\model;

use think\Model;

/**
 * 权限组与用户关系
 * Class RoleUser
 * @package think\auth\model
 */
class RoleUser extends Model
{
    // 表名
    protected $name = 'AuthRoleUser';
    /**
     * 数据表主键 复合主键使用数组定义
     * @var mixed|string|array
     */
    protected $pk = 'role_id';

    /**
     * 用户角色列表
     * @return mixed|\think\model\relation\HasMany
     */
    public function rules()
    {
        return $this->hasMany(RoleRule::class, 'role_id', 'role_id');
    }

    /**
     * 关联角色
     * @return mixed|\think\model\relation\HasOne
     */
    public function role()
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
}
