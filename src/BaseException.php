<?php

namespace Chocofamily\Exception;

use JsonSerializable;
use Throwable;

class BaseException extends \Exception implements RestAPIException, JsonSerializable
{
    /**
     * @var array
     */
    private $debug = [];

    /**
     * @var array
     */
    private $data = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        array $debug = [],
        Throwable $previous = null,
        $data = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->setDebug($debug);
        $this->setData($data);
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
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getDebugAsString(): string
    {
        return print_r($this->debug, true);
    }

    /**
     * @return mixed
     */
    public function jsonSerialize()
    {
        return [
            $this->normalizeException($this),
            [
                'debug' => $this->getDebug(),
                'data'  => $this->getData(),
            ],
        ];
    }

    /**
     * @param Throwable $e
     * @param int       $depth
     *
     * @return array
     */
    protected function normalizeException(Throwable $e, int $depth = 0): array
    {
        $data = [
            'class'   => \get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile().':'.$e->getLine(),
        ];

        $trace = $e->getTrace();
        foreach ($trace as $frame) {
            if (isset($frame['file'])) {
                $data['trace'][] = $frame['file'].':'.$frame['line'];
            }
        }

        if ($previous = $e->getPrevious()) {
            $data['previous'] = $this->normalizeException($previous, $depth + 1);
        }

        return $data;
    }
}
