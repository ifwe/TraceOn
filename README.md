TraceOn
=======

## Overview

[![Build Status](https://travis-ci.org/runkit7/TraceOn.svg?branch=master)](https://travis-ci.org/runkit7/TraceOn)

A simple PHP utility to trace(investigate) invocations of instance/static/global methods (and log those calls).
This requires [runkit7 (fork of the runkit PECL)](https://github.com/runkit7/runkit7).

Logging for parameters, return types, backtraces, and exceptions is enabled by default.
Code using this utility can disable or replace the default implementation to log those details.

## Authors

Tyson Andre

## Requirements

- PHP version 7.0 or greater
- [runkit7 (fork of runkit)](https://github.com/runkit7/runkit7) must be installed and enabled.
- runkit must be enabled in your php.ini settings (`extension=runkit.so)

## License

The TraceOn framework is licensed under the <a href="http://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>

## Motivations

This makes it somewhat easier/faster to trace a bug or an unexpected behavior's cause.

- Much faster than locating, modifying, and possibly deploying php code with additional logging when investigating an issue.
- Works on classes even if they were loaded before this framework could be loaded, thanks to runkit methods.
- Easy to work with, if developers are able to run short snippets of php code in the environment being used.

## Installation

Currently, this project can be checked out to a directory and required manually.

```php
require_once $traceOnDir . '/src/TraceOn/TraceOn.php';
```

TODO: publish this on packagist and add a release version.


## How this class works:

- This redefines the old method to a temporary method wrapping the original method.
- This framework will then call various callbacks when entering the method
  (to log args, return values, and backtraces),
- It also logs after exiting the original method (to log the return value, or, when an exception is caught, to log and rethrow).
  The default callbacks print data to stdout, but those callbacks can be changed to the name of a global function or static method (e.g. to log to a file or logging service).

## Examples:

Log cache fetches to stdout, assuming the (user-defined) cache class name is MyCache  (log only the params `MyCache::get` was called with, don't print any backtraces or return values):

```php
use \TraceOn\TraceOn;

$cacheTrace = new TraceOn('MyCache', 'get', [
    TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
    TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
    TraceOn::PARAM_RETURN_LOGGER => false,
    TraceOn::PARAM_EXCEPTION_LOGGER => false
]);
// ... call some method/api which would use the cache
$cacheTrace->cleanup()
```

Log as much detail as possible about cache fetches to stdout (backtraces, etc):

```php
use \TraceOn\TraceOn;

$cacheTrace = new TraceOn('MyCache', 'get');
// ... call some method/api which would use the cache
$cacheTrace->cleanup()
// Or TraceOn::cleanup_all()
```

Log calls of a user-defined function to stdout, assuming the (user-defined) cache class name is MyCache  (log the params `myfunc` was called with and the return value, don't print any backtraces):

```php
use \TraceOn\TraceOn;

// Pass null instead of a class name when mocking global functions.
$cacheTrace = new TraceOn(null, 'myfunc', [
    TraceOn::PARAM_SHOULD_PRINT_BACKTRACE => false,
    TraceOn::PARAM_ARGS_LOGGER => TraceOn::JSON_ARGS_LOGGER,
    TraceOn::PARAM_RETURN_LOGGER => TraceOn::DEFAULT_RETURN_LOGGER,
    TraceOn::PARAM_EXCEPTION_LOGGER => false,
]);
// ... call some method/api which would use myfunc()
$cacheTrace->cleanup()
```

Aside: It is possible to use this framework to investigate calls to internal(built in) functions (e.g. `time`), although runkit7 has some bugs related to internal function overrides.
To attempt this, `runkit.internal_override=On` must be temporarily added to php.ini (Cannot use `ini_set()`).

## Running tests

1. Make sure runkit is installed and enabled in php.ini
2. Run `composer install` in this directory.
3. vendor/bin/phpunit tests

-----

README.md: Copyright 2017 Ifwe Inc.

README.md is licensed under a Creative Commons Attribution-ShareAlike 4.0 International License.

You should have received a copy of the license along with this work. If not, see <http://creativecommons.org/licenses/by-sa/4.0/>.
