<?php

namespace TraceOn;

/**
 * This class is recommended only for debugging issues involving deeply nested calls, and not for use during normal operations.
 *
 * It uses runkit to print out function arguments, stack traces, and return values, as well as exceptions.
 * Calls to this can be added to an entry point, e.g. to figure out if a deeply nested function is being called,
 * what a function is being called with, what it is returning, etc.
 *
 * This library is compatible with php 7.0 and 7.1.
 *
 * This library works with static and instance methods. It has a similar interface to \SimpleStaticMock\SimpleStaticMock.
 * TODO: allow operating on functions by passing in null as a class name.
 *
 * E.g. to check if my_class::_my_protected_method() is being called:
 *
 * $trace = new \TraceOn\TraceOn('my_class', '_my_protected_method');
 *
 * ....call api which you expect should be calling my_class::_my_protected_method()....
 *
 * $trace->untrace();
 *
 * It is also possible to do similar things to this (with many more features)
 * with xdebug, which has many clients (e.g. Eclipse)
 *
 * Examples
 * --------
 *
 * 1. Log cache fetches to stdout, assuming the (user-defined) cache class name is MyCache  (log only the params MyCache::get was called with, don't call backtraces):
 *
 *    use \TraceOn\TraceOn;
 *
 *    $cacheTrace = new TraceOn('MyCache', 'get', [TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false, TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER, TraceOn::PARAM_RETURN_LOGGER => false, TraceOn::PARAM_EXCEPTION_LOGGER => false]);
 *    // ...
 *    $cacheTrace->cleanup()
 *
 * Known bugs:
 *
 * - Does not work properly when tracing functions with reference params. See the patches to ifwe/Phockito fork for how this would be done properly.
 *
 * ----------------------------------------------------------------------
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
class TraceOn {
    // Configuration options.

    /**
     * Boolean flag. Print files and lines of backtrace when method is called. 'false' by default
     */
    const PARAM_SHOULD_PRINT_BACKTRACE = 'backtrace';

    /**
     * Name of a callback (method or function) to print the arguments a method was called with, as the method is entered.
     * Set the corresponding values to null/false to disable.
     * This is invoked as `argsLoggerName(string $methodName, array $args)`
     */
    const PARAM_ARGS_LOGGER            = 'args_logger';

    /**
     * Name of a callback (method or function) to print the value a method was called with, as the method is entered.
     * Set the corresponding values to null/false to disable.
     * This is invoked as `returnLoggerName(string $methodName, array $args)`
     */
    const PARAM_RETURN_LOGGER          = 'return_logger';

    /**
     * Print exception values before rethrowing them. Set the corresponding values to null/false to disable.
     * This is invoked as `returnLoggerName(string $methodName, throwable $e)`
     */
    const PARAM_EXCEPTION_LOGGER       = 'exception_logger';

    /** Default for PARAM_ARGS_LOGGER */
    const DEFAULT_ARGS_LOGGER      = '\TraceOn\TraceOn::log_arguments';
    /** Alternative, uses json_encode for shorter output than var_export. */
    const JSON_ARGS_LOGGER         = '\TraceOn\TraceOn::log_arguments_json';
    /** Default for PARAM_RETURN_LOGGER */
    const DEFAULT_RETURN_LOGGER    = '\TraceOn\TraceOn::log_return';
    /** Default for PARAM_EXCEPTION_LOGGER */
    const DEFAULT_EXCEPTION_LOGGER = '\TraceOn\TraceOn::log_exception';
    /** This can be passed in to disable a specific logging type. False can be passed in, to change loggers to noop. */
    const NOOP                     = '\TraceOn\TraceOn::noop';

    /** @var bool */
    private $_traced = true;
    /** @var ?string - If null, this is a mock of a function instead of a method. */
    private $_className;
    /** @var string */
    private $_methodName;
    /** @var string */
    private $_originalMethodName;

    /** @var TraceOn[] - Maps full name to path */
    private static $_registry = [];

    /**
     * Start logging backtraces, parameters, and return values of the instance method or static method $className::$methodName, whenever it is called.
     * @param string $className class name with method to mock
     * @param string $methodName method name to mock
     * @param array $options
     */
    public function __construct($className, $methodName, array $options = []) {
        if ($className === null) {
            $methodName = ltrim($methodName, "\\");
        }
        $key = self::get_key($className, $methodName);
        if (isset(self::$_registry[$key])) {
            throw new \RuntimeException("Logger for $key already exists");
        }
        if (!extension_loaded('runkit')) {
            throw new \RuntimeException("Runkit is not installed");
        }
        if ($className !== null) {
            if (!class_exists($className)) {
                throw new \RuntimeException("Failed to load class '$className'");
            }
        } else if (!function_exists($methodName)) {
            throw new \RuntimeException("Failed to load function '$methodName'");
        }
        $runkitFlags = self::compute_runkit_flags($className, $methodName);
        $methodSeparator = ($runkitFlags & RUNKIT_ACC_STATIC) ? '::' : '->';
        if ($className !== null) {
            $fullMethodName = $className . $methodSeparator . $methodName;
        } else {
            $fullMethodName = '\\' . $methodName;
        }

        $originalMethodName = $methodName . '_original';
        if ($className !== null) {
            if (!runkit_method_copy($className, $originalMethodName, $className, $methodName)) {
                throw new \RuntimeException('Failed to copy method');
            }
        } else {
            if (!runkit_function_copy(ltrim($methodName, "\\"), $originalMethodName)) {
                throw new \RuntimeException('Failed to copy function');
            }
        }
        $args = '';  // no args

        /**
         * @param string $key
         * @param string $default
         * @return string callback to invoke for this stage of the method tracing (Before call, after call returns, after call has an exception)
         * Treat passing no value as the default, treat passing falsey value as a no-op, and otherwise, expect a string.
         */
        $getOption = function(string $key, $default) use ($options) {
            if (!array_key_exists($key, $options)) {
                return $default;
            } else if ($options[$key]) {
                return $options[$key];
            }
            return self::NOOP;
        };
        $log_args = $getOption(self::PARAM_ARGS_LOGGER, self::DEFAULT_ARGS_LOGGER);
        $log_return = $getOption(self::PARAM_RETURN_LOGGER, self::DEFAULT_RETURN_LOGGER);
        $log_exception = $getOption(self::PARAM_EXCEPTION_LOGGER, self::DEFAULT_EXCEPTION_LOGGER);
        if ($log_args && (!is_string($log_args) || !is_callable($log_args))) {
            throw new \InvalidArgumentException("Expected a callable string (method/function) for TraceOn::PARAM_ARGS_LOGGER, got " . gettype($log_args));
        }
        if ($log_return && (!is_string($log_return) || !is_callable($log_return))) {
            throw new \InvalidArgumentException("Expected a callable string (method/function) for TraceOn::PARAM_RETURN_LOGGER, got " . gettype($log_return));
        }
        if ($log_exception && (!is_string($log_exception) || !is_callable($log_exception))) {
            throw new \InvalidArgumentException("Expected a callable string (method/function) for TraceOn::PARAM_EXCEPTION_LOGGER, got " . gettype($log_exception));
        }

        $print_backtrace_repr = ($options[self::PARAM_SHOULD_PRINT_BACKTRACE] ?? true) ? 'true' : 'false';
        if ($className === null) {
            $fullOriginalMethodRepr = $originalMethodName;
        } else if ($runkitFlags & RUNKIT_ACC_STATIC) {
            $fullOriginalMethodRepr = 'self::' . $originalMethodName;
        } else {
            $fullOriginalMethodRepr = '$this->' . $originalMethodName;
        }

        // TODO: This would be simplified with a closure, but closure implementation may crash, hasn't been tested.
        $implementation = <<<EOT
            $log_args("$fullMethodName", func_get_args());
            if ($print_backtrace_repr) {
                echo "Backtrace:\\n";
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }
            try {
                // fullOriginalMethodRepr is \$this->methodName or self::methodName
                \$value = $fullOriginalMethodRepr(...func_get_args());
                $log_return("$fullMethodName", \$value);
                return \$value;
            } catch (Exception \$e) {
                $log_exception("$fullMethodName", \$e);
                throw \$e;
            }
EOT;

        if ($className !== null) {
            if (!runkit_method_redefine($className, $methodName, $args, $implementation, $runkitFlags)) {
                throw new \RuntimeException('Failed to redefine method ' . $fullMethodName);
            }
        } else {
            if (!runkit_function_redefine($methodName, $args, $implementation)) {
                throw new \RuntimeException('Failed to redefine method ' . $fullMethodName);
            }
        }
        $this->_className = $className;
        $this->_methodName = $methodName;
        $this->_originalMethodName = $originalMethodName;
        self::$_registry[$key] = $this;
    }

    // Callback to do nothing
    public static function noop(...$arguments) { }

    public static function log_arguments($fullMethodName, array $args) {
        echo "in $fullMethodName : Params : \n";
        var_export($args);
        echo "\\n";
    }

    public static function log_return(string $fullMethodName, $value) {
        echo "\nreturn value of $fullMethodName is :\n";
        var_export($value);
        echo "\n\n";
    }

    public static function log_exception(string $fullMethodName, \Throwable $e) {
        printf("\ngot exception of class %s for %s : %s\n%s\n\n", get_class($e), $fullMethodName, $e->getMessage(), $e->getTraceAsString());
    }

    public static function log_arguments_json(string $fullMethodName, array $args) {
        printf("Calling %s: args=%s\n", $fullMethodName, json_encode($args));
    }

    /**
     * @param ?string $className (if null, this returns 0)
     * @param string $methodName
     * @return int $flags to pass to runkit for the signature of the new implementation of the method
     */
    public static function compute_runkit_flags($className, string $methodName) : int {
        if ($className === null) {
            return 0;
        }
        $method = new \ReflectionMethod($className, $methodName);
        $flags = 0;
        if ($method->isStatic()) {
          $flags |= RUNKIT_ACC_STATIC;
        }
        if ($method->isPrivate()) {
          $flags |= RUNKIT_ACC_PRIVATE;
        } else if ($method->isProtected()) {
          $flags |= RUNKIT_ACC_PROTECTED;
        } else {
          $flags |= RUNKIT_ACC_PUBLIC;
        }
        return $flags;
    }

    /**
     * @param ?string $className
     * @param string $methodName
     * @return string
     */
    public static function get_key($className, string $methodName) {
        if ($className === null) {
            return ltrim("\\", $methodName);
        }
        return sprintf('%s::%s', ltrim(strtolower($className), "\\"), strtolower($methodName));
    }

    /**
     * Stop logging calls to this function.
     * @return void
     */
    public function cleanup() {
        if (!$this->_traced) { return; }
        $key = self::get_key($this->_className, $this->_methodName);
        if ($this->_className !== null) {
            if (!runkit_method_remove($this->_className, $this->_methodName)) {
               throw new \RuntimeException('Unmock failed to remove method ' . $key);
            }
            if (!runkit_method_rename($this->_className, $this->_originalMethodName, $this->_methodName)) {
               throw new \RuntimeException('Unmock failed rename to restore original method for ' . $key);
            }
        } else {
            if (!runkit_function_remove($this->_methodName)) {
               throw new \RuntimeException('Unmock failed to remove function ' . $this->_methodName);
            }
            if (!runkit_function_rename($this->_originalMethodName, $this->_methodName)) {
               throw new \RuntimeException('Unmock failed rename to restore original function for ' . $this->_methodName);
            }
        }
        $this->_traced = false;
        unset(self::$_registry[$key]);
    }

    /**
     * Cleans up all of the TraceOn instances.
     * @return void
     */
    public static function cleanup_all() {
        $entries = self::$_registry;
        foreach ($entries as $entry) {
            $entry->cleanup();
        }
    }
}

