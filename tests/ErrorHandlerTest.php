<?php

namespace Jasny;

use Jasny\ErrorHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @covers Jasny\ErrorHandler
 */
class ErrorHandlerTest extends \PHPUnit_Framework_TestCase
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
        
        $middleware = new ErrorHandler();

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
        
        $errorHandler = new ErrorHandler();

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
        
        $errorHandler = new ErrorHandler();
        
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
        
        $errorHandler = new ErrorHandler();
        
        $result = $errorHandler($request, $response, $next);

        $this->assertSame($errorResponse, $result);
        
        $error = $errorHandler->getError();
        $this->assertEquals("Call to undefined function this_function_does_not_exist()", $error->getMessage());
    }
    
    
    public function testSetLogger()
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);
        
        $this->assertSame($logger, $errorHandler->getLogger());
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
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);
        
        $next = $this->getMockBuilder(\stdClass::class)->setMethods(['__invoke'])->getMock();
        $next->expects($this->once())->method('__invoke')
            ->with($request, $response)
            ->willThrowException($exception);
        
        $errorHandler($request, $response, $next);
    }
    
    public function errorProvider()
    {
        return [
            [E_ERROR, LogLevel::ERROR, 'Fatal error'],
            [E_USER_ERROR, LogLevel::ERROR, 'Fatal error'],
            [E_RECOVERABLE_ERROR, LogLevel::ERROR, 'Fatal error'],
            [E_WARNING, LogLevel::WARNING, 'Warning'],
            [E_USER_WARNING, LogLevel::WARNING, 'Warning'],
            [E_PARSE, LogLevel::CRITICAL, 'Parse error'],
            [E_NOTICE, LogLevel::NOTICE, 'Notice'],
            [E_USER_NOTICE, LogLevel::NOTICE, 'Notice'],
            [E_CORE_ERROR, LogLevel::CRITICAL, 'Core error'],
            [E_CORE_WARNING, LogLevel::WARNING, 'Core warning'],
            [E_COMPILE_ERROR, LogLevel::CRITICAL, 'Compile error'],
            [E_COMPILE_WARNING, LogLevel::WARNING, 'Compile warning'],
            [E_STRICT, LogLevel::INFO, 'Strict standards'],
            [E_DEPRECATED, LogLevel::INFO, 'Deprecated'],
            [E_USER_DEPRECATED, LogLevel::INFO, 'Deprecated'],
            [99999999, LogLevel::ERROR, 'Unknown error']
        ];
    }
    
    /**
     * @dataProvider errorProvider
     * 
     * @param int    $code
     * @param string $level
     * @param string $type
     */
    public function testLogError($code, $level, $type)
    {
        $error = new \ErrorException("no good", 0, $code, "foo.php", 42);
        $context = ['error' => $error, 'code' => $code, 'message' => "no good", 'file' => 'foo.php', 'line' => 42];
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')
            ->with($level, "$type: no good at foo.php line 42", $context);
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);

        $errorHandler->log($error);
    }
    
    public function testLogException()
    {
        $exception = $this->createMock(\Exception::class);
        
        $message = $this->stringStartsWith('Uncaught Exception ' . get_class($exception));
        $context = ['exception' => $exception];
        
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')
            ->with(LogLevel::ERROR, $message, $context);
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);

        $errorHandler->log($exception);
    }
    
    public function testLogString()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(LogLevel::WARNING, "Unable to log a string");
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);

        $errorHandler->log('foo');
    }
    
    public function testLogObject()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(LogLevel::WARNING, "Unable to log a stdClass object");
        
        $errorHandler = new ErrorHandler();
        $errorHandler->setLogger($logger);

        $errorHandler->log(new \stdClass());
    }
}
