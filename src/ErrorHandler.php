<?php

namespace Jasny;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Handle error in following middlewares/app actions
 */
class ErrorHandler implements LoggerAwareInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Exception|\Error
     */
    protected $error;
    
    
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
     * @param \Exception $error
     */
    protected function logException(\Exception $error)
    {
        $level = $this->getLogLevel();
        
        $message = sprintf('Uncaught Exception %s: "%s" at %s line %s', get_class($error), $error->getMessage(),
            $error->getFile(), $error->getLine());
        
        $context = ['exception' => $error];

        $this->getLogger()->log($level, $message, $context);
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
     * Run middleware action
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @param callback               $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (!is_callable($next)) {
            throw new \InvalidArgumentException("'next' should be a callback");            
        }

        try {
            $this->error = null;
            $nextResponse = $next($request, $response);
        } catch(\Error $e) {
            $this->error = $e;
        } catch(\Exception $e) {
            $this->error = $e;
        }
        
        if ($this->error) {
            $this->log($this->error);
            $nextResponse = $this->handleError($response);
        }
        
        return $nextResponse;
    }

    /**
     * Handle caught error
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function handleError($response)
    {
        $errorResponse = $response->withStatus(500);
        $errorResponse->getBody()->write('Unexpected error');

        return $errorResponse;
    }
    
    
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
