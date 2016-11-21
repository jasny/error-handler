<?php

namespace Jasny\ErrorHandler;

use Jasny\ErrorHandler;
use Jasny\ErrorHandler\Middleware;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * @covers Jasny\ErrorHandler\Middleware
 */
class MiddlewareTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ErrorHandler|MockObject
     */
    protected $errorHandler;
    
    /**
     * @var Middleware
     */
    protected $middleware;
    
    public function setUp()
    {
        $this->errorHandler = $this->getMockBuilder(ErrorHandler::class)
            ->setMethods(['errorReporting', 'errorGetLast', 'setErrorHandler', 'setExceptionHandler',
                'registerShutdownFunction', 'clearOutputBuffer'])
            ->getMock();
        
        $this->middleware = new Middleware($this->errorHandler);
    }
    
    
    /**
     * Test invoke with invalid 'next' param
     * 
     * @expectedException \InvalidArgumentException
     */
    public function testInvokeInvalidNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $middleware = $this->middleware;
        
        $middleware($request, $response, 'not callable');
    }

    /**
     * Test case when there is no error
     */
    public function testInvokeNoError()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $finalResponse = $this->createMock(ResponseInterface::class);

        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willReturn($finalResponse);
        
        $middleware = $this->middleware;

        $result = $middleware($request, $response, $next);        

        $this->assertSame($finalResponse, $result);
    }
    
    /**
     * Test that Exception in 'next' callback is caught
     */
    public function testInvokeCatchException()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $errorResponse = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        $exception = $this->createMock(\Exception::class);

        $stream->expects($this->once())->method('write')->with('Unexpected error');
        $response->expects($this->once())->method('withStatus')->with(500)->willReturn($errorResponse);

        $errorResponse->expects($this->once())->method('getBody')->willReturn($stream);
        
        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willThrowException($exception);
        
        $errorHandler = $this->errorHandler;
        
        $middleware = $this->middleware;
        
        $result = $middleware($request, $response, $next);

        $this->assertSame($errorResponse, $result);
        $this->assertSame($exception, $errorHandler->getError());
    }
    
    /**
     * Test that an error in 'next' callback is caught
     */
    public function testInvokeCatchError()
    {
        if (!class_exists('Error')) {
            $this->markTestSkipped(PHP_VERSION . " doesn't throw errors yet");
        }
        
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $errorResponse = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        $stream->expects($this->once())->method('write')->with('Unexpected error');
        $response->expects($this->once())->method('withStatus')->with(500)->willReturn($errorResponse);

        $errorResponse->expects($this->once())->method('getBody')->willReturn($stream);
        
        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willReturnCallback(function() {
                \this_function_does_not_exist();
            });
        
        $errorHandler = $this->errorHandler;
        $middleware = $this->middleware;
        
        $result = $middleware($request, $response, $next);

        $this->assertSame($errorResponse, $result);
        
        $error = $errorHandler->getError();
        $this->assertEquals("Call to undefined function this_function_does_not_exist()", $error->getMessage());
    }
    
    public function testInvokeLog()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        
        $response->method('withStatus')->willReturnSelf();
        $response->method('getBody')->willReturn($stream);
        
        $exception = $this->createMock(\Exception::class);
        
        $message = $this->stringStartsWith('Uncaught Exception ' . get_class($exception));
        $context = ['exception' => $exception];
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')
            ->with(LogLevel::ERROR, $message, $context);
        
        $errorHandler = $this->errorHandler;
        $errorHandler->setLogger($logger);
        
        $middleware = $this->middleware;
        
        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willThrowException($exception);
        
        $middleware($request, $response, $next);
    }
}
