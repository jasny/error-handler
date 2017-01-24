<?php

namespace Jasny\ErrorHandler;

/**
 * Wrapper methods for internal PHP functions
 *
 * @codeCoverageIgnore
 */
trait FunctionWrapper
{
    /**
     * Wrapper method for `error_reporting`
     * 
     * @return int
     */
    protected function errorReporting()
    {
        return error_reporting();
    }

    /**
     * Wrapper method for `error_get_last`
     * 
     * @return array|null
     */
    protected function errorGetLast()
    {
        return error_get_last();
    }
    
    /**
     * Wrapper method for `set_error_handler`
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
     * 
     * @param callable $callback
     * @return callable|null
     */
    protected function setExceptionHandler($callback)
    {
        return set_exception_handler($callback);
    }
    
    /**
     * Wrapper method for `register_shutdown_function`
     * 
     * @param callable $callback
     */
    protected function registerShutdownFunction($callback)
    {
        register_shutdown_function($callback);
    }
}

