<?php

declare(strict_types=1);

use think\facade\Event;
use think\facade\Route;
use think\facade\Cache;
use think\helper\Str;

define('DS', DIRECTORY_SEPARATOR);

// 插件类库自动载入
spl_autoload_register(function ($class) {
    // 获取类名
    $class = ltrim($class, '\\');
    // 获取根目录
    $root_path = str_replace('\\', '/', dirname(__DIR__));
    $dir = strstr($root_path, 'vendor', true);
    // 获取命名空间
    $namespace = 'addons';
    if (strpos($class, $namespace) === 0) {
        $class = substr($class, strlen($namespace));
        $path = '';
        if (($pos = strripos($class, '\\')) !== false) {
            $path = str_replace('\\', '/', substr($class, 0, $pos)) . '/';
            $class = substr($class, $pos + 1);
        }
        $path .= str_replace('_', '/', $class) . '.php';
        $dir .= $namespace . $path;
        if (file_exists($dir)) {
            include $dir;
            return true;
        }
        return false;
    }
    return false;
});

if (!function_exists('hook')) {
    /**
     * 执行插件钩子
     * 
     * 通过调用此函数,可以触发一个插件钩子,允许插件在特定的事件点插入自定义代码
     * 这是插件系统的核心功能之一,它使得主题和插件可以无侵入地扩展和修改应用程序的行为
     * 
     * @param string $event 钩子的名称,标识要触发的事件
     * @param array|null $params 传递给钩子函数的参数,可以是单个参数或参数数组
     * @param bool $once 指定钩子是否只执行一次.如果设置为true,则在第一次触发后取消订阅
     * @return mixed 返回钩子执行的结果,通常是字符串拼接的结果,也可以是其他数据类型
     */
    function hook($event, $params = null, bool $once = false)
    {
        // 触发事件,调用所有订阅了此事件的钩子函数,并根据$once参数决定是否只执行一次
        $result = Event::trigger($event, $params, $once);
        // 将所有钩子函数的返回值拼接成一个字符串并返回
        // 这样做是为了方便处理多个钩子函数返回的结果,尤其是当它们都是字符串时
        return join('', $result);
    }
}

if (!function_exists('get_addons_info')) {
    /**
     * 获取插件的基本信息
     * 
     * 本函数用于通过插件名获取特定插件的基础信息
     * 如果插件不存在或无法实例化,则返回空数组;否则,返回插件实例的info方法返回的信息
     * 
     * @param string $name 插件的名称.用于唯一标识一个插件
     * @return mixed|array 如果插件存在并成功实例化,返回插件的信息数组;否则,返回空数组
     */
    function get_addons_info($name)
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化,如果没有成功,返回空数组
        if (!$addon) {
            return [];
        }
        // 返回插件实例的信息数组
        return $addon->getInfo();
    }
}

if (!function_exists('set_addons_info')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新插件的配置信息,通过提供插件名称和一个新的配置数组来更新插件的信息
     * 如果插件不存在或无法实例化,则函数不会进行更新操作并返回空数组
     * 如果插件存在并成功更新信息,则返回插件实例的更新结果
     * 
     * @param string $name 插件的名称.如果未提供名称,则默认为空字符串
     * @param array $array 一个包含插件新配置信息的数组.如果未提供数组,则默认为空数组
     * @return mixed|bool 如果插件不存在或无法实例化,返回空数组;如果成功更新插件信息,返回插件实例的更新结果
     */
    function set_addons_info($name = '', $array = [])
    {
        // 实例化指定名称的插件
        $addon = get_addons_instance($name);
        // 检查插件是否成功实例化
        if (!$addon) {
            return [];
        }
        // 调用插件实例的方法来更新插件信息
        return $addon->setInfo($name, $array);
    }
}

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置信息
     * 
     * 本函数用于检索指定插件的配置信息
     * 插件配置是插件开发者定义的一组参数,用于定制插件的行为或提供给插件使用者进行配置
     * 通过插件名获取插件实例后,调用插件实例的getConfig方法来获取配置信息
     * 
     * @param string $name 插件的名称.这是识别插件的唯一标识符
     * @param bool $type 指定是否获取完整的配置信息.默认为false,表示只获取默认配置
     *                  如果设置为true,则会尝试获取完整的配置信息,包括可能的用户自定义配置
     * @return mixed|array 如果插件存在并成功获取配置,则返回配置信息,这可以是一个数组或其它类型的值
     *                    如果插件不存在或获取配置失败,则返回一个空数组
     */
    function get_addons_config($name, $type = false)
    {
        // 获取指定插件的实例.
        $addon = get_addons_instance($name);
        // 检查插件实例是否获取成功.
        if (!$addon) {
            return [];
        }
        // 通过插件实例获取配置信息,根据$type的值决定获取默认配置还是完整配置.
        return $addon->getConfig($type);
    }
}

