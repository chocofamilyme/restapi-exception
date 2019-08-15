<?php

namespace Chocofamily\Exception\Handler;

use Chocofamily\Exception\NoticeException;
use Chocofamily\Exception\RestAPIException;
use Chocofamily\Logger\Adapter\Sentry;
use PDOException;
use Phalcon\Logger\AdapterInterface;

class ExceptionIntervention
{
    const DEFAULT_ERROR_CODE = 500;

    /**
     * @var bool
     */
    private $productionEnvironment;

    /**
     * @var
     */
    private $exception;

    /**
     * @var AdapterInterface
     */
    private $logger;

    /**
     * @var Sentry
     */
    private $sentry;

    /**
     * @var array
     */
    private $listOfExceptionsShownInProduction = [];

    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $message;

    /**
     * @var mixed
     */
    private $debug;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @var mixed
     */
    private $file;

    /**
     * @var mixed
     */
    private $line;

    /**
     * @var string
     */
    private $messageLog;

    /**
     * ExceptionIntervention constructor.
     * @param bool $productionEnvironment
     * @param AdapterInterface $logger
     * @param Sentry $sentry
     */
    public function __construct(bool $productionEnvironment, AdapterInterface $logger, Sentry $sentry)
    {
        $this->productionEnvironment = $productionEnvironment;
        $this->logger = $logger;
        $this->sentry = $sentry;
    }

    public function handle() : void
    {
        $this->setExceptionParameters();
        $this->handleIfRestApiException();
        $this->handleIfNotNoticeException();
    }

    private function setExceptionParameters() : void
    {
        $this->code = $this->exception->getCode() ?: self::DEFAULT_ERROR_CODE;
        $this->message = $this->exception->getMessage();
        $this->debug = [];
        $this->data = [];
        $this->file = $this->exception->getFile();
        $this->line = $this->exception->getLine();
        $this->messageLog = sprintf('%d %s in %s:%s', $this->code, $this->message, $this->file, $this->line);
    }

    private function handleIfRestApiException() : void
    {
        if ($this->exception instanceof RestAPIException) {
            if ($debug = $this->exception->getDebug()) {
                foreach ($debug as $key => $value) {
                    if (false == empty($value)) {
                        $this->sentry->setTag($key, $value);
                    }
                }

                $this->messageLog .= PHP_EOL . $this->exception->getDebugAsString();
            }

            $this->data = $this->exception->getData();
        }
    }

    private function handleIfNotNoticeException() : void
    {
        if ($this->exception instanceof NoticeException) {
            return;
        }

        if (!$this->exception instanceof PDOException) {
            $this->messageLog .= PHP_EOL.$this->exception->getTraceAsString();
        }

        $this->sentryLogException();
        $this->loggerLogError();
        $this->rewriteCodeAndMessageOnProductionEnvironment();
    }

    private function rewriteCodeAndMessageOnProductionEnvironment() : void
    {
        if ($this->isProductionEnvironment() && $this->hideExceptionInProduction()) {
            $this->code = 500;
            $this->message = 'Ошибка сервера';
        }
    }

    /**
     * @return bool
     */
    private function hideExceptionInProduction() : bool
    {
        foreach ($this->listOfExceptionsShownInProduction as $exceptionShownInProduction) {
            if ($this->exception instanceof $exceptionShownInProduction) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    private function isProductionEnvironment() : bool
    {
        if ($this->productionEnvironment === true) {
            return true;
        }

        return false;
    }

    public function setListOfExceptionsShownInProduction(array $list) : void
    {
        $this->listOfExceptionsShownInProduction = $list;
    }

    private function sentryLogException() : void
    {
        $this->sentry->logException($this->exception, [], \Phalcon\Logger::ERROR);
    }

    private function loggerLogError() : void
    {
        $this->logger->error($this->messageLog);
    }

    /**
     * @return int
     */
    public function getCode() : int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage() : string
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    public function setException(\Throwable $exception) : void
    {
        $this->exception = $exception;
    }
}
