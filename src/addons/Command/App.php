<?php

declare(strict_types=1);

namespace think\addons\Command;

use think\app\command\Build;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Console;

class App extends Build
{
    protected function configure()
    {
        // 指令配置
        $this->setName('plugin')
            ->addArgument('plugin', Argument::OPTIONAL, 'plugin name .')
            ->setDescription('Custom Plugin Dirs');
    }
    protected function execute(Input $input, Output $output)
    {
        $this->basePath = $this->app->addons->getAddonsPath();
        $plugin         = $input->getArgument('plugin') ?: '';

        Console::call('addons:config');
        Console::call('addons:controller', [$plugin]);
        Console::call('addons:install', [$plugin]);
        Console::call('addons:menu', [$plugin]);
        Console::call('addons:other', [$plugin]);
        Console::call('addons:info', [$plugin]);
        Console::call('addons:plugin', [$plugin]);
        Console::call('addons:uninstall', [$plugin]);
        $output->writeln('<info>plugin created successfully.</info>');
    }
}
