<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 11:04
 */

namespace dce\project\view;

use Throwable;

abstract class ViewHttpApi extends ViewHttp {
    /**
     * 渲染Api成功的结果
     * @param string|null $message
     * @param int|null $code
     */
    public function success(string|null $message = null, int|null $code = null) {
        $this->assignStatus('status', true);
        $this->result($message, $code);
    }

    /**
     * 渲染Api失败的结果
     * @param string|null $message
     * @param int|null $code
     */
    public function fail(string|null $message = null, int|null $code = null) {
        $this->assignStatus('status', false);
        $this->result($message, $code);
    }

    /**
     * 异常失败的结果
     * @param Throwable $throwable
     */
    public function exception(Throwable $throwable) {
        $this->fail($throwable->getMessage(), $throwable->getCode());
    }

    /**
     * 渲染Api结果
     * @param string|null $message
     * @param int|null $code
     */
    private function result(string|null $message, int|null $code) {
        if (null !== $message) {
            $this->assignStatus('message', $message);
        }
        if (null !== $code) {
            $this->assignStatus('code', $code);
        }
        $this->render();
    }
}
