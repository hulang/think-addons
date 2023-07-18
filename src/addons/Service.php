<?php

declare(strict_types=1);

namespace think\addons;

use think\addons\middleware\Addons;
use think\App;
use think\Route;
use think\Console;
use think\helper\Str;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;

/**
 * 插件服务
 * Class Service
 * @package wym\addons
 */
class Service extends \think\Service
{
    protected $addons_path;

    protected $appName;

    //存放[插件名称]列表数据
    protected $addons_data = [];

    //存放[插件ini所有信息]列表数据
    protected $addons_data_list = [];

    //模块所有[config.php]里的信息存放
    protected $addons_data_list_config = [];

    public function register()
    {
        $this->app->bind('addons', Service::class);

        // 无则创建addons目录
        $this->addons_path = $this->getAddonsPath();
        // 自动载入插件
        $this->autoload();
        // 加载系统语言包
        $this->loadLang();
        // 2.注册插件事件hook
        $this->loadEvent();
        // 加载插件系统服务
        $this->loadService();
        // 4.自动加载全局的插件内部第三方类库
        addons_vendor_autoload($this->addons_data_list ? $this->addons_data_list : Cache::get('addons_data_list'));
    }

    public function boot()
    {
        $commands = [
            'addons:app'        => Command\App::class,
            'addons:config'     => Command\Config::class,
            'addons:controller' => Command\Controller::class,
            'addons:info'       => Command\info::class,
            'addons:install'    => Command\Install::class,
            'addons:menu'       => Command\menu::class,
            'addons:other'      => Command\Other::class,
            'addons:plugin'     => Command\Plugin::class,
            'addons:uninstall'  => Command\Uninstall::class,
            'addons:view'       => Command\View::class,
        ];
        Console::starting(function (Console $console) use ($commands) {
            foreach ($commands as $key => $command) {
                $console->addCommand($command, is_numeric($key) ? '' : $key);
            }
        });
        //注册HttpRun事件监听,触发后注册全局中间件到开始位置
        $this->registerRoutes(function (Route $route) {
            // 路由脚本
            $execute = '\\think\\addons\\Route::execute';

            // 注册插件公共中间件
            if (is_file($this->app->addons->getAddonsPath() . 'middleware.php')) {
                $this->app->middleware->import(include $this->app->addons->getAddonsPath() . 'middleware.php', 'route');
            }

            // 注册控制器路由
            $route->rule('addons/:addon/[:controller]/[:action]', $execute)->middleware(Addons::class);

            // 自定义路由
            $routes = (array) config('addons.route', []);

            foreach ($routes as $key => $val) {
                if (!$val) {
                    continue;
                }

                if (is_array($val)) {
                    $domain = $val['domain'];
                    $rules = [];

                    foreach ($val['rule'] as $k => $rule) {
                        [$addon, $controller, $action] = explode('/', $rule);
                        $rules[$k] = [
                            'addons'     => $addon,
                            'controller' => $controller,
                            'action'     => $action,
                            'indomain'   => 1,
                        ];
                    }
                    $route->domain($domain, function () use ($rules, $route, $execute) {
                        // 动态注册域名的路由规则
                        foreach ($rules as $k => $rule) {
                            $route->rule($k, $execute)
                                ->name($k)
                                ->completeMatch(true)
                                ->append($rule);
                        }
                    });
                } else {
                    [$addon, $controller, $action] = explode('/', $val);
                    $route->rule($key, $execute)
                        ->name($key)
                        ->completeMatch(true)
                        ->append([
                            'addons'     => $addon,
                            'controller' => $controller,
                            'action'     => $action,
                        ]);
                }
            }
        });
    }

