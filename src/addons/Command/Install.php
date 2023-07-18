<?php

declare(strict_types=1);

namespace think\addons\Command;

use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\helper\Str;
use think\addons\Addon;

class Install extends Addon
{
    protected $type = 'Install';

    protected function configure()
    {
        $this->setName('addons:install')
            ->addArgument('plugin', Argument::REQUIRED, 'plugin name .')
            ->setDescription('Custom plugin install');
    }

    protected function execute(Input $input, Output $output)
    {
        $basePath = $this->app->addons->getAddonsPath();
        $plugin   = $input->getArgument('plugin') ?: '';

        $pluginPath = $basePath . $plugin;
        $this->checkDirBuild($pluginPath);

        $filename = $pluginPath . DIRECTORY_SEPARATOR . Str::lower($this->type) . '.sql';
        $info     = $this->type . ':' . str_replace('.sql', '', str_replace(root_path(), '', $filename));

        if (!is_file($filename)) {
            $content = file_get_contents($this->getStub());
            $content = str_replace(['{%plugin%}'], [$plugin], $content);

            file_put_contents($filename, $content);
            $info = '<info>' . $info . ' created successfully.</info>';
        } else {
            $info = '<error>' . $info . ' already exists!</error>';
        }
        $output->writeln($info);
    }
}
