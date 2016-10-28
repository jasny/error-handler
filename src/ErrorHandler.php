<?php

namespace Jasny;

use Jasny\ErrorHandler\ErrorCodes;
use Jasny\ErrorHandler\Logging;
use Jasny\ErrorHandler\Middleware;
use Psr\Log\LoggerAwareInterface;

/**
 * Handle error in following middlewares/app actions
 */
class ErrorHandler implements LoggerAwareInterface
{
    use Logging;
    use ErrorCodes;
    
    /**
     * @var \Exception|\Error
     */
    protected $error;
    
    /**
     * @var callable|false
     */
    protected $chainedErrorHandler;

    /**
     * @var callable|false
     */
    protected $chainedExceptionHandler;

    /**
     * @var boolean
     */
    protected $registeredShutdown = false;
    
    /**
     * Convert fatal errors to exceptions
     * @var boolean
     */
    protected $convertFatalErrors = false;
    
    /**
     * Log the following error types (in addition to caugth errors)
     * @var int
     */
    protected $logErrorTypes = 0;
    
    /**
     * Log the following error types (in addition to caugth errors)
     * @var int
     */
    protected $logExceptions = [];
    
    /**
     * A string which reserves memory that can be used to log the error in case of an out of memory fatal error
     * @var string
     */
    protected $reservedMemory;
    
    /**
     * @var callback
     */
    protected $onFatalError;

    
    /**
     * Set the caught error
     * 
     * @param \Throwable|\Exception|\Error
     */
    public function setError($error)
    {
        if (!$error instanceof \Error && !$error instanceof \Exception) {
            $type = (is_object($error) ? get_class($error) . ' ' : '') . gettype($error);
            trigger_error("Excpeted an Error or Exception, got a $type", E_USER_WARNING);
            return;
        }
        
        $this->error = $error;
    }
    
    /**
     * Get the caught error
     * 
     * @return \Throwable|\Exception|\Error
     */
    public function getError()
    {
        return $this->error;
    }
    
    
    /**
     * Get the error handler that has been replaced.
     * 
     * @return callable|false|null
     */
    public function getChainedErrorHandler()
    {
        return $this->chainedErrorHandler;
    }
    
    /**
     * Get the error handler that has been replaced.
     * 
     * @return callable|false|null
     */
    public function getChainedExceptionHandler()
    {
        return $this->chainedExceptionHandler;
    }
    
    
    /**
     * Get the types of errors that will be logged
     * 
     * @return int  Binary set of E_* constants
     */
    public function getLoggedErrorTypes()
    {
        return $this->logErrorTypes;
    }
    
    /**
     * Use the global error handler to convert E_USER_ERROR and E_RECOVERABLE_ERROR to an ErrorException
     */
    public function converErrorsToExceptions()
    {
        $this->convertFatalErrors = true;
        $this->initErrorHandler();
    }
    
    /**
     * Log these types of errors or exceptions
     * 
     * @param int|string $type  E_* contants as binary set OR Exception class name
     */
    public function logUncaught($type)
    {
        if (is_int($type)) {
            $this->logUncaughtErrors($type);
        } elseif (is_string($type)) {
            $this->logUncaughtException($type);
        } else {
            throw new \InvalidArgumentException("Type should be an error code (int) or Exception class (string)");
        }
    }
    
    /**
     * Log these types of errors or exceptions
     * 
     * @param string $class  Exception class name
     */
    protected function logUncaughtException($class)
    {
        if (!in_array($class, $this->logExceptions)) {
            $this->logExceptions[] = $class;
        }

        $this->initExceptionHandler();
    }
    
    /**
     * Log these types of errors or exceptions
     * 
     * @param int $type  E_* contants as binary set
     */
    protected function logUncaughtErrors($type)
    {
        $this->logErrorTypes |= $type;

        $unhandled = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

        if ($type & ~$unhandled) {
            $this->initErrorHandler();
        }

        if ($type & $unhandled) {
            $this->initShutdownFunction();
        }
    }

    
    /**
     * Set a callback for when the script dies because of a fatal, non-catchable error.
     * The callback should have an `ErrorException` as only argument.
     * 
     * @param callable $callback
     * @param boolean  $clearOutput  Clear the output buffer before calling the callback
     */
    public function onFatalError($callback, $clearOutput = false)
    {
        if (!$clearOutput) {
            $this->onFatalError = $callback;
        } else {
            $this->onFatalError = function ($error) use ($callback) {
                $this->clearOutputBuffer();
                $callback($error);
            };
        }
    }
    
