<?php

declare(strict_types=1);

namespace think\addons;

use think\helper\Str;
use think\facade\Event;
use think\facade\Config;
use think\exception\HttpException;

class Route
{
    /**
     * 插件路由执行方法
     * 该方法用于处理插件的路由请求,通过解析请求中的路由信息,找到对应的插件、控制器和操作,并执行相应的逻辑
     * 如果插件、控制器或操作不存在,或者插件被禁用,将抛出相应的异常
     * 在执行操作之前,会触发一系列的事件,允许其他地方对插件的请求进行干预
     * @return mixed 返回执行操作的结果
     * @throws HttpException 如果插件、控制器或操作不存在,或者插件被禁用,将抛出HTTP异常
     */
    public static function execute()
    {
        // 获取应用程序实例
        $app = app();
        // 获取当前请求对象
        $request = $app->request;
        // 从路由中获取插件、控制器和操作的名称
        $addon = $request->route('addon');
        $controller = $request->route('controller');
        $action = $request->route('action');
        // 触发addons_begin事件,可以在事件处理程序中进行一些全局的初始化操作
        Event::trigger('addons_begin', $request);
        // 检查插件、控制器和操作的名称是否为空,如果为空,抛出HTTP异常
        if (empty($addon) || empty($controller) || empty($action)) {
            throw new HttpException(500, lang('addon can not be empty'));
        }
        // 设置请求的插件、控制器和操作属性
        $request->addon = $addon;
        // 设置当前请求的控制器、操作
        $request->setController($controller)->setAction($action);
        // 获取插件的信息,如果插件不存在,抛出HTTP异常
        $info = get_addons_info($addon);
        if (!$info) {
            throw new HttpException(404, lang('addon %s not found', [$addon]));
        }
        // 检查插件是否被禁用,如果被禁用,抛出HTTP异常
        if (!$info['status']) {
            throw new HttpException(500, lang('addon %s is disabled', [$addon]));
        }
        // 触发addon_module_init事件,可以在事件处理程序中进行一些插件相关的初始化操作
        Event::trigger('addon_module_init', $request);
        // 根据插件和控制器的名称获取插件控制器的类名,如果类名不存在,抛出HTTP异常
        $class = get_addons_class($addon, 'controller', $controller);
        if (!$class) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }
        // 设置视图的路径为插件的视图目录
        $config = Config::get('view');
        $config['view_path'] = $app->addons->getAddonsPath() . $addon . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR;
        Config::set($config, 'view');
        // 创建插件控制器的实例,如果实例创建失败,抛出HTTP异常
        try {
            $instance = new $class($app);
        } catch (\Exception $e) {
            throw new HttpException(404, lang('addon controller %s not found', [Str::studly($controller)]));
        }
        // 初始化变量,用于存储传递给操作方法的参数
        $vars = [];
        // 检查是否可以调用指定的操作方法,如果可以,记录调用的方法
        if (is_callable([$instance, $action])) {
            // 执行操作方法
            $call = [$instance, $action];
        } elseif (is_callable([$instance, '_empty'])) {
            // 如果操作方法不存在,但是控制器中定义了_empty方法,记录_empty方法作为调用
            $call = [$instance, '_empty'];
            $vars = [$action];
        } elseif (is_callable([$instance, '__call'])) {
            // 如果操作方法和_empty方法都不存在,但是控制器中定义了__call方法,记录__call方法作为调用
            $call = [$instance, '__call'];
            $vars = [$action];
        } else {
            // 如果都无法调用,抛出HTTP异常,表示操作方法不存在
            throw new HttpException(404, lang('addon action %s not found', [get_class($instance) . '->' . $action . '()']));
        }
        // 触发addons_action_begin事件,可以在事件处理程序中对操作的执行进行干预
        Event::trigger('addons_action_begin', $call);
        // 调用记录的操作方法,并返回执行结果
        return call_user_func_array($call, $vars);
    }
}
