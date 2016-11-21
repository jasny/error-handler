<?php

namespace Jasny\ErrorHandler;

use Jasny\ErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Use error handler as middleware
 */
class Middleware
{
    /**
     * @var ErrorHandler
     */
    protected $errorHandler;
    
    /**
     * Class constructor
     * 
     * @param ErrorHandler $errorHandler
     */
    public function __construct(ErrorHandler $errorHandler)
    {
        $this->errorHandler = $errorHandler;
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
            $nextResponse = $next($request, $response);
            $error = null;
        } catch (\Error $e) {
            $error = $e;
        } catch (\Exception $e) {
            $error = $e;
        }
        
        $this->errorHandler->setError($error);
        
        if ($error) {
            $this->errorHandler->log($error);
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
}
