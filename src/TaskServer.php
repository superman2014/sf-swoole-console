<?php

namespace Superman2014\SfSwooleConsole;

use Swoole\Server;
use Swoole\Process;
use Swoole\Client;

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
        'request_slowlog_file' => '/tmp/trace.log',
        'request_slowlog_timeout' => 2, // 设置请求超时时间为2秒
        'trace_event_worker' => true, //跟踪 Task 和 Worker 进程

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
                 $this->clientSendCommand(TaskConstant::STOP)();
                break;
            case TaskConstant::STATUS:
                $recv = $this->clientSendCommand(TaskConstant::STATUS)();
                var_dump($recv['body']);
                break;
            case TaskConstant::PING:
                var_dump($this->clientSendCommand(TaskConstant::PING)());
                break;
            case TaskConstant::RELOAD:
                $recv = $this->clientSendCommand( TaskConstant::RELOAD)();
                echo $recv['body'],PHP_EOL;
                break;
            case TaskConstant::RESTART:
                $recv = $this->clientSendCommand( TaskConstant::RESTART)();
                echo $recv['body'],PHP_EOL;
                break;
            case TaskConstant::ADD:

                $params = [
                    1,
                    2,
                    3,
                ];

                $recv = $this->clientSendCommand(
                    TaskConstant::ADD
                )(json_encode($params));

                echo sprintf("add input array:[ %s]" . PHP_EOL, implode(',', $params));
                echo sprintf("result:%s".PHP_EOL, $recv['body']);

                break;
        }
    }

    public function clientSendCommand($command)
    {
        return function ($params = null) use ($command) {
            if (empty($params)) {
                $params = $command;
            }

            $client = new Client(SWOOLE_SOCK_TCP);
            if (!$client->connect(self::MANAGE_HOST, self::PORT, -1)) {
                exit("connect failed. Error: {$client->errCode}\n");
            }

            if (in_array($command, TaskConstant::commandLine())) {
                $client->send(Protocol::encode($params, TaskConstant::commandIdByName($command)));
            } else {
                exit("client not implement {$command} \n");
            }

            $recv = $client->recv();
            $client->close();
            if (empty($recv)) {
                return "";
            }
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
                $this->processUserProcess($process, $server);
            },
            false,
            2,
            1
        );

        $server->addProcess($process);

        $server->on('Receive', function ($server, $fd, $reactorId, $data) use ($process) {
            $this->onReceive($server, $fd, $reactorId, $data, $process);
        });

        $server->start();
    }

    public function processUserProcess($process, $server)
    {
        $socket = $process->exportSocket();
        while (true) {
            $command = $socket->recv();

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
            } elseif ($command == TaskConstant::PING) {
                $socket->send('pong');
            } else {
                $socket->send('not system command');
            }
        }
    }

    public function onConnect(Server $server, int $fd, int $reactorId)
    {

    }

    //此回调函数在worker进程中执行
    public function onReceive(Server $server, int $fd, int $reactorId, $data, $process)
    {
        if (strlen($data) <= 7) {
            $server->send(
                $fd,
                Protocol::encode(
                    'request data is exception',
                    TaskConstant::nameByCommandId(TaskConstant::EX),
                    0
                )
            );
            return;
        }
        $msg = Protocol::decode($data);

        if (!$command = TaskConstant::nameByCommandId($msg['commandId'])) {
            $server->send(
                $fd,
                Protocol::encode(
                    'not found command ID:'. $msg['commandId'],
                    TaskConstant::nameByCommandId(TaskConstant::EX),
                    0
                )
            );
            return;
        }

        if (in_array($command, TaskConstant::SYS_COMMAND)) {
            $socket = $process->exportSocket();
            $socket->send($command);
            $server->send(
                $fd,
                Protocol::encode($socket->recv(), TaskConstant::commandIdByName($command))
            );
        } elseif (in_array($command, TaskConstant::USER_COMMAND)) {

            switch ($command) {
                case TaskConstant::ADD:
                    $params = json_decode($msg['body'], true);
                    $ex = 0;
                    if (json_last_error() == JSON_ERROR_NONE) {
                        $result = array_sum($params);
                    } else {
                        $result = 0;
                        $ex = 1;
                    }
                    $server->send($fd, Protocol::encode($result, $msg['commandId'], $ex));
                    break;
                default:
                    $server->send($fd, Protocol::encode('not implement command ID', $msg['commandId'], 0));
            }
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
