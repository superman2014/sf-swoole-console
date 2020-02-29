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
}
