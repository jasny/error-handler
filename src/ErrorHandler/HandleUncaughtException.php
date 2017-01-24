<?php

namespace Jasny\ErrorHandler;

/**
 * Trait for handling uncaught exceptions using PHP's exception handler
 */
trait HandleUncaughtException
{
    /**
     * @var callable|false
     */
    protected $chainedExceptionHandler;


    /**
     * Log the following exception classes (and subclasses)
     * @var array
     */
    protected $logExceptionClasses = [];


    /**
     * Wrapper method for `set_error_handler`
     * 
     * @param callable $callback
     * @param int      $error_types
     * @return callable|null
     */
    abstract protected function setErrorHandler($callback, $error_types = E_ALL);

    /**
     * Wrapper method for `set_exception_handler`
     * 
     * @param callable $callback
     * @return callable|null
     */
    abstract protected function setExceptionHandler($callback);

    /**
     * Log an error or exception
     * 
     * @param \Exception|\Error $error
     * @return void
     */
    abstract public function log($error);

    /**
     * Get the types of errors that will be logged
     * 
     * @return int  Binary set of E_* constants
     */
    abstract public function getLoggedErrorTypes();


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
     * Get a list of Exception and other Throwable classes that will be logged
     * @return array
     */
    public function getLoggedExceptionClasses()
    {
        return $this->logExceptionClasses;
    }
    
    
    /**
     * Log these types of errors or exceptions
     * 
     * @param string $class  Exception class name
     */
    protected function logUncaughtException($class)
    {
        if (!in_array($class, $this->logExceptionClasses)) {
            $this->logExceptionClasses[] = $class;
        }

        $this->initExceptionHandler();
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
     * Uncaught exception handler
     * @ignore
     * 
     * @param \Exception|\Error $exception
     */
    public function handleException($exception)
    {
        $this->setExceptionHandler(null);
        $this->setErrorHandler(null);
        
        $isInstanceOf = array_map(function ($class) use ($exception) {
            return is_a($exception, $class);
        }, $this->logExceptionClasses);
        
        if ($exception instanceof \Error || $exception instanceof \ErrorException) {
            $type = $exception instanceof \Error ? $exception->getCode() : $exception->getSeverity();
            $shouldLog = $this->getLoggedErrorTypes() & $type;
        } else {
            $shouldLog = array_sum($isInstanceOf) > 0;
        }
        
        if ($shouldLog) {
            $this->log($exception);
        }
        
        $this->callOnFatalError($exception);
        
        if ($this->chainedExceptionHandler) {
            call_user_func($this->chainedExceptionHandler, $exception);
        }
        
        throw $exception; // This is now handled by the default exception and error handler
    }
}

