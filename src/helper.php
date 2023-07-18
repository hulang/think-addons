<?php

declare(strict_types=1);

use think\Exception;
use think\facade\Config;
use think\facade\Db;
use think\facade\Event;
use think\facade\Route;
use think\facade\Cache;
use think\helper\Str;

define('DS', DIRECTORY_SEPARATOR);
// 插件类库自动载入
spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    $namespace = 'addons';

    if (strpos($class, $namespace) === 0) {
        $dir = app()->getRootPath();
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
     * @param  string     $event  钩子名称
     * @param  array|null $params 传入参数
     * @param  bool       $once   是否只返回一个结果
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
     * @param  string $name 插件名
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

if (!function_exists('addons_vendor_autoload')) {
    /**
     * 加载插件内部第三方类库
     * @param mixed $addonsName 插件名称或插件数组
     */
    function addons_vendor_autoload($addonsName)
    {
        //插件全局类库
        if (is_array($addonsName)) {
            foreach ($addonsName as $item) {
                $autoload_file = app()->addons->getAddonsPath() . $item['name'] . '/vendor/autoload.php';

                if (file_exists($autoload_file)) {
                    require_once $autoload_file;
                }
            }
        } else {
            //插件私有类库
            $autoload_file = app()->addons->getAddonsPath() . $addonsName . '/vendor/autoload.php';

            if (file_exists($autoload_file)) {
                require_once $autoload_file;
            }
        }

        return true;
    }
}

if (!function_exists('set_addons_info')) {
    /**
     * 设置基础配置信息
     * @param  string    $name  插件名
     * @param  array     $array 配置数据
     * @return boolean
     * @throws Exception
     */
    function set_addons_info($name, $array)
    {
        $addons_path = app()->addons->getAddonsPath();
        // 插件列表
        $file  = $addons_path . $name . DS . 'plugin.ini';
        $addon = get_addons_instance($name);
        $array = $addon->setInfo($name, $array);
        $array['status'] ? $addon->enabled() : $addon->disabled();

        if (!isset($array['name']) || !isset($array['title']) || !isset($array['version'])) {
            throw new Exception(lang('failed to write plugin config'));
        }
        $res = [];

        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $res[] = "[$key]";

                foreach ($val as $k => $v) {
                    $res[] = "$k = " . (is_numeric($v) ? $v : $v);
                }
            } else {
                $res[] = "$key = " . (is_numeric($val) ? $val : $val);
            }
        }

        if ($handle = fopen($file, 'w')) {
            fwrite($handle, implode(PHP_EOL, $res) . PHP_EOL);
            fclose($handle);
            //清空当前配置缓存
            Config::set($array, "addon_{$name}_info");
            Cache::delete('addonslist');
        } else {
            throw new Exception(lang('file does not have write permission'));
        }

        return true;
    }
}

if (!function_exists('get_addons_instance')) {
    /**
     * 获取插件的单例
     * @param  string     $name 插件名
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
        }

        return null;
    }
}

if (!function_exists('get_addons_class')) {
    /**
     * 获取插件类的类名
     * @param  string $name  插件名
     * @param  string $type  返回命名空间类型
     * @param  string $class 当前类名
     * @return string
     */
    function get_addons_class($name, $type = 'hook', $class = null)
    {
        $name = trim($name);
        // 处理多级控制器情况
        if (!is_null($class) && strpos($class, ' . ')) {
            $class = explode(' . ', $class);

            $class[count($class) - 1] = Str::studly(end($class));
            $class = implode('\\', $class);
        } else {
            $class = Str::studly(is_null($class) ? $name : $class);
        }

        switch ($type) {
            case 'controller':
                $namespace = '\\addons\\' . $name . '\\controller\\' . $class;
                break;
            default:
                $namespace = '\\addons\\' . $name . '\\Plugin';
        }

        return class_exists($namespace) ? $namespace : '';
    }
}

if (!function_exists('get_addons_config')) {
    /**
     * 获取插件的配置
     * @param  string     $name 插件名
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

if (!function_exists('set_addons_config')) {
    function set_addons_config($name, $array)
    {
        $file = app()->addons->getAddonsPath() . $name . DS . 'config.php';

        if ($handle = fopen($file, 'w')) {
            fwrite($handle, "<?php\n\n" . 'return ' . var_export($array, true) . ';');
            fclose($handle);
        } else {
            throw new Exception(lang('file does not have write permission'));
        }

        return true;
    }
}

if (!function_exists('addons_url')) {
    /**
     * 插件显示内容里生成访问插件的url
     * @param $url
     * @param  array       $param
     * @param  bool|string $suffix 生成的URL后缀
     * @param  bool|string $domain 域名
     * @return bool|string
     */
    function addons_url($url = '', $param = [], $suffix = true, $domain = false)
    {
        $request = app('request');

        if (empty($url)) {
            // 生成 url 模板变量
            $addons     = $request->addon;
            $controller = $request->controller();
            $controller = str_replace(' / ', ' . ', $controller);
            $action     = $request->action();
        } else {
            $url = Str::studly($url);
            $url = parse_url($url);

            if (isset($url['scheme'])) {
                $addons     = Str::lower($url['scheme']);
                $controller = $url['host'];
                $action     = trim($url['path'], ' / ');
            } else {
                $route      = explode(' / ', $url['path']);
                $addons     = $request->addon;
                $action     = array_pop($route);
                $controller = array_pop($route) ?: $request->controller();
            }
            $controller = Str::snake((string) $controller);

            /* 解析URL带的参数 */
            if (isset($url['query'])) {
                parse_str($url['query'], $query);
                $param = array_merge($query, $param);
            }
        }

        return Route::buildUrl("@addons/{$addons}/{$controller}/{$action}", $param)->suffix($suffix)->domain($domain);
    }
}

