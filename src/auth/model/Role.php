<?php

declare(strict_types=1);

namespace think\auth\model;

use think\Model;

/**
 * 权限角色
 * Class Role
 * @package think\auth\model
 */
class Role extends Model
{
    // 表名
    protected $name = 'AuthRole';

    /**
     * 删除角色时同时删除与规则，用户的关系数据
     * @param \think\Model $user
     * @throws \Exception
     * @return mixed
     */
    public static function onAfterDelete($role)
    {
        RoleRule::where(['role_id' => $role->id])->delete();
        RoleUser::where('role_id', $role->id)->delete();
    }

    /**
     * 标准化状态值
     * @param $val
     * @return mixed|int
     */
    protected function setStatusAttr($val)
    {
        switch ($val) {
            case 'on':
            case 'true':
            case '1':
            case 1:
                $val = 1;
                break;
            default:
                $val = 0;
        }
        return $val;
    }

    /**
     * 用户数
     * @return float|int|string
     * @throws mixed|\think\Exception
     */
    protected function getUserNumAttr()
    {
        $role_id = $this->getData('id');
        return RoleUser::where(['role_id' => $role_id])->count();
    }

    /**
     * 角色对应权限规则
     * @return mixed|\think\model\relation\HasMany
     */
    public function rules()
    {
        return $this->hasMany('RoleRule', 'role_id', 'id');
    }
}
