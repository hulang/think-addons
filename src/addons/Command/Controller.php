<?php

declare(strict_types=1);

namespace think\addons\Command;

use think\console\Input;
use think\console\Output;
use think\helper\Str;
use think\addons\Addon;

class Controller extends Addon
{
    protected $type = 'Controller';

    protected function configure()
    {
        parent::configure();
        $this->setName('addons:controller')
            ->setDescription('Custom plugin controller');
    }

    protected function execute(Input $input, Output $output)
    {
        parent::execute($input, $output);
        $plugin    = trim($input->getArgument('name'));
        $classname = $this->getClassName($plugin);
        $namespace = trim(implode('\\', array_slice(explode('\\', $classname), 0, -1)), '\\');

        $class    = str_replace($namespace . '\\', '', $classname);
        $filename = $this->getPathName($classname);

        if (is_file($filename)) {
            $content = file_get_contents($filename);
            $content = str_replace(['{%namespace%}'], [$namespace], $content);

            file_put_contents($filename, $content);
        }
    }

    protected function getNamespace(string $appNamespace): string
    {
        return parent::getNamespace($appNamespace) . '\\' . Str::lower($this->type);
    }

    protected function getClassName(string $name): string
    {
        if (strpos($name, '\\') !== false) {
            return $name;
        }
        $plugin = $name;

        if (strpos($name, '@')) {
            [$plugin, $name] = explode('@', $name);
        } else {
            $name = 'Index';
        }

        if (strpos($plugin, '/') !== false) {
            $plugin = str_replace('/', '\\', $plugin);
        }

        return $this->getNamespace($plugin) . '\\' . Str::studly($name);
    }

    protected function getPathName(string $name): string
    {
        $name = str_replace('addons\\', '', $name);

        return $this->app->addons->getAddonsPath() . ltrim(str_replace('\\', '/', $name), '/') . '.php';
    }
}
