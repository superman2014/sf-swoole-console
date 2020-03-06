<?php

namespace Superman2014\SfSwooleConsole;

class TaskConstant
{
    // 系统指令
    const SYS_COMMAND = [
        self::START,
        self::STOP,
        self::RESTART,
        self::RELOAD,
        self::STATUS,
        self::PING,
    ];

    // 用户自定义的指令
    const USER_COMMAND = [
        self::ADD,
    ];

    const START = 'start';
    const STOP = 'stop';
    const STATUS = 'status';
    const RELOAD = 'reload';
    const RESTART = 'restart';
    const PING = 'ping';
    const USAGE = 'usage';

    const ADD = 'add';
    const EX = 'exception';

    const COMMAND_ID_LIST = [
        1001 => self::START,
        1002 => self::STOP,
        1003 => self::RESTART,
        1004 => self::RELOAD,
        1005 => self::PING,
        1006 => self::STATUS,
        2000 => self::ADD,
        2001 => self::EX,
    ];

    public static function commandIdByName($name)
    {
       return array_flip(self::COMMAND_ID_LIST)[$name];
    }

    public static function nameByCommandId($commandId)
    {
        echo $commandId, PHP_EOL;
        if (empty(self::COMMAND_ID_LIST[$commandId])) {
            return false;
        }

        return self::COMMAND_ID_LIST[$commandId];
    }

    public static function commandLine()
    {
        return array_merge(
            self::USER_COMMAND,
            self::SYS_COMMAND
        );
    }

    public static function commandUsage()
    {
        return sprintf("<%s>".PHP_EOL, join('|', static::commandLine()));
    }
}
