Jasny Error Handler
===

[![Build Status](https://secure.travis-ci.org/jasny/error-handler.png?branch=master)](http://travis-ci.org/jasny/error-handler)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/error-handler/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/error-handler/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/error-handler/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/error-handler/?branch=master)

Error handler as PSR-7 compatible middleware.

The error will catch [Exceptions](http://php.net/manual/en/class.exception.php) and
[Errors](http://php.net/manual/en/class.error.php).

You can use this middleware with:

* [Jasny Router](https://github.com/jasny/router)
* [Relay](https://github.com/relayphp/Relay.Relay)
* [Expressive](http://framework.zend.com/expressive)
* [Slim 3](http://www.slimframework.com)

You can log with a [PSR-3 compatible logger](http://www.php-fig.org/psr/psr-3/) like
[Monolog](https://github.com/Seldaek/monolog).


Installation
---

The Jasny Error Handler package is available on [packagist](https://packagist.org/packages/jasny/error-handler).
Install it using composer:

    composer require jasny/error-handler-middleware


Usage
---

```php
use Jasny\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$log = new Logger();
$log->pushHandler(new StreamHandler('path/to/your.log'));

$errorHandler = new ErrorHandler();

// Log errors
$errorHandler->setLogger($log);

// Log fatal errors and warnings in addition to catchable errors
$errorHandler->alsoLog(E_PARSE | E_ERROR | E_WARNING | E_USER_WARNING);

// PHP 5 support
$errorHandler->converErrorsToExceptions();
```

For example use it with **Relay**:

```php
use Relay\RelayBuilder;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$relay = new RelayBuilder();
$dispatcher = $relay->newInstance([$errorHandler]);

$response = $dispatcher((new ServerRequest())->withGlobalEnvironment(), new Response());
```

Or with **Jasny Router**:

```php
use Jasny\Router;
use Jasny\Router\Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$routes = new Routes\Glob(['/**' => ['controller' => '$1', 'id' => '$2']);
$router = new Router($routes);

$router->add($errorHandler);

$response = $dispatcher((new ServerRequest())->withGlobalEnvironment(), new Response());
```

## Logging

By default the error handler with only catch [Throwables](http://php.net/manual/en/class.throwable.php) and not set the
[php error handler](http://php.net/set_error_handler).

To log these errors, set the logger using the `setLogger()` method.

The `alsoLog()` method will set the error handler, so warnings and notices can be logged. It may also [register a
shutdown function](http://php.net/manual/en/function.register-shutdown-function.php) to handle uncatchable fatal
errors.

## PHP 5 support

To add support for PHP5, you should call `converErrorsToExceptions()`. This method will convert an error to an
[ErrorException](http://php.net/manual/en/class.errorexception.php).

