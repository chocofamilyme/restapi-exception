<?php

namespace Chocofamily\Exception\Handler;

use Chocofamily\Exception\NoticeException;
use Chocofamily\Exception\RestAPIException;
use Chocofamily\Logger\Adapter\Sentry;
use Phalcon\Config;
use Phalcon\Di;
use Phalcon\Logger\AdapterInterface;

class ExceptionIntervention
{
    const DEFAULT_ERROR_CODE = 500;

    /**
     * @var bool
     */
    private $productionEnvironment;

    /**
     * @var \Throwable
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
     * @var Config
     */
    private $config;

    /**
     * ExceptionIntervention constructor.
     *
     * @param bool             $productionEnvironment
     * @param AdapterInterface $logger
     * @param Sentry           $sentry
     */
    public function __construct(bool $productionEnvironment, AdapterInterface $logger, Sentry $sentry)
    {
        $this->productionEnvironment = $productionEnvironment;
        $this->logger                = $logger;
        $this->sentry                = $sentry;
        $this->config                = Di::getDefault()->get('config');
    }

    public function handle(): void
    {
        $this->setExceptionParameters();
        $this->handleIfRestApiException();
        $this->logException();
    }

    private function setExceptionParameters(): void
    {
        $this->code       = $this->exception->getCode() ?: self::DEFAULT_ERROR_CODE;
        $this->message    = $this->exception->getMessage();
        $this->debug      = [];
        $this->data       = [];
        $this->file       = $this->exception->getFile();
        $this->line       = $this->exception->getLine();
    }

    private function handleIfRestApiException(): void
    {
        if ($this->exception instanceof RestAPIException) {
            if ($debug = $this->exception->getDebug()) {
                foreach ($debug as $key => $value) {
                    if (is_string($value)) {
                        $this->sentry->setTag($key, $value);
                    }
                }
            }

            $this->data = $this->exception->getData();
        }
    }

    private function logException(): void
    {
        $this->sentryLogException();
        $this->loggerLogError();

        if (false === $this->isShownExceptionInProduction()) {
            $this->rewriteCodeAndMessageOnProductionEnvironment();
        }
    }

    private function rewriteCodeAndMessageOnProductionEnvironment(): void
    {
        if ($this->isProductionEnvironment()) {
            $this->code    = self::DEFAULT_ERROR_CODE;
            $this->message = 'Ошибка сервера';
        }
    }

    /**
     * @return bool
     */
    private function isShownExceptionInProduction(): bool
    {
        $showInProduction =
            $this->config->get('exceptions', new Config())->get('showInProduction', [NoticeException::class,]);
        foreach ($showInProduction as $show) {
            if ($this->exception instanceof $show) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    private function isProductionEnvironment(): bool
    {
        return $this->productionEnvironment === true;
    }

    private function sentryLogException(): void
    {
        $this->sentry->logException($this->exception, \Phalcon\Logger::ERROR);
    }

    private function loggerLogError(): void
    {
        $dontReport = $this->config->get('logger', new Config())->get('dontReport', [NoticeException::class,]);
        foreach ($dontReport as $ignore) {
            if ($this->exception instanceof $ignore) {
                return;
            }
        }

        $this->logger->error($this->exception->getMessage(), ['exception' => $this->exception]);
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
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

    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
    }
}
