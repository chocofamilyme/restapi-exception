<?php

namespace Chocofamily\Exception\Handler;

use Chocofamily\Logger\Adapter\Sentry;
use Phalcon\Di\Injectable;
use Phalcon\Logger\AdapterInterface;

/**
 * Class ApiExceptions
 *
 * @package Chocofamily\Exception\Handler
 */
class ApiExceptions extends Injectable
{
    /** @var AdapterInterface */
    private $logger;

    /** @var Sentry */
    private $sentry;

    /** @var bool */
    private $productionEnvironment;

    /**
     * The internal application core.
     *
     * @var \Phalcon\Application
     */
    private $app;

    /**
     * @var ExceptionIntervention
     */
    private $exceptionIntervention;

    public function __construct($app, $productionEnvironment = true)
    {
        $this->app                   = $app;
        $this->logger                = $this->getDI()->get('logger');
        $this->sentry                = $this->getDI()->getShared('sentry');
        $this->productionEnvironment = $productionEnvironment;
        $this->exceptionIntervention =
            new ExceptionIntervention($this->isProductionEnvironment(), $this->logger, $this->sentry);
    }

    /**
     * Register handlers
     */
    public function register()
    {
        /** if development enable error displaying */
        ini_set('display_errors', $this->isDevelopmentEnvironment());
        /** report every error, notice, warning */
        error_reporting(E_ALL);
        /** Register error handler */
        set_error_handler([$this, 'handleErrors']);
        /** Register exception handler */
        set_exception_handler([$this, 'handleExceptions']);
    }

    /**
     * Custom Exception handler
     *
     * @param \Throwable $exception
     *
     * @return array
     */
    public function handleExceptions(\Throwable $exception): array
    {
        $this->report($exception);

        return $this->render();
    }

    /**
     * @param \Throwable $exception
     */
    public function report(\Throwable $exception)
    {
        $this->exceptionIntervention->setException($exception);
        $this->exceptionIntervention->handle();
    }

    /**
     * @return array
     */
    public function render(): array
    {
        $code    = $this->exceptionIntervention->getCode();
        $message = $this->exceptionIntervention->getMessage();
        $debug   = $this->exceptionIntervention->getDebug();
        $data    = $this->exceptionIntervention->getData();

        return $this->apiResponse($code, $message, $debug, $data);
    }

    /**
     * Custom error handler
     *
     * @param int    $errorNumber
     * @param string $errorString
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @throws \ErrorException
     */
    public function handleErrors(int $errorNumber, string $errorString, string $errorFile, int $errorLine)
    {
        if (error_reporting() && $errorNumber != 0) {
            throw new \ErrorException($errorString, $errorNumber, 1, $errorFile, $errorLine);
        }
    }

    /**
     * @param int    $code
     * @param string $message
     * @param array  $debug
     *
     * @param array  $data
     *
     * @return array
     */
    private function apiResponse(
        int $code = 500,
        string $message = 'Internal Server Error',
        array $debug = [],
        $data = []
    ): array {
        $data     = $this->setDataDebugInDevelopmentEnvironment($data, $debug);
        $response = $this->response($message, $data, $code, 'error');

        if ($this->isCliApplication()) {
            print_r($response);
        } else {
            $this->app->response->setJsonContent($response);
            $this->app->response->send();

            if ($this->app instanceof \Phalcon\Mvc\Micro) {
                $this->app->stop();
            }
        }

        return $response;
    }

    /**
     * @param $data
     * @param $debug
     *
     * @return mixed
     */
    private function setDataDebugInDevelopmentEnvironment($data, $debug)
    {
        if ($this->isDevelopmentEnvironment() && !empty($debug)) {
            $data['debug'] = $debug;
        }

        return $data;
    }

    /**
     * @return bool
     */
    private function isCliApplication()
    {
        return 'cli' == php_sapi_name();
    }

    /**
     * @param string $message
     * @param array  $data
     * @param int    $error_code
     * @param string $status
     *
     * @return array
     */
    public function response(string $message, $data = [], int $error_code = 0, string $status = 'success'): array
    {
        $data = $this->nullDataIfEmpty($data);

        return [
            'error_code' => $error_code,
            'status'     => $status,
            'message'    => $message,
            'data'       => $data,
        ];
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function nullDataIfEmpty($data)
    {
        if (is_array($data) && empty($data)) {
            $data = null;
        }

        return $data;
    }

    /**
     * @return bool
     */
    private function isDevelopmentEnvironment(): bool
    {
        return $this->productionEnvironment === false;
    }

    /**
     * @return bool
     */
    private function isProductionEnvironment(): bool
    {
        return $this->productionEnvironment === true;
    }
}
