<?php
/**
 * Author: Drunk
 * Date: 2020-04-29 18:32
 */

namespace dce\service\server;

use dce\project\request\Request;
use dce\project\view\ViewCli;

abstract class ViewConnection extends ViewCli {
    protected RawRequestConnection $rawRequest;

    public function __construct(Request $request) {
        parent::__construct($request);
        $this->rawRequest = $this->request->rawRequest;
    }

    /**
     * 响应客户端, 回发数据
     * @param mixed $content
     * @param string|false|null $path
     * @return mixed
     */
    protected function response(mixed $content = false, string|false|null $path = null): mixed {
        // 如果未指定路径, 则返回请求路径
        $path ??= $this->request->rawRequest->path;
        return $this->request->rawRequest->response(false === $content ? $this->getAllAssignedStatus() : $content, $path);
    }
}
