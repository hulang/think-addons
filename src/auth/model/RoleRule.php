<?php

declare(strict_types=1);

namespace think\auth\model;

use think\Model;

/**
 * 权限组与规则关系
 * Class RoleRule
 * @package think\auth\model
 */
class RoleRule extends Model
{
    /**
     * 表名
     * @var mixed|string
     */
    protected $name = 'AuthRoleRule';
    /**
     * 数据表主键 复合主键使用数组定义
     * @var mixed|string|array
     */
    protected $pk = 'role_id';
    /**
     * 追加一对一字段
     * @var mixed|array
     */
    protected $append = ['rules'];

    /**
     * 角色规则表列
     * @return mixed|\think\model\relation\HasOne
     */
    public function rules()
    {
        return $this->hasOne(Rule::class, 'id', 'rule_id')->bind(Rule::getTableFields());
    }
}
