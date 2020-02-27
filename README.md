# RestAPI - обработка исключений

Обработка исключений/ошибок для шаблона RestAPI

## Требуется
    - Phalcon > 3.0.0
    - RestAPI
    - chocofamilyme/logformatter
    
## Использование
В проекте должен быть настроен сервис для логирования и sentry из репозитория chocofamilyme/logformatter.

````php
return [
    $di  = new Phalcon\Di\FactoryDefault()
    $app = new Phalcon\Mvc\Micro($di);
    
    $apiExceptions = new ApiExceptions($app, true);
    $apiExceptions->register();
];
````

## Показывать определенные исключения на бою
В проекте должен быть настроен файл конфигурации config/exceptions.php <br/>
**Внимание это только пример!**
````php
return [
    'showInProduction' => [
        \PDOException::class,
        \Chocofamily\Exception\NoticeException::class
    ],
];
````
Примечание: метод setListOfExceptionsShownInProduction, который вызывалася в провайдере, был удален 

## Логировать определенные исключения
#### Logger
В проекте должен быть настроен файл конфигурации config/logger.php <br/>
**Внимание это только пример!**
````php
return [
    return [
        # Ваша конфигурация
        
        'dontReport'   => [
            \PDOException::class,
            \Chocofamily\Exception\NoticeException::class
        ],
    ];
];
````
#### Sentry
Посмотреть https://github.com/chocofamilyme/logformatter