if (!function_exists('set_addons_config')) {
    /**
     * 设置插件的配置信息
     * 本函数用于更新指定插件的配置文件.如果插件存在,则将新配置信息写入插件的配置文件中
     * @param string $name 插件名称.如果未指定名称,则默认为空字符串
     * @param array $array 新的配置信息数组.如果未指定配置数组,则默认为空数组
     * @return mixed|bool 如果插件不存在,则返回空数组.如果插件存在且配置更新成功,则返回true.否则,返回false
     */
    function set_addons_config($name = '', $array = [])
    {
        // 获取指定插件的实例
        $addon = get_addons_instance($name);
        // 检查插件实例是否存在,如果不存在,则返回空数组
        if (!$addon) {
            return [];
        }
        // 调用插件实例的setConfig方法来更新插件的配置文件,并返回操作结果
        return $addon->setConfig($name, $array);
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例对象
     * 
     * 本函数用于获取一个插件的单例实例
     * 如果插件已实例化,则直接返回已存在的实例;否则,尝试实例化插件类,并返回新的实例
     * 插件的实例化只会在第一次调用时发生,之后的调用都会返回相同的实例,实现了单例模式
     * 
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @return mixed|null 返回插件的实例对象,如果插件不存在或无法实例化,则返回null
     */
    function get_addons_instance($name)
    {
        // 使用静态变量存储已实例化的插件,避免重复实例化
        static $_addons = [];
        // 检查是否已存在该插件的实例,如果存在直接返回
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        // 通过插件名获取插件的类名
        $class = get_addons_class($name);
        // 检查插件类是否存在,如果存在则实例化插件类
        if (class_exists($class)) {
            // 实例化插件类,并传入应用实例作为构造函数的参数
            $_addons[$name] = new $class(app());
            return $_addons[$name];
        } else {
            // 如果插件类不存在,返回null
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 根据插件名和类型获取插件类的完整类名
     * 
     * 该函数用于生成并返回指定插件的类名,根据插件名、类型和可选的类名片段
     * 主要用于在不同的插件管理和调用场景中,动态生成插件类的完全限定名
     * 
     * @param string $name 插件的名称.这是用于唯一标识插件的字符串
     * @param string $type 类的类型.用于确定生成类名的命名空间.默认为'hook'
     * @param string $class 可选的类名片段.当需要指定插件中的特定类时使用,可以是类的路径片段
     * @return mixed|string 返回插件类的完全限定名,如果类不存在则返回空字符串
     */
    function get_addons_class($name, $type = 'hook', $class = null)
    {
        // 移除$name中的前后空格
        $name = trim($name);
        // 当$class提供并且包含点号时,处理为命名空间的数组形式
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            // 将数组中的最后一个元素转换为StudlyCaps格式,用于类名
            $class[count($class) - 1] = Str::studly(end($class));
            // 通过逆向操作将数组转换回字符串形式的命名空间
            $class = implode('\\', $class);
        } else {
            // 如果没有提供$class或者$class为null,将$name或$class转换为StudlyCaps格式,用于类名
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        // 根据$type生成插件类的命名空间
        switch ($type) {
                // 如果$type为'controller',则生成控制器的命名空间
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                break;
                // 默认情况下,生成插件基类的命名空间
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }
        // 检查命名空间对应的类是否存在,如果存在则返回命名空间字符串,否则返回空字符串
        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('addons_url')) {
    /**
     * 生成插件的URL地址
     * 
     * 该函数用于构建插件的路由URL,支持从当前请求上下文中解析插件、控制器和操作,也支持通过参数直接指定URL的各个部分
     * 可以设置URL的后缀和域名
     * 
     * @param string $url 要生成的URL路径,可以是相对路径或者完整的URL字符串
     * @param array $param URL中的参数,以键值对形式提供
     * @param bool|string $suffix URL的后缀,可以是true（使用配置的默认后缀）或者具体的后缀字符串
     * @param bool|string $domain 是否使用域名,可以是true（使用配置的默认域名）或者具体的域名字符串
     * @return mixed|bool|string 返回生成的URL字符串,如果无法生成则返回false
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        /* 获取当前应用的请求对象 */
        $request = app('request');
        /* 如果URL为空,尝试从当前请求中解析插件、控制器和操作 */
        if (empty($url)) {
            // 从请求中获取当前插件名
            // 生成 url 模板变量
            $addons = $request->addon;
            // 从请求中获取当前控制器名,并将其转换为点分隔的形式
            $controller = $request->controller();
            $controller = str_replace('/', '.', $controller);
            // 从请求中获取当前操作名
            $action = $request->action();
        } else {
            /* 对提供的URL字符串进行处理,以解析出插件、控制器和操作 */
            $url = Str::studly($url);
            $url = parse_url($url);
            /* 如果URL中包含协议（scheme）,则认为是完整的URL,并从中提取插件、控制器和操作 */
            if (isset($url['scheme'])) {
                $addons = strtolower($url['scheme']);
                $controller = $url['host'];
                $action = trim($url['path'], '/');
            } else {
                /* 如果URL中不包含协议,则认为是相对路径,从中解析出控制器和操作 */
                $route = explode('/', $url['path']);
                $addons = $request->addon;
                $action = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
                $controller = Str::snake((string)$controller);
                /* 如果URL中包含查询参数,则将其合并到参数数组中 */
                if (isset($url['query'])) {
                    parse_str($url['query'], $query);
                    $param = array_merge($query, $param);
                }
            }
        }
        /* 使用解析出的插件、控制器和操作,以及参数数组,构建URL,并根据需要设置后缀和域名 */
        return Route::buildUrl("@addons/{$addons}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('get_addons_list')) {
    /**
     * 获得插件列表
     * @return mixed|array
     */
    function get_addons_list()
    {
        $list = Cache::get('addons_list');
        if (empty($list)) {
            // 插件目录
            $addonsPath = app()->getRootPath() . 'addons' . DS;
            $results = scandir($addonsPath);
            $list = [];
            foreach ($results as $name) {
                if ($name === '.' or $name === '..') {
                    continue;
                }
                if (is_file($addonsPath . $name)) {
                    continue;
                }
                $addonDir = $addonsPath . $name . DS;
                if (!is_dir($addonDir)) {
                    continue;
                }
                if (!is_file($addonDir . 'Plugin' . '.php')) {
                    continue;
                }
                $info = get_addons_info($name);
                if (!isset($info['name'])) {
                    continue;
                }
                $list[$name] = $info;
            }
            Cache::set('addons_list', $list);
        }
        return $list;
    }
}
