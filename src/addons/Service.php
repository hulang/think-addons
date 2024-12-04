<?php

declare(strict_types=1);

namespace think\addons;

use think\Route;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;
use think\facade\Config;
use think\addons\middleware\Addons;
use hulang\tool\FileHelper;

/**
 * 插件服务
 * Class Service
 * @package think\addons
 */
class Service extends \think\Service
{
    protected $addons_path;

    /**
     * 注册插件系统
     * 该方法初始化插件的各个组成部分,包括加载语言包、自动加载插件、加载插件事件和服务
     * 它还绑定了插件服务到应用容器
     */
    public function register()
    {
        // 设置插件路径,用于后续插件的查找和加载
        $this->addons_path = $this->getAddonsPath();
        // 加载插件的语言包,支持多语言环境
        $this->loadLang();
        // 自动加载插件的类文件,提高插件的使用便捷性
        $this->autoload();
        // 加载插件函数文件
        $this->loadFun();
        // 加载插件的事件处理,使得插件可以参与到应用的生命周期中
        $this->loadEvent();
        // 加载自定义路由
        $this->loadRoutes();
        // 加载插件的服务,提供给应用和其他插件使用
        $this->loadService();
        // 加载插件命令
        $this->loadCommand();
        // 加载配置
        $this->loadConfig();
        // 将插件服务绑定到应用容器,方便随时获取和使用
        $this->app->bind('addons', Service::class);
    }

    /**
     * 加载语言包
     */
    private function loadLang()
    {
        Lang::load([
            $this->app->getRootPath() . '/vendor/hulang/think-addons/src/lang/zh-cn.php',
        ]);
    }

