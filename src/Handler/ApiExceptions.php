<?php

namespace Chocofamily\Exception\Handler;

use Chocofamily\Exception\RestAPIException;
use Phalcon\Di\Injectable;
use Chocofamily\Exception\NoticeException;
use Phalcon\Logger\AdapterInterface;

/**
 * Class ApiExceptions
 *
 * @package RestAPI\Exception\Handler
 */
class ApiExceptions extends Injectable
{
    const PRODUCTION         = true;
    const DEVELOPMENT        = false;
    const DEFAULT_ERROR_CODE = 500;

    /** @var  AdapterInterface */
    private $logger;

    /** @var \Chocofamily\Logger\Adapter\Sentry */
    private $sentry;

    /** @var  bool */
    private $environment;

    /**
     * The internal application core.
     *
     * @var \Phalcon\Application
     */
    private $app;

    public function __construct($app, $environment = self::DEVELOPMENT)
    {
        $this->app         = $app;
        $this->logger      = $this->getDI()->get('logger');
        $this->sentry      = $this->getDI()->getShared('sentry');
        $this->environment = $environment;
    }

    /**
     * Register handlers
     */
    public function register()
    {
        /** if development enable error displaying */
        ini_set('display_errors', self::DEVELOPMENT === $this->environment);
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
        $code       = $exception->getCode() ?: self::DEFAULT_ERROR_CODE;
        $message    = $exception->getMessage();
        $file       = $exception->getFile();
        $line       = $exception->getLine();
        $messageLog = sprintf('%d %s in %s:%s', $code, $message, $file, $line);
        $debug      = [];
        $data       = [];

        if ($exception instanceof RestAPIException) {
            if ($debug = $exception->getDebug()) {
                foreach ($debug as $key => $value) {
                    if (false == empty($value)) {
                        $this->sentry->setTag($key, $value);
                    }
                }

                $messageLog .= PHP_EOL.$exception->getDebugAsString();
            }

            $data = $exception->getData();
        }

        if (false == $exception instanceof NoticeException) {
            if (false == $exception instanceof \PDOException) {
                $messageLog .= PHP_EOL.substr($exception->getTraceAsString(), 0, 500);
            }

            $this->sentry->logException($exception, [], \Phalcon\Logger::ERROR);
            $this->logger->error($messageLog);

            if (self::PRODUCTION === $this->environment) {
                $code    = 500;
                $message = 'Ошибка сервера';
            }
        }

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
        if (error_reporting()) {
            $errorNumber = $errorNumber ?: self::DEFAULT_ERROR_CODE;

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
        if (self::DEVELOPMENT === $this->environment && $debug) {
            $data['debug'] = $debug;
        }

        $response = $this->response($message, $data, $code, 'error');

        if (PHP_SAPI == 'cli') {
            print_r($response);
        } else {
            $this->app->response->setJsonContent($response);
            $this->app->response->setHeader('Cache-Control', 'no-store');
            $this->app->response->send();

            if ($this->app instanceof \Phalcon\Mvc\Micro) {
                $this->app->stop();
            }
        }

        return $response;
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
        if (is_array($data) && empty($data)) {
            $data = null;
        }

        return [
            'error_code' => $error_code,
            'status'     => $status,
            'message'    => $message,
            'data'       => $data,
        ];
    }
}
