<?php

namespace think\addons;

use think\console\command\Make;

class Addon extends Make
{

    protected $type;

    /**
     * 获取模板
     * @return string
     */
    protected function getStub()
    {
        return $this->app->getRootPath() . 'vendor\hulang\think-addons\src' . '\Stubs' . DIRECTORY_SEPARATOR . $this->type . '.stub';
    }

    /**
     * 获取类命名空间
     * @param string $appNamespace
     * @return string
     */
    protected function getNamespace(string $app): string
    {
        return 'addons' . ($app ? '\\' . $app : '');
    }

    /**
     * 创建目录
     * @access protected
     * @param  string $dirname 目录名称
     * @return void
     */
    protected function checkDirBuild(string $dirname): void
    {
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
    }
}
