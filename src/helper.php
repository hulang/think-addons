<?php

declare(strict_types=1);

use think\facade\App;
use think\facade\Config;
use think\facade\Event;
use think\facade\Route;
use think\facade\Cache;
use think\addons\Service;
use think\helper\{
    Str,
    Arr
};

\think\Console::starting(function (\think\Console $console) {
    $console->addCommands([
        'addons:config' => '\\think\\addons\\command\\SendConfig'
    ]);
});

// 插件类库自动载入
spl_autoload_register(function ($class) {

    $class = ltrim($class, '\\');

    $dir = app()->getRootPath();
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
     * 处理插件钩子
     * @param string $event 钩子名称
     * @param array|null $params 传入参数
     * @param bool $once 是否只返回一个结果
     * @return mixed
     */
    function hook($event, $params = null, bool $once = false)
    {
        $result = Event::trigger($event, $params, $once);
        return join('', $result);
    }
}

if (!function_exists('get_addons_info')) {
    /**
     * 读取插件的基础信息
     * @param string $name 插件名
     * @return array
     */
    function get_addons_info($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getInfo();
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_instance($name)
    {
        static $_addons = [];
        if (isset($_addons[$name])) {
            return $_addons[$name];
        }
        $class = get_addons_class($name);
        if (class_exists($class)) {
            $_addons[$name] = new $class(app());

            return $_addons[$name];
        } else {
            return null;
        }
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 获取插件类的类名
     * @param string $name 插件名
     * @param string $type 返回命名空间类型
     * @param string $class 当前类名
     * @return string
     */
    function get_addons_class($name, $type = 'hook', $class = null, $module = 'admin')
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, '.')) {
            $class = explode('.', $class);
            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);
        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }
        switch ($type) {
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\' . $module . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\plugin';
        }

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

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置
     * @param string $name 插件名
     * @return mixed|null
     */
    function get_addons_config($name)
    {
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getConfig($name);
    }
}

/**
 * 获得插件列表
 * @return array
 */
if (!function_exists('get_addons_list')) {

    function get_addons_list()
    {
        if (!Cache::get('addonslist')) {
            $service = new Service(App::instance()); // 获取service 服务
            $addons_path = $service->getAddonsPath(); // 插件列表
            $results = scandir($addons_path);
            $list = [];
            foreach ($results as $name) {
                if ($name === '.' or $name === '..')
                    continue;
                if (is_file($addons_path . $name))
                    continue;
                $addonDir = $addons_path . $name . DIRECTORY_SEPARATOR;
                if (!is_dir($addonDir))
                    continue;
                if (!is_file($addonDir . 'plugin' . '.php'))
                    continue;
                $info = get_addons_info($name);
                if (!isset($info['name']))
                    continue;
                $info['url'] = isset($info['url']) && $info['url'] ? (string)addons_url($info['url']) : '';
                $list[$name] = $info;
                Cache::set('addonslist', $list);
            }
        } else {
            $list = Cache::get('addonslist');
        }
        return $list;
    }
}


/**
 * 获得插件自动加载的配置
 * @param bool $chunk 是否清除手动配置的钩子
 * @return array
 */
if (!function_exists('get_addons_autoload_config')) {

    function get_addons_autoload_config($chunk = false)
    {
        // 读取addons的配置
        $config = (array)Config::get('addons');
        if ($chunk) {
            // 清空手动配置的钩子
            $config['hooks'] = [];
        }
        $route = [];
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\think\\Addons");
        $base = array_merge($base, ['init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled']);

        $url_domain_deploy = Config::get('route.url_domain_deploy');
        $addons = get_addons_list();
        $domain = [];
        foreach ($addons as $name => $addon) {
            if (!$addon['status']) continue;
            try {
                $db_status = \think\facade\Db::name('addon')->where('name', $name)->value('status');
                if ($db_status != 1) continue;
            } catch (\Exception $e) {
            }
            // 读取出所有公共方法
            $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . 'plugin');
            // 跟插件基类方法做比对，得到差异结果
            $hooks = array_diff($methods, $base);
            // 循环将钩子方法写入配置中
            foreach ($hooks as $hook) {
                if (!isset($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = [];
                }
                // 兼容手动配置项
                if (is_string($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                }
                if (!in_array($name, $config['hooks'][$hook])) {
                    $config['hooks'][$hook][] = $name;
                }
            }
            $conf = get_addons_config($addon['name']);
            if ($conf) {
                $conf['rewrite'] = isset($conf['rewrite']) && is_array($conf['rewrite']) ? $conf['rewrite'] : [];
                $rule = $conf['rewrite'] ? $conf['rewrite']['value'] : [];
                if ($url_domain_deploy && isset($conf['domain']) && $conf['domain']) {
                    $domain[] = [
                        'addons' => $addon['name'],
                        'domain' => $conf['domain']['value'],
                        'rule' => $rule
                    ];
                } else {
                    $route = array_merge($route, $rule);
                }
            }
        }
        $config['route'] = $route;
        $config['route'] = array_merge($config['route'], $domain);
        return $config;
    }
}
