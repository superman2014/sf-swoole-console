<?php

// src/Command/TaskServerCommand.php
namespace Superman2014\SfSwooleConsole\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TaskServerCommand extends Command
{
    protected static $defaultName = 'task:server';

    protected function configure()
	{
		$this
			->setDescription('Start Swoole Task Server')
			->setHelp('Swoole Task Manager')
            ->addArgument('daemonize', InputArgument::OPTIONAL, 'daemonize, default: false', false)
            ->addArgument('port', InputArgument::OPTIONAL, 'port, default:9501', 9501)
		;
	}


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ...

        return 0;
    }
}
