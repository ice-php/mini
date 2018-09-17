<?php

declare(strict_types=1);

namespace icePHP;

class MiniException extends \Exception
{
    //中途退出
    const SOCKET_EXIT=4;

    //Http模式指定错误
    const HTTP_METHOD_ERROR=5;

    //当前仅支持POST/GET请求
    const HTTP_METHOD_UNSUPPORTED=6;
}