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
    ];

    const LISTEN_HOST = '0.0.0.0';

    const MANAGE_HOST = '127.0.0.1';

    const PORT = 9501;

    const ADD = 'add';

    const OP = [
        self::ADD,
    ];

    public function __construct($command)
    {
        switch ($command) {
            case TaskConstant::START:
                $this->start();
                break;
            case TaskConstant::STOP:
                $recv = $this->clientSendCommand()(TaskConstant::STOP);
                Process::kill($recv, SIGTERM);
                break;
            case TaskConstant::STATUS:
                $this->clientSendCommand()(TaskConstant::STATUS);
                break;
            case TaskConstant::PING:
                break;
            case TaskConstant::RELOAD:
                $this->clientSendCommand()(TaskConstant::RELOAD);
                break;
            case TaskConstant::RESTART:
                $this->clientSendCommand()(TaskConstant::RESTART);
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
            $client->send($command);
            $recv = $client->recv();
            $client->close();
            return $recv;
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
                    if ($msg == TaskConstant::STATUS) {
                        $socket->send(json_encode($server->stats(), 128));
                    } elseif ($msg == TaskConstant::RELOAD) {
                        $server->reload(true);
                        $socket->send('reload ok');
                    } elseif ($msg == TaskConstant::RESTART) {
                        Process::kill($server->manager_pid, SIGUSR1);
                        Process::kill($server->manager_pid, SIGUSR2);
                        $socket->send('restart ok');
                    } elseif ($msg == TaskConstant::STOP) {
                        $socket->send($server->manager_pid);
                    }
                }
            },
            false,
            2,
            1
        );

        $server->addProcess($process);

        $server->on('Receive', function ($server, $fd, $reactorId, $data) use ($process) {
            if (in_array($data, TaskConstant::USER_COMMAND)) {
                $socket = $process->exportSocket();
                $socket->send($data);
                $server->send($fd, $socket->recv());
            } else {
                $this->onReceive($server, $fd, $reactorId, $data);
            }
        });

        $server->start();
    }

    public function onConnect(Server $server, int $fd, int $reactorId)
    {

    }

    //此回调函数在worker进程中执行
    public function onReceive(Server $server, int $fd, int $reactorId, string $data)
    {
        echo "[from-reactor-id=$reactorId][worker-id=$server->worker_id]][data=$data]", PHP_EOL;

        $data = json_decode(trim($data), true);

        if (JSON_ERROR_NONE == json_last_error()) {
            $ret = false;
            if (!empty($data['op']) && in_array($data['op'], self::OP) && !empty($data['data'])) {
                switch ($data['op']) {
                    case self::ADD:
                        $tasks = array_chunk($data['data'], 2);
                        do {
                            $results = $server->taskWaitMulti($tasks, 10);

                            if (count($results) == 1) {
                                break;
                            } else {
                                $tasks = array_chunk($results, 2);
                            }
                        } while (true);
                        $ret = $results[0];
                        break;
                }
            }
            $server->send($fd, $ret . PHP_EOL);
        } else {
            $server->send($fd, json_last_error_msg() . PHP_EOL);
        }
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
        echo "[from-worker-id=$fromWorkerId][task-id=$taskId][data=", json_encode($data), "]", PHP_EOL;

        if (is_array($data)) {
            return $data[0] + $data[1];
        } else {
            return 0;
        }
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