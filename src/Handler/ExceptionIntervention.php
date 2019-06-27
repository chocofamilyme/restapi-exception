<?php

namespace Chocofamily\Exception\Handler;

use Chocofamily\Exception\NoticeException;
use Chocofamily\Exception\RestAPIException;
use Chocofamily\Logger\Adapter\Sentry;
use PDOException;
use Phalcon\Logger\AdapterInterface;
use Throwable;

class ExceptionIntervention
{
    private $productionEnvironment;
    private $exception;
    private $logger;
    private $sentry;
    private $listOfExceptionsShownInProduction = [
        \League\OAuth2\Server\Exception\OAuthServerException::class
    ];

    private $code;
    private $message;
    private $debug;
    private $data;
    private $file;
    private $line;
    private $messageLog;

    public function __construct($productionEnvironment, Throwable $exception, AdapterInterface $logger, Sentry $sentry)
    {
        $this->productionEnvironment = $productionEnvironment;
        $this->exception = $exception;
        $this->logger = $logger;
        $this->sentry = $sentry;

        $this->setExceptionParameters();
        $this->handleIfRestApiException();
        $this->handleIfNotNoticeException();
    }

    private function setExceptionParameters()
    {
        $this->code = $this->exception->getCode();
        $this->message = $this->exception->getMessage();
        $this->debug = [];
        $this->data = [];
        $this->file = $this->exception->getFile();
        $this->line = $this->exception->getLine();
        $this->messageLog = sprintf('%d %s in %s:%s', $this->code, $this->message, $this->file, $this->line);
    }

    private function handleIfRestApiException()
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

    private function handleIfNotNoticeException()
    {
        if ($this->exception instanceof NoticeException) {
            return;
        }

        if (!$this->exception instanceof PDOException) {
            $this->messageLog .= PHP_EOL . $this->exception->getTraceAsString();
        }

        $this->sentryLogException();
        $this->loggerLogError();
        $this->rewriteCodeAndMessageOnProductionEnvironment();

    }

    private function rewriteCodeAndMessageOnProductionEnvironment()
    {
        if ($this->isProductionEnvironment() && $this->hideExceptionInProduction()) {
            $this->code = 500;
            $this->message = 'Ошибка сервера';
        }
    }

    private function hideExceptionInProduction()
    {
        foreach ($this->listOfExceptionsShownInProduction as $exceptionShownInProduction) {
            if ($this->exception instanceof $exceptionShownInProduction) {
                return false;
            }
        }

        return true;
    }

    private function isProductionEnvironment()
    {
        if ($this->productionEnvironment === true) {
            return true;
        }

        return false;
    }

    private function sentryLogException()
    {
        $this->sentry->logException($this->exception, [], \Phalcon\Logger::ERROR);
    }

    private function loggerLogError()
    {
        $this->logger->error($this->messageLog . PHP_EOL . substr($this->exception->getTraceAsString(), 0, 500));
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function getData()
    {
        return $this->data;
    }
}