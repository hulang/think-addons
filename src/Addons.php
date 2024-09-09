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
     * 构建插件实例时调用,初始化插件的相关属性和设置
     * 
     * @param \think\App $app ThinkPHP的应用程序实例
     */
    public function __construct(App $app)
    {
        // 注入应用程序实例
        $this->app = $app;
        // 获取当前请求实例
        $this->request = $app->request;
        // 自动获取并设置插件名称
        $this->name = $this->getName();
        // 设置插件路径,用于后续的视图解析和其他文件操作
        $this->addon_path = $app->addons->getAddonsPath() . $this->name . DIRECTORY_SEPARATOR;
        // 定义插件的配置和信息的存储键名
        $this->addon_config = sprintf('addon_%s_config', $this->name);
        $this->addon_info = sprintf('addon_%s_info', $this->name);
        // 克隆视图引擎实例,用于插件的视图渲染
        $this->view = clone View::engine('Think');
        // 配置视图路径为插件的视图目录
        $this->view->config([
            'view_path' => $this->addon_path . 'view' . DIRECTORY_SEPARATOR
        ]);
        // 执行初始化操作,可用于插件的自定义初始化设置
        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化方法
     * 
     * 该方法用于在类的其他方法执行前进行必要的初始化操作
     * 子类可以重写此方法以实现特定的初始化逻辑
     */
    protected function initialize()
    {
        // 初始化操作可以在这里进行
    }

    /**
     * 获取当前插件的名称
     * 
     * 通过解析当前类的全限定名,提取插件名称
     * 将插件名称设置到请求对象中,以便后续使用
     * 
     * @return string 插件的名称
     */
    final protected function getName()
    {
        // 获取当前对象的类名
        $class = get_class($this);
        // 使用explode函数将类名按反斜线分割,然后取第二个元素作为插件名
        [$use, $name, $plugin] = explode('\\', $class);
        // 将插件名设置到请求对象的addon属性中
        $this->request->addon = $name;
        // 返回插件名
        return $name;
    }

    /**
     * 加载模板并渲染输出
     * 本函数主要用于加载指定的模板文件,并将给定的变量渲染到模板中,最终返回渲染后的结果
     * 如果不指定模板文件名,则默认加载当前控制器对应的视图文件
     *
     * @param string $template 模板文件名.可以是相对路径或者绝对路径,如果不指定,则默认使用当前控制器对应的视图文件
     * @param array $vars 用于渲染模板的变量数组.键值对形式,键为变量名,值为变量值
     * @return mixed|false|string 返回渲染后的结果.如果渲染失败,可能返回false或者抛出异常
     * @throws \think\Exception 如果模板文件不存在或者渲染过程中发生错误,则可能抛出异常
     */
    protected function fetch($template = '', $vars = [])
    {
        return $this->view->fetch($template, $vars);
    }

    /**
     * 渲染并输出模板内容
     * 此方法用于在控制器中调用,以显示视图部分
     * 它接受一个可选的模板内容字符串和一个变量数组,用于在模板渲染过程中代替预定义的变量
     * 
     * @param string $content 可选的模板内容.如果未提供,将使用默认的模板内容
     * @param array $vars 一个包含模板变量的数组,这些变量将在渲染过程中被替换
     * @return mixed 返回渲染后的模板内容
     */
    protected function display($content = '', $vars = [])
    {
        return $this->view->display($content, $vars);
    }

    /**
     * 给模板变量赋值
     * 这个方法允许开发者为模板引擎分配变量,这些变量可以在模板文件中被引用和使用
     * @param string|array $name 如果是字符串,则表示变量名；如果是数组,则表示多个变量名和值的对应关系
     * @param mixed $value 变量的值.当$name是字符串时,这个参数表示对应的值；当$name是数组时,这个参数不会被使用
     * @return $this 返回自身,支持链式调用
     */
    protected function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->view->assign($name);
        } else {
            $this->view->assign([$name => $value]);
        }
        return $this;
    }

    /**
     * 初始化模板引擎
     * 该方法用于设置并初始化模板引擎
     * 支持传入数组或字符串作为引擎参数
     * @param array|string $engine 引擎参数,可以是数组或字符串.数组形式提供更丰富的配置信息,字符串则为简单的引擎标识
     * @return mixed|$this 返回值可以是任意类型,但通常为了支持链式调用,返回$this
     */
    protected function engine($engine)
    {
        // 将引擎配置应用于视图对象,进行模板引擎的初始化
        $this->view->engine($engine);
        // 支持链式调用,返回对象本身
        return $this;
    }

    /**
     * 插件更新[info]配置文件
     * 本函数用于更新插件的info.json配置文件
     * 如果插件目录不存在,则会创建该目录
     * 参数$name指定插件名称,参数$array为info.json的更新内容
     * 返回值为文件写入操作的结果,成功返回true,失败返回false
     *
     * @param string $name 插件名,用于定位插件的info.json文件
     * @param array $array 包含插件信息的数组,这些信息将被写入info.json文件
     * @return mixed|bool 文件写入操作的结果,成功返回true,失败返回false
     */
    final public function setInfo($name = '', $array = [])
    {
        // 获取插件的根目录
        $path = $this->addon_path;
        // 如果指定了插件名,则更新插件路径为指定插件的目录
        if (!empty($name)) {
            $path = $this->app->addons->getAddonsPath() . $name . DIRECTORY_SEPARATOR;
        }
        // 拼接info.json文件的完整路径
        $config = $path . 'info.json';
        // 如果插件目录不存在,则创建该目录
        if (!is_file($path)) {
            FileHelper::mkDir($path);
        }
        // 将输入的数组直接赋值给$list,此处用于演示,实际操作中可进行更多处理
        $list = [];
        foreach ($array as $k => $v) {
            $list[$k] = $v;
        }
        // 将数组内容编码为JSON格式,并写入info.json文件
        // 使用JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES选项以保持正确的字符编码和格式化输出
        $result = FileHelper::writeFile($config, json_encode($array, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        // 返回文件写入操作的结果
        return $result;
    }

    /**
     * 获取插件的基础信息
     * 
     * 本方法用于获取当前插件的配置信息
     * 首先尝试从配置管理系统中直接获取插件信息
     * 如果信息不存在,则尝试从插件目录下的info.json文件中读取并解析信息
     * 解析成功后,会合并从配置系统中获取的信息(如果存在)和info.json中的信息,并保存回配置系统
     * 最后返回合并后的插件信息
     * 
     * @return mixed|array 返回插件的信息数组.如果无法获取信息,则返回空数组
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

    /**
     * 安装方法
     * 该方法是一个抽象方法,没有具体的实现
     * 它的目的是为了要求所有继承自这个类的子类必须提供一个安装的实现方法
     * 具体的安装步骤和逻辑应该在子类的install方法中实现
     * 
     * @abstract
     * @return void 没有返回值,因为安装操作通常是副作用的表现,例如创建文件、设置配置等
     */
    abstract public function install();

    /**
     * 抽象方法,用于卸载插件
     * 
     * 该方法是所有插件类必须实现的抽象方法之一,旨在提供一个统一的插件卸载接口
     * 当调用此方法时,插件应该执行必要的操作以确保自身被安全地从系统中卸载
     * 这可能包括清理数据库、删除文件、取消注册事件监听器等操作
     * 
     * @abstract
     * @access public
     */
    abstract public function uninstall();
}
