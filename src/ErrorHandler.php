<?php

namespace Jasny;

use Jasny\ErrorHandler;
use Jasny\ErrorHandler\Middleware;
use Psr\Log\LoggerAwareInterface;

/**
 * Handle error in following middlewares/app actions
 */
class ErrorHandler implements LoggerAwareInterface
{
    use ErrorHandler\Logging;
    use ErrorHandler\ErrorCodes;
    use ErrorHandler\HandleUncaughtError;
    use ErrorHandler\HandleShutdownError;
    use ErrorHandler\HandleUncaughtException;
    use ErrorHandler\FunctionWrapper;


    /**
     * @var \Exception|\Error
     */
    protected $error;
    
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
        if (isset($error) && !$error instanceof \Error && !$error instanceof \Exception) {
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
     * Use this error handler as middleware
     */
    public function asMiddleware()
    {
        return new Middleware($this);
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
     * Run the fatal error callback
     * 
     * @param \Exception|\Error $error
     */
    protected function callOnFatalError($error)
    {
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
}

