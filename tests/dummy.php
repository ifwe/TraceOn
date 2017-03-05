<?php
namespace TraceOn\Tests;

/**
 * Dummy class for testing TraceOn
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
class dummy {
    public function getPublicStaticFunction(string $arg) : string {
        return self::publicStaticFunction($arg);
    }

    public static function publicStaticFunction(string $arg) {
        return 'dummy' . 'publicStaticFunction:' . $arg;
    }

    public function instanceFunction(string ...$args) {
        return 'retval:' . json_encode($args);
    }
}
