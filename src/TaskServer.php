<?php

namespace Superman2014\SfSwooleConsole;

use Swoole\Server;
use Swoole\Process;

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
    ];

    const HOST = '0.0.0.0';

    const PORT = 9501;

    public function __construct($command)
    {
        switch ($command) {
            case TaskConstant::START:
                $this->start();
                break;
            case TaskConstant::STOP:
                $this->stop();
                break;
            case TaskConstant::STATUS:
                $this->status();
                break;
            case TaskConstant::PING:
                break;
            case TaskConstant::RELOAD:
                $this->reload();
                break;
            case TaskConstant::RESTART:
                $this->restart();
                break;
        }
    }

    public function reload()
    {
        $this->stop();
        $this->start();
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }

    public function status()
    {

    }

    public function start()
    {
        $taskPid = new TaskPid();
        if ($taskPid->exists()) {
            echo "server started", PHP_EOL;
            return;
        }

        $server = new Server(self::HOST, self::PORT, SWOOLE_BASE, SWOOLE_SOCK_TCP);

        $server->set($this->config);

        $server->on('Connect', [$this, 'onConnect']);
        $server->on('Receive', [$this, 'onReceive']);
        $server->on('Close', [$this, 'onClose']);
        $server->on('Task', [$this, 'onTask']);
        $server->on('Finish', [$this, 'onFinish']);
//        $server->on('Start', [$this, 'onStart']); // SWOOLE_BASE 不存在
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('WorkerStop', [$this, 'onWorkerStop']);
        $server->on('ManagerStart', [$this, 'onManagerStart']);
        $server->on('Shutdown', [$this, 'onShutdown']);

        $server->start();
    }

    public function stop()
    {
        $taskPid = new TaskPid();
        if ($taskPid->exists()) {
            $masterPid = $taskPid->read(TaskPid::MASTER_PID);
            Process::kill($masterPid, SIGTERM);
        }
    }

    public function onConnect(Server $server, int $fd, int $reactorId)
    {

    }

    //此回调函数在worker进程中执行
    public function onReceive(Server $server, int $fd, int $reactorId, string $data)
    {
        echo "[from-reactor-id=$reactorId][worker-id=$server->worker_id]][data=$data]", PHP_EOL;

        //投递异步任务
        $taskId = $server->task($data);

        $server->send($fd, "receive success, task-id:" . $taskId . PHP_EOL);
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
        $taskPid = new TaskPid();
        $taskPid->del();
    }

    public function onWorkerStart(Server $server, int $workerId)
    {
        $pids = [
            'master_pid' => $server->master_pid,
            'manager_pid' => $server->manager_pid,
        ];

        $taskPid = new TaskPid();
        if (!$taskPid->write(json_encode($pids))) {
            echo "write pid failure", PHP_EOL;
        }

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