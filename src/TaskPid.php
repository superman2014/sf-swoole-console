<?php

namespace Superman2014\SfSwooleConsole;

class TaskPid
{
    const NAME = 'sf-swoole-console';

    const MASTER_PID = 'master_pid';

    const MANAGER_PID = 'manager_pid';

    protected function filename()
    {
        return sprintf("/tmp/%s.pid", self::NAME);
    }

    public function write(string $pids)
    {
        $fp = fopen($this->filename(), "w+");

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $pids);
            fflush($fp);
            flock($fp, LOCK_UN);
        } else {
            echo sprintf("write %s failure", $this->filename()), PHP_EOL;
            return false;
        }

        fclose($fp);

        return true;
    }

    public function read($process)
    {
        $fp = fopen($this->filename(), "r+");

        if (flock($fp, LOCK_SH)) {
            $line = fgets($fp, 1024);
            flock($fp, LOCK_UN);
        } else {
            echo sprintf("get pid failure"), PHP_EOL;
        }

        fclose($fp);

        return json_decode($line, true)[$process];
    }

    public function exists()
    {
        return file_exists($this->filename());
    }

    public function del()
    {
        if ($this->exists()) {
            unlink($this->filename());
        }
    }
}
