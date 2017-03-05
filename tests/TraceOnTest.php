<?php
namespace TraceOn\Tests;

use \PHPUnit\Framework\TestCase;
use \TraceOn\TraceOn;

function dummyGlobalFunction(string $arg) {
    return 'dummy' . 'GlobalFunction:' . $arg;
}

/**
 * Simple examples for the usage of TraceOn, doubling as a PHPUnit test suite.
 * Tests for more complicated bug fixes, etc. can be added to another test file.
 *
 *
 * Copyright 2017 Ifwe Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
class TraceOnTest extends TestCase {
    public function tearDown() {
        parent::tearDown();
        TraceOn::cleanup_all();
    }

    public function testTraceStaticFunction() {
        $expectedValue = 'dummypublicStaticFunction:arg';
        $this->assertSame($expectedValue, \TraceOn\Tests\dummy::publicStaticFunction('arg'));
        $tracer = new TraceOn('\TraceOn\Tests\dummy', 'publicStaticFunction', [
            TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
            TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
            TraceOn::PARAM_RETURN_LOGGER => false,
            TraceOn::PARAM_EXCEPTION_LOGGER => false,
        ]);
        ob_start();
        $dummy = new \TraceOn\Tests\dummy();
        try {
            $actualValue = $dummy->getPublicStaticFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }

        $expectedMsg = 'Calling \TraceOn\Tests\dummy::publicStaticFunction: args=["arg"]' . "\n";
        $this->assertSame($expectedValue, $actualValue, 'Should preserve original return value');
        $this->assertSame($expectedMsg, $stdout, 'should log to stdout');
        $tracer->cleanUp();
        ob_start();
        try {
            $actualValue = $dummy->getPublicStaticFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame($expectedValue, $actualValue, 'After the mock is cleaned up, method implementation should be unchanged');
        $this->assertSame('', $stdout, 'should stop logging to stdout after TraceOn is cleaned up');
    }

    public function testTraceStaticFunctionAndUntraceAll() {
        $expectedValue = 'dummypublicStaticFunction:arg';
        $dummy = new \TraceOn\Tests\dummy();
        $this->assertSame($expectedValue, $dummy->getPublicStaticFunction('arg'));
        $tracer = new TraceOn('\TraceOn\Tests\dummy', 'publicStaticFunction', [
            TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
            TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
            TraceOn::PARAM_RETURN_LOGGER => false,
            TraceOn::PARAM_EXCEPTION_LOGGER => false,
        ]);
        ob_start();
        try {
            $actualValue = $dummy->getPublicStaticFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }

        $expectedMsg = 'Calling \TraceOn\Tests\dummy::publicStaticFunction: args=["arg"]' . "\n";
        $this->assertSame($expectedValue, $actualValue, 'Should preserve original return value');
        $this->assertSame($expectedMsg, $stdout, 'should log to stdout');
        TraceOn::cleanup_all();

        ob_start();
        try {
            $actualValue = $dummy->getPublicStaticFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame($expectedValue, $actualValue, 'After the mock is cleaned up, method implementation should be unchanged');
        $this->assertSame('', $stdout, 'should stop logging to stdout after TraceOn is cleaned up (via cleanup_all())');
    }

    public function testTraceInstanceFunction() {
        $expectedValue = 'retval:["arg1","arg2"]';
        $dummy = new \TraceOn\Tests\dummy();
        $this->assertSame($expectedValue, $dummy->instanceFunction('arg1', 'arg2'));
        $tracer = new TraceOn('\TraceOn\Tests\dummy', 'instanceFunction', [
            TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
            TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
            TraceOn::PARAM_RETURN_LOGGER => TraceOn::DEFAULT_RETURN_LOGGER,
            TraceOn::PARAM_EXCEPTION_LOGGER => false,
        ]);
        ob_start();
        try {
            $actualValue = $dummy->instanceFunction('arg1', 'arg2');
        } finally {
            $stdout = ob_get_clean();
        }

        $expectedMsg = <<<EOT
Calling \TraceOn\Tests\dummy->instanceFunction: args=["arg1","arg2"]

return value of \TraceOn\Tests\dummy->instanceFunction is :
'retval:["arg1","arg2"]'


EOT;
        $this->assertSame($expectedValue, $actualValue, 'Should preserve original return value');
        $this->assertSame($expectedMsg, $stdout, 'should log to stdout');
        $tracer->cleanUp();
        ob_start();
        try {
            $actualValue = $dummy->instanceFunction('arg1', 'arg2');
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame($expectedValue, $actualValue, 'After the mock is cleaned up, method implementation should be unchanged');
        $this->assertSame('', $stdout, 'should stop logging to stdout after TraceOn is cleaned up');
    }

    public function testTraceGlobalFunction() {
        $expectedValue = 'dummyGlobalFunction:arg';
        $tracer = new TraceOn(null, '\TraceOn\Tests\dummyGlobalFunction', [
            TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
            TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
            TraceOn::PARAM_RETURN_LOGGER => false,
            TraceOn::PARAM_EXCEPTION_LOGGER => false,
        ]);
        ob_start();
        try {
            $actualValue = dummyGlobalFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }

        $expectedMsg = 'Calling \TraceOn\Tests\dummyGlobalFunction: args=["arg"]' . "\n";
        $this->assertSame($expectedValue, $actualValue, 'Should preserve original return value');
        $this->assertSame($expectedMsg, $stdout, 'should log to stdout');
        $tracer->cleanUp();
        ob_start();
        try {
            $actualValue = dummyGlobalFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame($expectedValue, $actualValue, 'After the mock is cleaned up, method implementation should be unchanged');
        $this->assertSame('', $stdout, 'should stop logging to stdout after TraceOn is cleaned up');
    }

    public function testTraceGlobalFunctionAndUntraceAll() {
        $expectedValue = 'dummyGlobalFunction:arg';
        $this->assertTrue(function_exists('\TraceOn\Tests\dummyGlobalFunction'));
        $tracer = new TraceOn(null, '\TraceOn\Tests\dummyGlobalFunction', [
            TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
            TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
            TraceOn::PARAM_RETURN_LOGGER => false,
            TraceOn::PARAM_EXCEPTION_LOGGER => false,
        ]);
        ob_start();
        try {
            $actualValue = dummyGlobalFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }

        $expectedMsg = 'Calling \TraceOn\Tests\dummyGlobalFunction: args=["arg"]' . "\n";
        $this->assertSame($expectedValue, $actualValue, 'Should preserve original return value');
        $this->assertSame($expectedMsg, $stdout, 'should log to stdout');
        TraceOn::cleanup_all();
        ob_start();
        try {
            $actualValue = dummyGlobalFunction('arg');
        } finally {
            $stdout = ob_get_clean();
        }
        $this->assertSame($expectedValue, $actualValue, 'After the mock is cleaned up, method implementation should be unchanged');
        $this->assertSame('', $stdout, 'should stop logging to stdout after TraceOn is cleaned up');
    }
}
