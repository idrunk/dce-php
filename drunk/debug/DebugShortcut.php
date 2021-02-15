<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/19 15:46
 */

use drunk\debug\Debug;

if (! function_exists('test')) {
    /**
     * 断点打印调试
     * @param mixed $_
     */
    function test(mixed $_ = null): void {
        testGet(true)->test();
    }
}

if (! function_exists('testPass')) {
    /**
     * 不断点打印调试
     * @param null $_
     */
    function testPass(mixed $_ = null): void {
        testGet(true)->mark()->dump();
    }
}

if (! function_exists('testPoint')) {
    /**
     * 逐步点印
     * @param null $_
     */
    function testPoint($_ = null): void {
        testGet()->point();
    }
}

if (! function_exists('testStep')) {
    /**
     * 按阶批量打印
     */
    function testStep(mixed $_ = null): Debug {
        return testGet()->step();
    }
}

if (! function_exists('testMark')) {
    /**
     * 标记调试点
     */
    function testMark(mixed $_ = null): Debug {
        return testGet()->mark();
    }
}

if (! function_exists('testDump')) {
    /**
     * 调试输出
     * @param mixed ...$args
     */
    function testDump(mixed ... $args): void {
        testGet()->die()->dump(... $args);
    }
}

if (! function_exists('testDumpPass')) {
    /**
     * 不断点调试输出
     * @param mixed ...$args
     */
    function testDumpPass(mixed ... $args): void {
        testGet()->dump(... $args);
    }
}

if (! function_exists('testGet')) {
    /**
     * 取Debug实例
     * @param string|bool|null $identity 实例标识 (不传则取test_mark系同实例)
     * @return Debug
     */
    function testGet(string|bool|null $identity = null): Debug {
        if (! is_string($identity)) {
            $identity = Debug::identity(!! $identity);
        }
        return Debug::get($identity);
    }
}

if (! function_exists('testSetFile')) {
    /**
     * 快捷设置File储存引擎
     * @param string $path
     * @param string $root
     * @param string|bool $identity
     * @return Debug
     * @throws \drunk\debug\DebugException
     */
    function testSetFile(string $path, string $root = '', string|bool $identity = false): Debug {
        return testGet($identity)->storage(Debug::STORAGE_FILE, $root)->log($path);
    }

    /**
     * 快捷设置Http储存引擎
     * @param string $path
     * @param string $root
     * @param string|bool $identity
     * @return Debug
     * @throws \drunk\debug\DebugException
     */
    function testSetHttp(string $path, string $root = '', string|bool $identity = false): Debug {
        return testGet($identity)->storage(Debug::STORAGE_HTTP, $root)->log($path);
    }
}
