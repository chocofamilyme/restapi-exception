# RestAPI - обработка исключений

Обработка исключений/ошибок для шаблона RestAPI

## Требуется
    - Phalcon > 3.0.0
    - RestAPI
    - chocofamilyme/logformatter
    
## Использование
В проекте должен настроен сервис для логирования и sentry из репозитория chocofamilyme/logformatter.

````php
return [
    $di  = new Phalcon\Di\FactoryDefault()
    $app = new Phalcon\Mvc\Micro($di);
    
    $apiExceptions = new ApiExceptions($app, true);
    $apiExceptions->register();
];
````

## Показывать определенные исключения на бою
**Внимание это только пример!**
````php
return [
    $di  = new Phalcon\Di\FactoryDefault()
    $app = new Phalcon\Mvc\Micro($di);
    
    $apiExceptions = new ApiExceptions($app, true);
    $apiExceptions->setListOfExceptionsShownInProduction([
        \Exception::class,
        \PDOException::class
    ]);
    $apiExceptions->register();
];
````