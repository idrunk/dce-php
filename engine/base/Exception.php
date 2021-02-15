<?php
/**
 * Author: Drunk
 * Date: 2016-12-10 1:28
 */

namespace dce\base;

class Exception extends \Exception {
    public static function init () {
        set_exception_handler([__CLASS__, 'interceptor']);
    }

    public static function interceptor (\Throwable $e) {
//        if (! headers_sent()) {
//            header("HTTP/1.1 500 Internal Server Error");
//        }
        test(
            get_class($e),
            'Code:' . $e->getCode() . ' ' . $e->getMessage(),
            $e->getFile(),
            'Line:' . $e->getLine(),
            $e->getTrace(),
            $e->getMessage()
        );
    }
}