if (!function_exists('get_addons_list')) {
    /**
     * 获得插件列表
     * @return array
     */
    function get_addons_list()
    {
        if (!Cache::get('addonslist')) {
            // 插件列表
            $addons_path = app()->addons->getAddonsPath();
            $results = scandir($addons_path);
            $list = [];

            foreach ($results as $name) {
                if ($name === '.' or $name === '..') {
                    continue;
                }

                if (is_file($addons_path . $name)) {
                    continue;
                }
                $addonDir = $addons_path . $name . DS;

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
                $info['install'] = $info['install'] ?? 0;
                $list[$name]     = $info;
            }
            Cache::set('addonslist', $list);
        } else {
            $list = Cache::get('addonslist');
        }

        return $list;
    }
}

if (!function_exists('get_addons_menu')) {
    /**
     * 获取插件菜单
     *
     * @param  string $name 插件名称
     * @return array
     */
    function get_addons_menu(string $name)
    {
        $menu = app()->addons->getAddonsPath() . $name . DS . 'Menu.php';

        if (file_exists($menu)) {
            return include_once $menu;
        }

        return [];
    }
}

if (!function_exists('get_addons_autoload_config')) {
    /**
     * 获得插件自动加载的配置
     * @param  bool  $chunk 是否清除手动配置的钩子
     * @return array
     */
    function get_addons_autoload_config($chunk = false)
    {
        // 读取addons的配置
        $config = (array)config('addons');

        if ($chunk) {
            // 清空手动配置的钩子
            $config['hooks'] = [];
        }
        $route = [];
        // 读取插件目录及钩子列表
        $base = get_class_methods('\\think\\addons\\addons');
        $base = array_merge($base, ['init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled']);

        $url_domain_deploy = config('route.url_domain_deploy');
        $addons = get_addons_list();
        $domain = [];

        foreach ($addons as $name => $addon) {
            if (!$addon['install']) {
                continue;
            }

            if (!$addon['status']) {
                continue;
            }
            // 读取出所有公共方法
            $methods = (array)get_class_methods('\\addons\\' . $name . '\\' . 'Plugin');
            // 跟插件基类方法做比对，得到差异结果
            $hooks = array_diff($methods, $base);
            // 循环将钩子方法写入配置中
            foreach ($hooks as $hook) {
                $hook = Str::studly($hook);

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
                        'rule'   => $rule,
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

if (!function_exists('refreshaddons')) {
    /**
     * 刷新插件缓存文件
     *
     * @return boolean
     * @throws Exception
     */
    function refreshaddons()
    {
        $addons = get_addons_list();
        $file   = config_path() . 'addons.php';

        $config = get_addons_autoload_config(true);

        if (!$config['autoload']) {
            return;
        }

        if ($handle = fopen($file, 'w')) {
            fwrite($handle, "<?php\n\n" . 'return ' . var_export($config, true) . ';');
            fclose($handle);
        } else {
            throw new Exception(lang('file does not have write permission'));
        }

        return true;
    }
}
/**
 * 判断文件或目录是否有写的权限
 * @param mixed $file
 */
function is_really_writable($file)
{
    if (DIRECTORY_SEPARATOR == '/' and @ini_get('safe_mode') == false) {
        return is_writable($file);
    }

    if (!is_file($file) or ($fp = @fopen($file, 'r+')) === false) {
        return false;
    }
    fclose($fp);

    return true;
}

if (!function_exists('importsql')) {
    /**
     * 导入SQL
     *
     * @param  string  $name 插件名称
     * @return boolean
     */
    function importsql(string $name)
    {
        $sqlFile = app()->addons->getAddonsPath() . $name . DS . 'install.sql';

        if (is_file($sqlFile)) {
            $gz  = fopen($sqlFile, 'r');
            $sql = '';

            while (1) {
                $sql .= fgets($gz);

                if (preg_match('/.*;$/', trim($sql))) {
                    $sql = preg_replace('/(\/\*(\s|.)*?\*\/);/', '', $sql);
                    $sql = str_replace('__PREFIX__', config('database.connections.mysql.prefix'), $sql);

                    if (strpos($sql, 'CREATE TABLE') !== false || strpos($sql, 'INSERT INTO') !== false || strpos($sql, 'ALTER TABLE') !== false || strpos($sql, 'DROP TABLE') !== false) {
                        try {
                            Db::execute($sql);
                        } catch (\Exception $e) {
                            throw new Exception($e->getMessage());
                        }
                    }
                    $sql = '';
                }

                if (feof($gz)) {
                    break;
                }
            }
        }

        return true;
    }
}

if (!function_exists('uninstallsql')) {
    /**
     * 卸载SQL
     *
     * @param  string  $name 插件名称
     * @return boolean
     */
    function uninstallsql($name)
    {
        $sqlFile = app()->addons->getAddonsPath() . DS . 'uninstall.sql';

        if (is_file($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $sql = str_replace('__PREFIX__', config('database.connections.mysql.prefix'), $sql);
            $sql = array_filter(explode("\r\n", $sql));

            foreach ($sql as $k => $v) {
                try {
                    Db::execute($v);
                } catch (\Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }

        return true;
    }
}
