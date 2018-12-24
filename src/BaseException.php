<?php

namespace Chocofamily\Exception;

use Throwable;

class BaseException extends \Exception implements RestAPIException
{

    private $debug = [];

    public function __construct(string $message = "", int $code = 0, array $debug = [], Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setDebug($debug);
    }

    /**
     * @return array
     */
    public function getDebug(): array
    {
        return $this->debug;
    }

    /**
     * @param array $debug
     */
    public function setDebug(array $debug = [])
    {
        $this->debug = $debug;
    }

    /**
     * @return string
     */
    public function getDebugAsString(): string
    {
        return print_r($this->debug, true);
    }
}
