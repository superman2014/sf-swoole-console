<?php

namespace Superman2014\SfSwooleConsole;

use Swoole\Server;
use Swoole\Process;
use Swoole\Client;
use Swoole\Timer;

class TaskServer
{
    const NAME = 'sf-swoole-console';

    public $config = [
        'worker_num' => 4,
        'task_worker_num' => 4,
        'daemonize' => true,
        'backlog' => 128,
        'log_file' => '/tmp/swoole.log',
        'log_level' => 0,
        'task_ipc_mode' => 3,
        'heartbeat_check_interval' => 5,
        'heartbeat_idle_time' => 10,
        'pid_file' => '/tmp/sf-swoole-console.pid',

        'open_length_check' => true,
        'package_length_type' => 'n',
        'package_length_offset' => 0,
        'package_body_offset' => Protocol::HEADER,
        'package_max_length' => Protocol::PACKAGE_LENGTH,
    ];

    const LISTEN_HOST = '0.0.0.0';

    const MANAGE_HOST = '127.0.0.1';

    const PORT = 9501;

    public function __construct($command)
    {
        switch ($command) {
            case TaskConstant::START:
                $this->start();
                break;
            case TaskConstant::STOP:
                 $this->clientSendCommand()(TaskConstant::STOP);
                break;
            case TaskConstant::STATUS:
                $recv = $this->clientSendCommand()(TaskConstant::STATUS);
                var_dump($recv['body']);
                break;
            case TaskConstant::PING:
                var_dump($this->clientSendCommand()(TaskConstant::PING));
                break;
            case TaskConstant::RELOAD:
                $recv = $this->clientSendCommand()( TaskConstant::RELOAD);
                echo $recv['body'],PHP_EOL;
                break;
            case TaskConstant::RESTART:
                $recv = $this->clientSendCommand()( TaskConstant::RESTART);
                echo $recv['body'],PHP_EOL;
                break;
        }
    }

    public function clientSendCommand()
    {
        return function ($command) {
            $client = new Client(SWOOLE_SOCK_TCP);
            if (!$client->connect(self::MANAGE_HOST, self::PORT, -1)) {
                exit("connect failed. Error: {$client->errCode}\n");
            }

            if (in_array($command, TaskConstant::COMMAND_SET)) {
                $client->send(Protocol::encode($command, TaskConstant::commandIdByName($command)));
            } else {
                $client->send(Protocol::encode($command, TaskConstant::commandIdByName(TaskConstant::DATA)));
            }

            $recv = $client->recv();
            $client->close();
            return Protocol::decode($recv);
        };
    }

    public function start()
    {
        $server = new Server(self::LISTEN_HOST, self::PORT, SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $server->set($this->config);

        $server->on('Connect', [$this, 'onConnect']);
        $server->on('Close', [$this, 'onClose']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onFinish']);
//        $server->on('Start', [$this, 'onStart']); // SWOOLE_BASE 不存在
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('WorkerStop', [$this, 'onWorkerStop']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);
        $server->on('WorkerExit', [$this, 'onWorkerExit']);

        /**
         * 用户进程实现了广播功能，循环接收unixSocket的消息，并发给服务器的所有连接
         */
        $process = new Process(
            function ($process) use ($server) {
                $socket = $process->exportSocket();
                while (true) {
                    $msg = $socket->recv();

                    $command = TaskConstant::COMMAND_ID_LIST[$msg];
                    if ($command == TaskConstant::STATUS) {
                        $socket->send(json_encode($server->stats()));
                    } elseif ($command == TaskConstant::RELOAD) {
                        $server->reload(true);
                        $socket->send('reload ok');
                    } elseif ($command == TaskConstant::RESTART) {
                        Process::kill($server->manager_pid, SIGUSR1);
                        Process::kill($server->manager_pid, SIGUSR2);
                        $socket->send('restart ok');
                    } elseif ($command == TaskConstant::STOP) {
                        $server->shutdown();
                    }
                }
            },
            false,
            2,
            1
        );

        $server->addProcess($process);

        $server->on('Receive', function ($server, $fd, $reactorId, $data) use ($process) {
            $msg = Protocol::decode($data);
            if (in_array($c = TaskConstant::COMMAND_ID_LIST[$msg['commandId']], TaskConstant::USER_COMMAND)) {
                $socket = $process->exportSocket();
                $socket->send($msg['commandId']);
                $server->send($fd, Protocol::encode($socket->recv(), TaskConstant::commandIdByName(TaskConstant::DATA)));
            } else {
                $this->onReceive($server, $fd, $reactorId, $msg);
            }
        });

        $server->start();
    }

    public function onConnect(Server $server, int $fd, int $reactorId)
    {

    }

    //此回调函数在worker进程中执行
    public function onReceive(Server $server, int $fd, int $reactorId, $data)
    {
        $server->send($fd, Protocol::encode($data['body'], $data['commandId']));
    }

    public function onClose(Server $server, int $fd, int $reactorId)
    {

    }

    public function onStart(Server $server)
    {
        swoole_set_process_name("server:master");
    }

    public function onShutdown(Server $server)
    {
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        if($server->taskworker) {
            swoole_set_process_name("task-worker");
            echo "任务进程启动", $workerId, PHP_EOL;
        } else {
            swoole_set_process_name("event-worker");
            echo "工作进程启动", $workerId, PHP_EOL;
        }
    }

    public function onWorkerStop(Server $server, int $workerId)
    {
        if($server->taskworker) {
            swoole_set_process_name("task-worker");
            echo "任务进程停止", $workerId, PHP_EOL;
        } else {
            swoole_set_process_name("event-worker");
            echo "工作进程停止", $workerId, PHP_EOL;
        }
    }

    public function onWorkerExit(Server $server, int $workerId)
    {
        Timer::clearAll();
    }

    public function onTaskStart()
    {

    }

    public function onTask(Server $server, int $taskId, int $fromWorkerId, $data)
    {
        echo "[from-worker-id=$fromWorkerId][task-id=$taskId][data=$data]", PHP_EOL;

        //返回任务执行的结果
        $server->finish("$data -> task-finish");
    }

    public function onFinish(Server $server, int $taskId, string $data)
    {
        echo "[worker-id=$server->worker_id][task-id=$taskId][finish][data=$data]", PHP_EOL;
    }

    public function onManagerStart(Server $server)
    {
        swoole_set_process_name("server:manage");
        echo "管理进程启动", PHP_EOL;
    }

    public function onManagerStop(Server $server)
    {
        echo "管理进程停止", PHP_EOL;
    }

}