<?php

namespace Chocofamily\Exception\Handler;

use Phalcon\Di\Injectable;
use Chocofamily\Exception\BaseException;
use Chocofamily\Exception\NoticeException;
use Phalcon\Logger\AdapterInterface;

/**
 * Class ApiExceptions
 *
 * @package RestAPI\Exception\Handler
 */
class ApiExceptions extends Injectable
{
    const PRODUCTION  = true;
    const DEVELOPMENT = false;

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
        $code       = $exception->getCode();
        $message    = $exception->getMessage();
        $file       = $exception->getFile();
        $line       = $exception->getLine();
        $messageLog = sprintf('%d %s in %s:%s', $code, $message, $file, $line);
        $debug      = [];

        if ($exception instanceof BaseException && $exception->getDebug()) {
            foreach ($exception->getDebug() as $key => $value) {
                $this->sentry->setTag($key, $value);
            }

            $messageLog .= PHP_EOL.$exception->getDebugAsString();
        }

        if (false == $exception instanceof NoticeException) {
            $this->sentry->logException($exception, [], \Phalcon\Logger::ERROR);
            $this->logger->error($messageLog.PHP_EOL.$exception->getTraceAsString());

            if (self::PRODUCTION === $this->environment) {
                $code    = 500;
                $message = 'Ошибка сервера';
            }
        }

        return $this->apiResponse($code, $message, $debug);
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
        if (error_reporting() && $errorNumber) {
            throw new \ErrorException($errorString, $errorNumber, 1, $errorFile, $errorLine);
        }
    }

    /**
     * @param int    $code
     * @param string $message
     * @param array  $debug
     *
     * @return array
     */
    private function apiResponse(int $code = 500, string $message = 'Internal Server Error', array $debug = []): array
    {
        $data = null;

        if (self::DEVELOPMENT === $this->environment && $debug) {
            $data          = [];
            $data['debug'] = $debug;
        }

        $response = $this->response($message, $data, $code, 'error');

        if (PHP_SAPI == 'cli') {
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
     * @param string $message
     * @param null   $data
     * @param int    $error_code
     * @param string $status
     *
     * @return array
     */
    public function response(string $message, $data = null, $error_code = 0, string $status = 'success'): array
    {
        return [
            'error_code' => $error_code,
            'status'     => $status,
            'message'    => $message,
            'data'       => $data,
        ];
    }
}
