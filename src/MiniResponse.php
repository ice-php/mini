<?php
declare(strict_types=1);

namespace icePHP;

class SMiniResponse
{

    private $buffer = [];

    public function write($msg)
    {
        $this->buffer[] = $msg;
    }

    public function writeLn($msg = '')
    {
        self::write($msg . "\r\n");
    }

    public function getBuffer()
    {
        return implode('', $this->buffer);
    }
}