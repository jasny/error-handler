<?php

namespace Jasny\ErrorHandler;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Trait for logging errors and exceptions
 */
trait Logging
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    
    /**
     * Set the logger for logging errors
     * 
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Set the logger for logging errors
     * 
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!isset($this->logger)) {
            $this->logger = new NullLogger();
        }
        
        return $this->logger;
    }
    
    
    /**
     * Log an error or exception
     * 
     * @param \Exception|\Error $error
     */
    public function log($error)
    {
        if ($error instanceof \Error || $error instanceof \ErrorException) {
            return $this->logError($error);
        }
        
        if ($error instanceof \Exception) {
            return $this->logException($error);
        }
        
        $message = "Unable to log a " . (is_object($error) ? get_class($error) . ' ' : '') . gettype($error);
        $this->getLogger()->log(LogLevel::WARNING, $message);
    }
    
    /**
     * Log an error
     * 
     * @param \Error|\ErrorException $error
     */
    protected function logError($error)
    {
        $code = $error instanceof \ErrorException ? $error->getSeverity() : E_ERROR;
        $level = $this->getLogLevel($code);
        
        $message = sprintf('%s: %s at %s line %s', $this->codeToString($code), $error->getMessage(),
            $error->getFile(), $error->getLine());

        $context = [
            'error' => $error,
            'code' => $code,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine()
        ];

        $this->getLogger()->log($level, $message, $context);
    }
    
    /**
     * Log an exception
     * 
     * @param \Exception $exception
     */
    protected function logException(\Exception $exception)
    {
        $level = $this->getLogLevel();
        
        $message = sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($exception), $exception->getMessage(),
            $exception->getFile(), $exception->getLine());
        
        $context = compact('exception');

        $this->getLogger()->log($level, $message, $context);
    }
}
