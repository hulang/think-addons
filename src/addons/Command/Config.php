<?php

declare(strict_types=1);

namespace think\addons\Command;

use think\console\Input;
use think\console\Output;
use think\addons\Addon;

class Config extends Addon
{
    protected $type = 'addons';

    public function configure()
    {
        $this->setName('addons:config')
            ->setDescription('send config to config folder');
    }
    protected function execute(Input $input, Output $output)
    {
        $basePath = $this->app->addons->getAddonsPath();

        $filename = config_path() . 'addons.php';
        //判断目录是否存在
        if (!file_exists(config_path())) {
            mkdir(config_path(), 0755, true);
        }
        $info = $this->type . ':The config file ' . str_replace('.php', '', str_replace(root_path(), '', $filename));
        if (!is_file($filename)) {
            $content = file_get_contents($this->getStub());

            file_put_contents($filename, $content);
            $info = '<info>' . $info . ' created successfully.</info>';
        } else {
            $info = '<error>' . $info . ' already exists!</error>';
        }
        $output->writeln($info);
    }
}
