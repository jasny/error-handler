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
     * @var callable|false
     */
    protected $chainedErrorHandler;

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
     * A string which reserves memory that can be used to log the error in case of an out of memory fatal error
     * @var string
     */
    protected $reservedMemory;
    
    
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
            $nextResponse = $this->errorResponse($request, $response);
        }
        
        return $nextResponse;
    }

    /**
     * Handle caught error
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     * @return ResponseInterface
     */
    protected function errorResponse(ServerRequestInterface $request, ResponseInterface $response)
    {
        $errorResponse = $response->withStatus(500);
        $errorResponse->getBody()->write('Unexpected error');

        return $errorResponse;
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
     * Also log these types of errors in addition to caught errors and exceptions
     * 
     * @param int $errorTypes  E_* contants as binary set
     */
    public function alsoLog($errorTypes)
    {
        $this->logErrorTypes |= $errorTypes;
        
        $nonFatal = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_STRICT | E_DEPRECATED | E_USER_DEPRECATED;
        $unhandled = E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR;
            
        if ($this->logErrorTypes & $nonFatal) {
            $this->initErrorHandler();
        }
        
        if ($this->logErrorTypes & $unhandled) {
            $this->initShutdownFunction();
        }
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
        $unhandled = E_ERROR|E_PARSE|E_CORE_ERROR|E_COMPILE_ERROR;
        
        if (!$err || !($err['type'] & $unhandled)) {
            return;
        }
        
        $error = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        
        if ($err['type'] & $this->logErrorTypes) {
            $this->log($error);
        }
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
