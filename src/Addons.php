<?php

declare(strict_types=1);

namespace think;

use think\App;
use think\facade\Config;
use think\facade\View;

abstract class Addons
{
    // app 容器
    protected $app;
    // 请求对象
    protected $request;
    // 当前插件标识
    protected $name;
    // 插件路径
    protected $addon_path;
    // 视图模型
    protected $view;
    // 插件配置
    protected $addon_config;
    // 插件信息
    protected $addon_info;
    /**
     * 插件构造函数
     * Addons constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->name = $this->getName();

        $this->addon_path = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        $this->addon_config = "addon_{$this->name}_config";
        $this->addon_info = "addon_{$this->name}_info";

        $this->view = clone View::engine('Think');
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getName()
    {
        $class = get_class($this);
        [, $name,] = explode('\\', $class);
        $this->request->addon = $name;
        return $name;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return mixed|false|string   模板输出变量
     * @throws \think\Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @return mixed
     */
    protected function display($content = '', $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return mixed|$this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign([$name => $value]);
        return $this;
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param  array|string $engine 引擎参数
     * @return mixed|$this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);
        return $this;
    }
    /**
     * 创建目录
     * @param string $dir 目录名
     * @return mixed|bool true 成功/false 失败
     */
    final public function mkDir($dir)
    {
        $dir = rtrim($dir, '/') . '/';
        if (!is_dir($dir)) {
            if (mkdir($dir, 0700) == false) {
                return false;
            }
            return true;
        }
        return true;
    }
    /**
     * 读取文件内容
     * @param string $filename 文件名
     * @return mixed|string 文件内容
     */
    final public function readFile($filename = '')
    {
        $content = '';
        if (!empty($filename) && is_file($filename)) {
            $content = file_get_contents($filename);
        }
        return $content;
    }
    /**
     * 写文件
     * @param string $filename 文件名
     * @param string $writetext 文件内容
     * @param string $mode 写入文件模式
     * @return mixed|bool
     */
    final public function writeFile($filename = '', $writetext = '', $mode = LOCK_EX)
    {
        if (!empty($filename) && !empty($writetext)) {
            $size = file_put_contents($filename, $writetext, $mode);
            if ($size > 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /**
     * 插件更新[info]配置文件
     *
     * @param string $name 插件名
     * @param array $array 数据
     * @return mixed|bool
     */
    final public function setInfo($name = '', $array = [])
    {
        $path = $this->addon_path;
        if (!empty($name)) {
            $path = $this->app->addons->getAddonsPath() . $name . DIRECTORY_SEPARATOR;
        }
        $config = $path . 'info.json';
        if (!is_file($path)) {
            $this->mkDir($path);
        }
        $list = [];
        foreach ($array as $k => $v) {
            $list[$k] = $v;
        }
        $result = $this->writeFile($config, json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $result;
    }

    /**
     * 获取插件基础信息
     * @return mixed|array
     */
    final public function getInfo()
    {
        $info = Config::get($this->addon_info, []);
        if ($info) {
            return $info;
        }
        // 文件配置
        $info_file = $this->addon_path . 'info.json';
        if (is_file($info_file)) {
            $_info = json_decode($this->readFile($info_file), true);
            $_info['url'] = addons_url();
            $info = array_merge($_info, $info);
        }
        Config::set($info, $this->addon_info);
        return isset($info) ? $info : [];
    }
    /**
     * 插件更新[config]配置文件
     *
     * @param string $name 插件名
     * @param array $array 数据
     * @return mixed|bool
     */
    final public function setConfig($name = '', $array = [])
    {
        $path = $this->addon_path;
        if (!empty($name)) {
            $path = $this->app->addons->getAddonsPath() . $name . DIRECTORY_SEPARATOR;
        }
        $config = $path . 'config.json';
        if (!is_file($path)) {
            $this->mkDir($path);
        }
        $result = $this->writeFile($config, json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $result;
    }
    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return mixed|array
     */
    final public function getConfig($type = false)
    {
        $config = Config::get($this->addon_config, []);
        if ($config) {
            return $config;
        }
        $config_file = $this->addon_path . 'config.json';
        if (is_file($config_file)) {
            $temp_arr = json_decode($this->readFile($config_file), true);
            if ($type) {
                return $temp_arr;
            }
            foreach ($temp_arr as $key => $value) {
                $config[$key] = $value['value'];
            }
            unset($temp_arr);
        }
        Config::set($config, $this->addon_config);
        return $config;
    }

    // 必须实现安装
    abstract public function install();

    // 必须卸载插件方法
    abstract public function uninstall();
}