    private function loadLang()
    {
        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/hulang/think-addons/src/lang/zh-cn.php',
        ]);
        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 挂载插件服务
     */
    private function loadService()
    {
        $results = scandir($this->addons_path);
        $bind    = [];

        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }

            if (is_file($this->addons_path . $name)) {
                continue;
            }
            $addonDir = $this->addons_path . $name . DIRECTORY_SEPARATOR;

            if (!is_dir($addonDir)) {
                continue;
            }

            if (!is_file($addonDir . 'Plugin.php')) {
                continue;
            }

            $service_file = $addonDir . 'service.ini';

            if (!is_file($service_file)) {
                continue;
            }
            $info = parse_ini_file($service_file, true, INI_SCANNER_TYPED) ?: [];

            if ($info) {
                $this->app->register(array_shift($info));
            }
            $bind = array_merge($bind, $info);
        }
        $this->app->bind($bind);
    }

    /**
     * 插件事件
     */
    private function loadEvent()
    {
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);

        if (empty($hooks)) {
            $hooks = (array) Config::get('addons.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array) $values;
                }
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    return [get_addons_class($v), $key];
                }, $values));
            }
            Cache::set('hooks', $hooks);
        }
        Event::listenEvents($hooks);
        // 如果在插件中有定义 AddonsInit，则直接执行
        if (isset($hooks['AddonsInit'])) {
            foreach ($hooks['AddonsInit'] as $k => $v) {
                Event::trigger('AddonsInit', $v);
            }
        }
    }

    /**
     * 自动载入钩子插件
     * @return bool
     */
    private function autoload()
    {
        // 是否处理自动载入
        if (!Config::get('addons.autoload', true)) {
            return true;
        }
        $config = Config::get('addons');
        // 读取插件目录及钩子列表
        $base = get_class_methods('\\think\\addons');
        $base = array_merge($base, ['init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled']);
        // 读取插件目录中的php文件
        foreach (glob($this->getAddonsPath() . '*/*.php') as $addons_file) {
            // 格式化路径信息
            $info = pathinfo($addons_file);
            // 获取插件目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // 找到插件入口文件
            if (Str::lower($info['filename']) === 'plugin') {
                // 读取出所有公共方法
                if (!class_exists('\\addons\\' . $name . '\\' . $info['filename'])) {
                    continue;
                }
                $methods = (array) get_class_methods('\\addons\\' . $name . '\\' . $info['filename']);
                $ini     = $info['dirname'] . DS . 'plugin.ini';

                if (!is_file($ini)) {
                    continue;
                }
                $addon_config = parse_ini_file($ini, true, INI_SCANNER_TYPED) ?: [];

                if (!$addon_config['status']) {
                    continue;
                }

                if (!$addon_config['install']) {
                    continue;
                }
                $this->addons_data[]                                  = $addon_config['name'];
                $this->addons_data_list[$addon_config['name']]        = $addon_config;
                $this->addons_data_list_config[$addon_config['name']] = include $this->getAddonsPath() . $addon_config['name'] . '/config.php';
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
            }
        }
        // 插件配置信息保存到缓存
        Cache::set('addons_config', $config);
        // 插件列表
        Cache::set('addons_data', $this->addons_data);
        // 插件ini列表
        Cache::set('addons_data_list', $this->addons_data_list);
        // 插件config列表
        Cache::set('addons_data_list_config', $this->addons_data_list_config);
        Config::set($config, 'addons');
    }

    /**
     * 获取 addons 路径
     * @return string
     */
    public function getAddonsPath()
    {
        // 初始化插件目录
        $addons_path = $this->app->getRootPath() . 'addons' . DS;
        // 如果插件目录不存在则创建
        if (!is_dir($addons_path)) {
            @mkdir($addons_path, 0755, true);
        }

        return $addons_path;
    }

    /**
     * 获取插件的配置信息
     * @param  string $name
     * @return array
     */
    public function getAddonsConfig()
    {
        $name  = $this->app->request->addon;
        $addon = get_addons_instance($name);

        if (!$addon) {
            return [];
        }

        return $addon->getConfig();
    }

    /**
     *
     * 获取需移动的目录
     *
     */
    public static function getDirs()
    {
        return [
            'app'    => base_path(),
            'public' => public_path() . 'static' . DS,
        ];
    }

    /**
     * 复制相关目录
     * @param $name
     * @param mixed $delete
     */
    public static function copyFiles($name, $delete = false)
    {
        $path = self::getDirs();

        foreach ($path as $k => $v) {
            self::recurse_copy(app()->addons->getAddonsPath() . $name . DS . $k . DS . $name, $v . $name, $delete);
        }
    }

    /**
     * 删除相关目录
     * @param $name
     * @param mixed $delete
     */
    public static function removeFiles($name, $delete = false)
    {
        $path = self::getDirs();

        foreach ($path as $k => $v) {
            self::recurse_copy($v . $name, app()->addons->getAddonsPath() . $name . DS . $k . DS . $name, $delete);
        }
    }

    /**
     * 递归复制
     * @param string $source 源目录
     * @param string $dest   新目录
     * @param mixed  $delete
     */
    private static function recurse_copy(string $source, string $dest, $delete = false)
    {
        if (!is_dir($source)) {
            //如果目标不是一个目录则退出
            return; //退出函数
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0777, true);
        }
        $dir = opendir($source);

        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($source . DS . $file)) {
                    self::recurse_copy($source . DS . $file, $dest . DS . $file, $delete);

                    if ($delete) {
                        rmdir($source . DS . $file);
                    }
                } else {
                    copy($source . DS . $file, $dest . DS . $file);

                    if ($delete) {
                        unlink($source . DS . $file);
                    }
                }
            }
        }
        closedir($dir);
    }

    /**
     * 更新插件状态
     * @param  string $name
     * @return array
     */
    public static function updateAddonsInfo(string $name, int $status = 1, int $install = 1)
    {
        $addonslist = get_addons_list();
        $addonslist[$name]['status']  = $status;
        $addonslist[$name]['install'] = $install;
        Cache::set('addonslist', $addonslist);
        set_addons_info($name, ['status' => $status, 'install' => $install]);
    }
}