    /**
     * Use this error handler as middleware
     */
    public function asMiddleware()
    {
        return new Middleware($this);
    }
    
    
    /**
     * Use the global error handler
     */
    protected function initErrorHandler()
    {
        if (!isset($this->chainedErrorHandler)) {
            $this->chainedErrorHandler = $this->setErrorHandler([$this, 'handleError']) ?: false;
        }
    }
    
    /**
     * Uncaught error handler
     * @ignore
     * 
     * @param int    $type
     * @param string $message
     * @param string $file
     * @param int    $line
     * @param array  $context
     */
    public function handleError($type, $message, $file, $line, $context)
    {
        if ($this->errorReporting() & $type) {
            $error = new \ErrorException($message, 0, $type, $file, $line);

            if ($this->convertFatalErrors && ($type & (E_RECOVERABLE_ERROR | E_USER_ERROR))) {
                throw $error;
            }

            if ($this->logErrorTypes & $type) {
                $this->log($error);
            }
        }
        
        return $this->chainedErrorHandler
            ? call_user_func($this->chainedErrorHandler, $type, $message, $file, $line, $context)
            : false;
    }

    
    /**
     * Use the global error handler
     */
    protected function initExceptionHandler()
    {
        if (!isset($this->chainedExceptionHandler)) {
            $this->chainedExceptionHandler = $this->setExceptionHandler([$this, 'handleException']) ?: false;
        }
    }
    
    /**
     * Uncaught error handler
     * @ignore
     * 
     * @param \Exception|\Error $exception
     */
    public function handleException($exception)
    {
        $isInstanceOf = array_map(function($class) use ($exception) {
            return is_a($exception, $class);
        }, $this->logExceptions);
        
        $shouldLog = array_sum($isInstanceOf) > 0;
        
        if ($shouldLog) {
            $this->log($exception);
        }
        
        if ($this->chainedExceptionHandler) {
            call_user_func($this->chainedErrorHandler, $type, $message, $file, $line, $context);
        }
        
        set_error_handler(null);
        
        $warning = sprintf("Uncaught exception '%s' with message '%s' in %s:%d", get_class($exception),
            $exception->getMessage(), $exception->getFile(), $exception->getLine());
        trigger_error($warning, E_USER_WARNING);
        
        if ($this->onFatalError) {
            call_user_func($this->onFatalError, $exception);
        }
    }

    
    /**
     * Reserve memory for shutdown function in case of out of memory
     */
    protected function reserveMemory()
    {
        $this->reservedMemory = str_repeat(' ', 10 * 1024);
    }
    
    /**
     * Register a shutdown function
     */
    protected function initShutdownFunction()
    {
        if (!$this->registeredShutdown) {
            $this->registerShutdownFunction([$this, 'shutdownFunction']) ?: false;
            $this->registeredShutdown = true;
            
            $this->reserveMemory();
        }
    }
    
    /**
     * Called when the script has ends
     * @ignore
     */
    public function shutdownFunction()
    {
        $this->reservedMemory = null;
        
        $err = $this->errorGetLast();
        $unhandled = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
        
        if (!$err || !($err['type'] & $unhandled)) {
            return;
        }
        
        $error = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        
        if ($err['type'] & $this->logErrorTypes) {
            $this->log($error);
        }
        
        if ($this->onFatalError) {
            call_user_func($this->onFatalError, $error);
        }
    }
    
   
    /**
     * Clear and destroy all the output buffers
     * @codeCoverageIgnore
     */
    protected function clearOutputBuffer()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    /**
     * Wrapper method for `error_reporting`
     * @codeCoverageIgnore
     * 
     * @return int
     */
    protected function errorReporting()
    {
        return error_reporting();
    }

    /**
     * Wrapper method for `error_get_last`
     * @codeCoverageIgnore
     * 
     * @return array
     */
    protected function errorGetLast()
    {
        return error_get_last();
    }
    
    /**
     * Wrapper method for `set_error_handler`
     * @codeCoverageIgnore
     * 
     * @param callable $callback
     * @param int      $error_types
     * @return callable|null
     */
    protected function setErrorHandler($callback, $error_types = E_ALL)
    {
        return set_error_handler($callback, $error_types);
    }
    
    /**
     * Wrapper method for `set_exception_handler`
     * @codeCoverageIgnore
     * 
     * @param callable $callback
     * @return callable|null
     */
    protected function setExceptionHandler($callback)
    {
        return set_error_handler($callback);
    }
    
    /**
     * Wrapper method for `register_shutdown_function`
     * @codeCoverageIgnore
     * 
     * @param callable $callback
     */
    protected function registerShutdownFunction($callback)
    {
        register_shutdown_function($callback);
    }
}
