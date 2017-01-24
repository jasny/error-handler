<?php

namespace Jasny\ErrorHandler;

/**
 * Trait for handling errors on shutdown
 */
trait HandleShutdownError
{
    /**
     * @var boolean
     */
    protected $registeredShutdown = false;
    
    /**
     * A string which reserves memory that can be used to log the error in case of an out of memory fatal error
     * @var string
     */
    protected $reservedMemory;
    

    /**
     * Run the fatal error callback
     * 
     * @param \Exception|\Error $error
     * @return void
     */
    abstract protected function callOnFatalError($error);

    /**
     * Wrapper method for `error_get_last`
     * 
     * @return array|null
     */
    abstract protected function errorGetLast();
    
    /**
     * Wrapper method for `register_shutdown_function`
     * 
     * @param callable $callback
     * @return void
     */
    abstract protected function registerShutdownFunction($callback);

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
     * Reserve memory for shutdown function in case of out of memory
     */
    protected function reserveMemory()
    {
        $this->reservedMemory = str_repeat(' ', 10 * 1024);
    }
    
    /**
     * Register the shutdown function
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
        
        if (empty($err) || !($err['type'] & $unhandled)) {
            return;
        }
        
        $error = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        
        if ($err['type'] & $this->getLoggedErrorTypes()) {
            $this->log($error);
        }
        
        $this->callOnFatalError($error);
    }
}
