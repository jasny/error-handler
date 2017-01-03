Jasny Error Handler
===

[![Build Status](https://secure.travis-ci.org/jasny/error-handler.png?branch=master)](http://travis-ci.org/jasny/error-handler)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jasny/error-handler/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jasny/error-handler/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jasny/error-handler/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jasny/error-handler/?branch=master)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/e0404b74-7a3e-41c3-a382-6ba12cd63560/mini.png)](https://insight.sensiolabs.com/projects/e0404b74-7a3e-41c3-a382-6ba12cd63560)
[![Packagist Stable Version](https://img.shields.io/packagist/v/jasny/error-handler.svg)](https://packagist.org/packages/jasny/error-handler)
[![Packagist License](https://img.shields.io/packagist/l/jasny/error-handler.svg)](https://packagist.org/packages/jasny/error-handler)

Error handler with PSR-7 support.

Installation
---

The Jasny Error Handler package is available on [packagist](https://packagist.org/packages/jasny/error-handler).
Install it using composer:

    composer require jasny/error-handler


Usage
---

```php
$errorHandler = new Jasny\ErrorHandler();
```

Just creating an error handler will do nothing. You can use it for logging, handling fatal errors and as PSR-7 compatible
middleware.

## Logging

By default the error handler with only catch [Throwables](http://php.net/manual/en/class.throwable.php) and not set the
[php error handler](http://php.net/set_error_handler).

To log errors, set the logger using `setLogger()`. You can log with any [PSR-3 compatible
logger](http://www.php-fig.org/psr/psr-3/) like [Monolog](https://github.com/Seldaek/monolog).

The `logUncaught()` method will set the error handler, so warnings and notices can be logged. It may also [register a
shutdown function](http://php.net/manual/en/function.register-shutdown-function.php) to handle uncatchable fatal
errors.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$errorHandler = new Jasny\ErrorHandler();

$log = new Logger('test');
$log->pushHandler(new StreamHandler('path/to/your.log'));

// Log fatal errors, warnings and uncaught exceptions
$errorHandler->setLogger($log);

$errorHandler->logUncaught(E_PARSE | E_ERROR | E_WARNING | E_USER_WARNING);
$errorHandler->logUncaught(Exception::class);
$errorHandler->logUncaught(Error::class); // PHP7 only
```

## PSR-7 compatible middleware

The error handler can be used as PSR-7 compatible (double-pass) middleware.

The error will catch [Exceptions](http://php.net/manual/en/class.exception.php) and
[Errors](http://php.net/manual/en/class.error.php).

You can use this middleware with:

* [Jasny Router](https://github.com/jasny/router)
* [Relay](https://github.com/relayphp/Relay.Relay)
* [Expressive](http://framework.zend.com/expressive)
* [Slim 3](http://www.slimframework.com)

For example use it with **Relay**:

```php
use Relay\RelayBuilder;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$errorHandler = new Jasny\ErrorHandler();

$relay = new RelayBuilder();
$dispatcher = $relay->newInstance([$errorHandler->asMiddleware()]);

$response = $dispatcher((new ServerRequest())->withGlobalEnvironment(), new Response());
```

Or with **Jasny Router**:

```php
use Jasny\Router;
use Jasny\Router\Routes\Glob as Routes;
use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Response;

$router = new Router(new Routes(['/**' => ['controller' => '$1', 'id' => '$2']));

$errorHandler = new Jasny\ErrorHandler();
$router->add($errorHandler->asMiddleware());

$response = $dispatcher((new ServerRequest())->withGlobalEnvironment(), new Response());
```

### PHP 5 support

With PHP 5 errors aren't thrown, so the middleware won't handle it. To add middleware support for errors in PHP5, you
should call `converErrorsToExceptions()`. This method will convert an error to an
[ErrorException](http://php.net/manual/en/class.errorexception.php).

## Handling fatal errors

Errors that are not thrown, like syntax errors, are not caught and will cause a fatal error. With the `logUncaught()` 
method, you can specify that the error handler should also these kind of errors.

With the `onFatalError()` method you take additional action, like output a pretty error message.

```php
ob_start();

$errorHandler = new Jasny\ErrorHandler();

$errorHandler->logUncaught(E_ERROR | E_RECOVERABLE_ERROR | E_USER_ERROR);

$errorHandler->onFatalError(function() {
    http_response_code(500);
    header('Content-Type: text/html');
    echo "<h1>An unexpected error occured</h1><p>The error has been logged.</p>";
}, true);
```

Use `true` as second argument of `onFatalError` to the output buffer before calling your function.

## Combine with other error handlers

Using the error logger might lose backtrace information that other error handlers can pick up. Jasny Error Handler
will always call the previous error handler, including the PHP internal error handler for non-thrown errors.

When using [Rollbar](https://rollbar.com/) you should not use the Rollbar handler for Monolog. By using Rollbar's own error 
handler, you'll get better error reports:

```php
use Jasny\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Rollbar error handler will log uncaught errors
Rollbar::init(array('access_token' => 'POST_SERVER_ITEM_ACCESS_TOKEN'));

$log = new Logger('test');
$log->pushHandler(new RollbarHandler(Rollbar::$instance));

$errorHandler = new ErrorHandler();

// Jasny error handler will only log caught errors
$errorHandler->setLogger($log);
```
