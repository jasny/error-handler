<?php

namespace Jasny\ErrorHandler;

use Psr\Log\LogLevel;

/**
 * Trait for using E_* error codes
 */
trait ErrorCodes
{
    /**
     * Get the log level for an error code
     * 
     * @param int $code  E_* error code
     * @return string
     */
    protected function getLogLevel($code = null)
    {
        switch ($code) {
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return LogLevel::INFO;
            
            case E_NOTICE:
            case E_USER_NOTICE:
                return LogLevel::NOTICE;
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return LogLevel::WARNING;
            
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                return LogLevel::CRITICAL;
            
            default:
                return LogLevel::ERROR;
        }
    }
    
    /**
     * Turn an error code into a string
     * 
     * @param int $code
     * @return string
     */
    protected function codeToString($code)
    {
        switch ($code) {
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return 'Fatal error';
            case E_WARNING:
            case E_USER_WARNING:
                return 'Warning';
            case E_PARSE:
                return 'Parse error';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'Notice';
            case E_CORE_ERROR:
                return 'Core error';
            case E_CORE_WARNING:
                return 'Core warning';
            case E_COMPILE_ERROR:
                return 'Compile error';
            case E_COMPILE_WARNING:
                return 'Compile warning';
            case E_STRICT:
                return 'Strict standards';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'Deprecated';
        }
        
        return 'Unknown error';
    }
}

