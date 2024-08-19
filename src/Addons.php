<?php

declare(strict_types=1);

namespace think;

use think\App;
use think\facade\Config;
use think\facade\View;
use hulang\tool\FileHelper;

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
    protected function initialize() {}

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
     * @param array $vars 模板文件名
     * @return false|mixed|string 模板输出变量
     * @throws \think\Exception
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @param string $content 模板内容
     * @param array $vars 模板输出变量
     * @return mixed
     */
    protected function display($content = '', $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign([$name => $value]);
        return $this;
    }

    /**
     * 初始化模板引擎
     * @param array|string $engine 引擎参数
     * @return $this
     */
    protected function engine($engine)
    {
        $this->view->engine($engine);
        return $this;
    }

    /**
     * 插件基础信息
     * @return array
     */
    final public function getInfo()
    {
        // 尝试从配置系统中直接获取插件信息
        $info = Config::get($this->addon_info, []);
        if ($info) {
            return $info;
        }
        // 构建插件info.json文件的路径
        // 文件配置
        $info_file = $this->addon_path . 'info.json';
        // 检查info.json文件是否存在
        if (is_file($info_file)) {
            // 读取并解析info.json文件内容
            $_info = json_decode(FileHelper::readFile($info_file), true);
            // 为info.json信息添加插件URL
            $_info['url'] = addons_url();
            // 合并从配置系统中获取的信息和info.json中的信息
            $info = array_merge($_info, $info);
        }
        // 将合并后的信息保存回配置系统
        Config::set($info, $this->addon_info);
        // 返回插件信息,如果不存在则返回空数组
        return isset($info) ? $info : [];
    }

    /**
     * 更新插件的配置文件
     * 
     * 本函数用于插件管理中,对指定插件的配置文件进行更新操作
     * 如果插件目录不存在,则会创建之
     * 配置文件采用 JSON 格式,更新时会确保编码为 UTF-8,并且不对特殊字符进行转义
     * 
     * @param string $name 插件名称,用于指定要更新配置的插件
     * @param array $array 新的配置数据数组,用于更新插件的配置
     * @return mixed|bool 返回更新操作的结果.成功返回 true,失败返回错误信息
     */
    final public function setConfig($name = '', $array = [])
    {
        // 获取插件的根目录
        $path = $this->addon_path;
        // 如果指定了插件名称,则更新路径为指定插件的目录
        if (!empty($name)) {
            // 构建指定插件的完整路径
            $path = $this->app->addons->getAddonsPath() . $name . DIRECTORY_SEPARATOR;
        }
        // 拼接插件的配置文件路径
        $config = $path . 'config.json';
        // 检查插件目录是否存在,如果不存在则创建
        if (!is_file($path)) {
            FileHelper::mkDir($path);
        }
        // 将新的配置数据编码为 JSON 格式,并写入到配置文件中
        $result = FileHelper::writeFile($config, json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // 返回写入操作的结果
        return $result;
    }

    /**
     * 获取插件的配置信息
     * 
     * 本方法用于获取当前插件的配置信息
     * 首先尝试从内存中的配置缓存中获取配置,如果缓存中不存在,则尝试从插件目录下的config.json文件中读取配置信息,并将读取到的配置缓存起来,以供后续使用
     * 
     * @param bool $type 是否返回完整的配置数组.如果为true,则返回完整的配置数组；
     *                  如果为false,则只返回配置值部分
     * @return mixed|array 如果$type为false,返回配置值的数组；如果$type为true,
     *                    返回包含完整配置信息的数组
     */
    final public function getConfig($type = false)
    {
        // 尝试从配置缓存中获取插件的配置信息
        $config = Config::get($this->addon_config, []);
        if ($config) {
            return $config;
        }
        // 构建插件配置文件的路径
        $config_file = $this->addon_path . 'config.json';
        // 如果配置文件存在,尝试读取并解析配置文件
        if (is_file($config_file)) {
            $temp_arr = json_decode(FileHelper::readFile($config_file), true);
            if ($type) {
                return $temp_arr;
            }
            // 如果不需要完整的配置数组,只提取配置值部分
            foreach ($temp_arr as $key => $value) {
                $config[$key] = $value['value'];
            }
            unset($temp_arr);
        }
        // 将获取到的配置信息缓存起来
        Config::set($config, $this->addon_config);
        return $config;
    }

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
}
