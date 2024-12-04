<?php

declare(strict_types=1);

namespace think\addons\middleware;

use think\App;

class Addons
{
    protected $app;

    /**
     * 构造函数用于初始化类的实例
     * 
     * 该构造函数接收一个App对象作为参数,用于绑定当前类实例到一个特定的应用上下文中
     * 这种依赖注入的方式允许类在不同的应用环境中灵活使用,同时也便于测试和Mock
     * 
     * @param App $app 一个应用上下文对象,用于绑定当前类实例到特定的应用环境中
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 插件中间件处理函数
     * 该函数旨在在请求通过中间件时,为插件提供一个干预请求处理流程的机会
     * 它通过执行插件注册的中间件回调,来实现插件对应用程序请求的处理
     *
     * @param $request 请求对象,包含当前HTTP请求的所有信息
     * @param \Closure $next 下一个中间件或最终的请求处理函数的闭包
     * @return mixed 返回经过下一个中间件或最终处理函数处理后的响应
     */
    public function handle($request, \Closure $next)
    {
        // 执行插件中间件钩子,允许插件注册的中间件在此处运行。
        hook('addon_middleware', $request);
        // 继续处理请求,通过传递请求给下一个中间件或最终处理函数。
        return $next($request);
    }
}
