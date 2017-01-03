<?php

namespace Jasny\ErrorHandler;

/**
 * Trait for handling uncaught errors using PHP's error handler
 */
trait HandleUncaughtError
{
    /**
     * @var callable|false
     */
    protected $chainedErrorHandler;

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
     * Run the fatal error callback
     * 
     * @param \Exception|\Error $error
     */
    abstract protected function callOnFatalError($error);

    /**
     * Wrapper method for `error_reporting`
     * 
     * @return int
     */
    abstract protected function errorReporting();

    /**
     * Wrapper method for `set_error_handler`
     * 
     * @param callable $callback
     * @param int      $error_types
     * @return callable|null
     */
    abstract protected function setErrorHandler($callback, $error_types = E_ALL);

    /**
     * Register the shutdown function
     */
    abstract protected function initShutdownFunction();

    /**
     * Log an error or exception
     * 
     * @param \Exception|\Error $error
     */
    abstract public function log($error);


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
     * Get the types of errors that will be logged
     * 
     * @return int  Binary set of E_* constants
     */
    public function getLoggedErrorTypes()
    {
        return $this->logErrorTypes;
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
     * Use the global error handler to convert E_USER_ERROR and E_RECOVERABLE_ERROR to an ErrorException
     */
    public function converErrorsToExceptions()
    {
        $this->convertFatalErrors = true;
        $this->initErrorHandler();
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

            if ($this->getLoggedErrorTypes() & $type) {
                $this->log($error);
            }
        }
        
        return $this->chainedErrorHandler
            ? call_user_func($this->chainedErrorHandler, $type, $message, $file, $line, $context)
            : false;
    }
}

