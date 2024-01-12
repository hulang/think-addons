<?php

declare(strict_types=1);

namespace think\auth\model;

use think\Model;

/**
 * 权限规则表
 * Class Rule
 * @package think\auth\model
 */
class Rule extends Model
{
    // 表名
    protected $name = 'AuthRule';
}
