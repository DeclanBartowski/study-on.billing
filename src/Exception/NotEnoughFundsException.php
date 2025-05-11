<?php

namespace App\Exception;

class NotEnoughFundsException extends \Exception
{
    public function __construct($message = "Недостаточно средств на счету", $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
