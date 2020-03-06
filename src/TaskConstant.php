<?php

namespace Superman2014\SfSwooleConsole;

class TaskConstant
{
    const COMMAND_SET = [
        self::START,
        self::STOP,
        self::RESTART,
        self::RELOAD,
        self::PING,
        self::STATUS,
        self::USAGE,
    ];

    const USER_COMMAND = [
        self::STATUS,
        self::RELOAD,
        self::RESTART,
        self::STOP,
    ];

    const START = 'start';

    const STOP = 'stop';

    const STATUS = 'status';

    const RELOAD = 'reload';

    const RESTART = 'restart';

    const PING = 'ping';

    const USAGE = 'usage';

    const DATA = 'data';

    const COMMAND_ID_LIST = [
        1001 => self::START,
        1002 => self::STOP,
        1003 => self::RESTART,
        1004 => self::RELOAD,
        1005 => self::PING,
        1006 => self::STATUS,
        1007 => self::DATA,
    ];

    public static function commandIdByName($name)
    {
       return array_flip(self::COMMAND_ID_LIST)[$name];
    }
}
