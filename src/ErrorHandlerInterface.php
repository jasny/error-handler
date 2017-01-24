<?php

namespace Jasny;

/**
 * Interface for interacting with an error handler.
 * The interface is not concerned with how the error handler is configured.
 */
interface ErrorHandlerInterface
{
    /**
     * Set the caught error.
     * 
     * @param \Throwable|\Exception|\Error
     */
    public function setError($error);
    
    /**
     * Get the caught error.
     * 
     * @return \Throwable|\Exception|\Error
     */
    public function getError();

    /**
     * Log an error or exception
     * 
     * @param \Exception|\Error $error
     */
    public function log($error);
}
