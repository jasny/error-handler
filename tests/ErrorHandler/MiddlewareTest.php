<?php

namespace Jasny\ErrorHandler;

/**
 * Description of MiddlewareTest
 *
 * @author arnold
 */
class MiddlewareTest
{
    
    /**
     * Test invoke with invalid 'next' param
     * 
     * @expectedException \InvalidArgumentException
     */
    public function testInvokeInvalidNext()
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        
        $errorHandler = $this->errorHandler;
        
        $errorHandler($request, $response, 'not callable');
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
        
        $errorHandler = $this->errorHandler;

        $result = $errorHandler($request, $response, $next);        

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
        
        $result = $errorHandler($request, $response, $next);

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
        
        $result = $errorHandler($request, $response, $next);

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
        
        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willThrowException($exception);
        
        $errorHandler($request, $response, $next);
    }
}