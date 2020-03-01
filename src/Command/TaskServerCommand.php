<?php

namespace Superman2014\SfSwooleConsole\Command;

use Superman2014\SfSwooleConsole\TaskConstant;
use Superman2014\SfSwooleConsole\TaskServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class TaskServerCommand extends Command
{

    protected static $defaultName = 'task:server';

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Start Swoole Task Server')
            ->setHelp('Swoole Task Manager')
            ->addArgument('cmd', InputArgument::OPTIONAL, 'start|stop|status|relaod|restart|ping|usage', 'usage')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $input->getArgument('cmd');

        if (!in_array($command, TaskConstant::COMMAND_SET) || $command == TaskConstant::USAGE) {
            $output->write("php console task:server <start|stop|status|reload|restart|ping|usage>".PHP_EOL);
            return 0;
        }

        new TaskServer($command);

        return 0;
    }


}
