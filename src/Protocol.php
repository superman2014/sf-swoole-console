<?php

namespace Superman2014\SfSwooleConsole;

/**
 * Class Protocol
 * @package Superman2014\SfSwooleConsole
 *
 *
 * 包结构
 *
 * 字段	字节数	说明
 * 包头	定长	每一个通信消息必须包含的内容
 * 包体	不定长	根据每个通信消息的不同产生变化
 *
 * 其中包头详细内容如下：
 *
 * 字段        字节数 类型  说明
 * pkg_len	    2   ushort	整个包的长度，不超过4K
 * version	    1 	uchar	通讯协议版本号
 * command_id	2 	ushort	消息命令ID
 * result	    2 	short	请求时不起作用；请求返回时使用
 *
 */
class Protocol
{

    const VERSION = '1';

    const HEADER = 7;

    const PACKAGE_LENGTH = 4096;

    public static function partHeader($bodyLen, $version, $commandId, $result)
    {
        return pack("nCns", $bodyLen, $version, $commandId, $result);
    }

    public static function partBody($msg)
    {
        return pack("a". strlen($msg), $msg);
    }

    public static function encode($msg, $commandId, $result = 0)
    {

        return self::partHeader(strlen($msg), self::VERSION, $commandId, $result)
            . self::partBody($msg);

    }

    public static function decode($msg)
    {

        $header = substr($msg, 0, self::HEADER);
        $p = unpack('nbodyLen/Cversion/ncommandId/sresult', $header);
        $len = $p['bodyLen'];
        $bodyPack = unpack("a{$len}body", substr($msg, self::HEADER, $len));
        return array_merge($bodyPack, $p);
    }

    public static function main()
    {
        $msg = self::encode("hello", "1001", 0);

        var_dump(self::decode($msg));

        //
//        array(5) {
//        ["body"]=>
//  string(5) "hello"
//        ["bodyLen"]=>
//  int(5)
//  ["version"]=>
//  int(1)
//  ["commandId"]=>
//  int(1001)
//  ["result"]=>
//  int(0)
//    }
    }
}

//Protocol::main();