    /**
     * 应用启动时执行的函数
     * 主要用于注册插件的路由规则
     */
    public function boot()
    {
        $this->registerRoutes(function (Route $route) {
            // 只有在addons下进行注册解析
            $path = $this->app->request->pathinfo();
            $pathArr = explode('/', str_replace('.html', '', str_replace('\\', '/', $path)));
            if ($pathArr[0] === 'addons') {
                // 路由脚本
                $execute = '\\think\\addons\\Route::execute';
                // 注册插件公共中间件
                if (is_file($this->app->addons->getAddonsPath() . 'middleware.php')) {
                    $this->app->middleware->import(include $this->app->addons->getAddonsPath() . 'middleware.php', 'route');
                }
                // 注册控制器路由
                $route->rule('addons/:addon/[:controller]/[:action]', $execute)->middleware(Addons::class);
                // 自定义路由
                $routes = (array) Config::get('addons.route', []);
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
                                'addon' => $addon,
                                'controller' => $controller,
                                'action' => $action,
                                'indomain' => 1,
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
                                'addon' => $addon,
                                'controller' => $controller,
                                'action' => $action
                            ]);
                    }
                }
            }
        });
    }

    /**
     * 插件事件加载函数
     * 本函数负责根据当前应用的调试状态,从缓存或配置中加载插件的事件钩子,并触发相应的插件事件
     * 
     * - 当应用处于调试模式时,不使用缓存,直接从配置加载钩子信息
     * - 当应用不在调试模式时,优先从缓存中加载钩子信息,若缓存中不存在则从配置中加载并缓存
     * - 对于配置中的钩子信息,进行处理,确保其格式适合事件监听器的期望
     * - 如果存在'AddonsInit'钩子,触发该钩子对应的插件事件
     * - 最后,注册处理所有钩子事件的监听器
     * 
     * 此函数通过动态加载插件的事件钩子,实现了插件对应用功能的扩展
     */
    private function loadEvent()
    {
        // 根据应用的调试状态决定是否使用缓存中的钩子信息
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        // 如果缓存中没有钩子信息,则从配置中加载并处理钩子信息
        if (empty($hooks)) {
            $hooks = (array) Config::get('addons.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                // 将字符串格式的钩子转换为数组格式
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array) $values;
                }
                // 处理钩子信息,格式化为事件监听器的期望格式
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    return [get_addons_class($v), $key];
                }, $values));
            }
            // 将处理后的钩子信息缓存起来
            Cache::set('hooks', $hooks);
        }
        // 如果存在'AddonsInit'钩子,触发该钩子对应的插件事件
        if (isset($hooks['AddonsInit'])) {
            foreach ($hooks['AddonsInit'] as $k => $v) {
                Event::trigger('AddonsInit', $v);
            }
        }
        // 注册处理所有钩子事件的监听器
        Event::listenEvents($hooks);
    }

    /**
     * 挂载插件服务
     * 本函数负责加载插件目录中的服务定义,并将它们绑定到应用程序中
     * 它通过扫描插件目录,查找每个插件的服务配置文件(service.json)
     * 然后解析这些配置文件,将插件的服务与相应的类绑定
     * 这样,应用程序在需要使用这些服务时,可以方便地通过依赖注入来获取
     */
    private function loadService()
    {
        $bind = [];
        // 配置
        $results = FileHelper::getFolder($this->addons_path);
        if (!empty($results)) {
            foreach ($results as $k => $v) {
                if ($v['type'] == 'dir') {
                    $service_file = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'service.json']);
                    if (is_file($service_file)) {
                        $j = json_decode($service_file, true);
                        // 将当前插件的绑定信息合并到总的绑定数组中
                        $bind = array_merge($bind, $j);
                    }
                    unset($service_file);
                }
            }
        }
        if (!empty($bind)) {
            // 将所有插件的绑定信息注册到应用程序中
            $this->app->bind($bind);
        }
    }

    /**
     * 自动加载插件的函数
     * 该函数用于在满足特定条件下自动加载插件,并更新配置以注册插件的钩子
     * 如果配置中未开启自动加载插件,则直接返回true
     * @return mixed|bool 返回值取决于配置是否开启自动加载插件
     */
    private function autoload()
    {
        // 检查是否开启自动加载插件的配置,如果未开启,则直接返回true
        if (!Config::get('addons.autoload', true)) {
            return true;
        }
        // 获取插件的全局配置
        $config = Config::get('addons');
        // 获取ThinkPHP自带的插件类方法作为基线,用于后续比较
        $base = get_class_methods("\\think\\Addons");
        // 遍历插件目录下的所有文件,以寻找和注册插件的钩子
        $list = FileHelper::getFolder($this->getAddonsPath());
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                if ($v['type'] == 'dir') {
                    $name = pathinfo($v['path_name'], PATHINFO_FILENAME);
                    $plugin = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'Plugin.php']);
                    // 如果文件名是plugin.php,则认为该文件定义了插件的钩子方法
                    if (is_file($plugin)) {
                        // 获取插件类的所有方法
                        $methods = (array)get_class_methods("\\addons\\" . $name . "\\Plugin");
                        // 通过比较基线方法,找出插件特有的方法,即钩子方法
                        $hook_list = array_diff($methods, $base);
                        // 遍历钩子方法,注册到配置中
                        foreach ($hook_list as $hook) {
                            // 确保配置中存在该钩子,如果不存在则初始化为空数组
                            if (!isset($config['hooks'][$hook])) {
                                $config['hooks'][$hook] = [];
                            }
                            // 将配置中的钩子字符串转换为数组,以支持多个插件注册同一钩子
                            if (is_string($config['hooks'][$hook])) {
                                $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                            }
                            // 将当前插件添加到该钩子的注册列表中,避免重复添加
                            if (!in_array($name, $config['hooks'][$hook])) {
                                $config['hooks'][$hook][] = $name;
                            }
                        }
                    }
                }
            }
        }
        // 更新配置,保存注册的插件钩子
        Config::set($config, 'addons');
    }

    /**
     * 自定义路由文件
     */
    private function loadRoutes()
    {
        // 配置
        $addons_dir = FileHelper::getFolder($this->addons_path);
        if (!empty($addons_dir)) {
            foreach ($addons_dir as $k => $v) {
                if ($v['type'] == 'dir') {
                    $route_file = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'route.php']);
                    if (is_file($route_file)) {
                        $this->loadRoutesFrom($route_file);;
                    }
                    unset($route_file);
                }
            }
        }
    }

    /**
     * 加载插件配置文件
     */
    private function loadConfig()
    {
        // 配置
        $addons_dir = FileHelper::getFolder($this->addons_path);
        if (!empty($addons_dir)) {
            foreach ($addons_dir as $k => $v) {
                if ($v['type'] == 'dir') {
                    $console_file = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'console.php']);
                    if (is_file($console_file)) {
                        $commands_config = include_once $console_file;
                        $commands = [];
                        if (isset($commands_config['commands'])) {
                            $commands = array_merge($commands, $commands_config['commands']);
                        }
                        if (!empty($commands)) {
                            $this->commands($commands);
                        }
                        unset($commands_config);
                        unset($commands);
                    }
                    unset($console_file);
                }
            }
        }
    }

    /**
     * 加载插件函数文件
     */
    private function loadFun()
    {
        // 配置
        $addons_dir = FileHelper::getFolder($this->addons_path);
        if (!empty($addons_dir)) {
            foreach ($addons_dir as $k => $v) {
                if ($v['type'] == 'dir') {
                    $fun_file = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'fun.php']);
                    if (is_file($fun_file)) {
                        include_once($fun_file);
                    }
                    unset($fun_file);
                }
            }
        }
    }

    /**
     * 加载插件命令
     */
    private function loadCommand()
    {
        // 配置
        $addons_dir = FileHelper::getFolder($this->addons_path);
        if (!empty($addons_dir)) {
            foreach ($addons_dir as $k => $v) {
                if ($v['type'] == 'dir') {
                    $command_file = join(DIRECTORY_SEPARATOR, [$v['path_name'], 'command.php']);
                    if (is_file($command_file)) {
                        $commandArr = include_once $command_file;
                        if (is_array($commandArr)) {
                            $this->commands($commandArr);
                        }
                    }
                    unset($command_file);
                }
            }
        }
    }

    /**
     * 获取插件路径
     * 
     * 本方法用于获取应用程序中插件目录的路径
     * 如果插件目录不存在,则会尝试创建该目录
     * 这样确保了后续操作可以正确地在插件目录中进行
     * 
     * @return mixed|string 返回插件目录的路径.如果无法创建目录或获取路径失败,可能返回错误信息
     */
    public function getAddonsPath()
    {
        // 构建插件目录的路径
        $addons_path = $this->app->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
        // 检查插件目录是否存在,不存在则尝试创建
        if (!is_dir($addons_path)) {
            FileHelper::mkDir($addons_path);
        }
        // 返回插件目录路径
        return $addons_path;
    }

    /**
     * 获取当前请求的插件的配置信息
     * 
     * 本函数用于获取指定插件的配置信息
     * 首先,它通过请求对象获取当前操作的插件名称
     * 然后,尝试创建并获取该插件的实例
     * 如果插件实例不存在,则返回空数组
     * 如果插件实例存在,则通过插件实例的方法获取插件的配置信息并返回
     * 
     * @return mixed|array 返回插件的配置信息.如果插件不存在或无法实例化,则返回空数组
     */
    public function getAddonsConfig()
    {
        // 通过应用的请求对象获取当前请求的插件名称
        $name = $this->app->request->addon;
        // 尝试获取插件的实例
        $addon = get_addons_instance($name);
        // 如果插件实例不存在,则返回空数组
        if (!$addon) {
            return [];
        }
        // 返回插件的配置信息
        return $addon->getConfig();
    }
}
