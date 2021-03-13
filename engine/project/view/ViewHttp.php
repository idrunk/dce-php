<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 11:04
 */

namespace dce\project\view;

use dce\Dce;
use dce\project\request\RawRequestHttp;
use dce\project\request\Request;

abstract class ViewHttp extends View {
    protected RawRequestHttp $httpRequest;

    private bool $rendered = false;

    private static array $statusMessageMapping = [
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '404' => '404 Not Found',
    ];

    public function __construct(Request $request) {
        parent::__construct($request);
        $this->httpRequest = $request->rawRequest;
        $this->renderCache();
    }

    public function status(int $code, string $reason = ''): void {
        $this->httpRequest->status($code, $reason ?: (self::$statusMessageMapping[$code] ?? ''));
    }

    /** @inheritDoc */
    public function call(string $method): void {
        // 如果未渲染过, 则执行控制器并渲染 (支持缓存逻辑)
        if (! $this->rendered) {
            parent::call($method);
            $this->render(); // 自动渲染
        }
    }

    /** 渲染响应内容 */
    final public function render(): void {
        if ($this->rendered) {
            return;
        }
        $this->setContentType();
        $dataId = $this->beforeRender();
        if ($this->rendered) {
            return;
        }
        $content = $this->rendering();
        $this->response($content);
        $this->afterRender($dataId, $content);
    }

    /**
     * 响应Http请求
     * @param string $content
     */
    public function response(string $content): void {
        $this->rendered = true;
        $this->httpRequest->response($content);
    }

    /** 渲染缓存页面 */
    private function renderCache(): void {
        if ($this->request->node->apiCache & 1) {
            $cacheData = Dce::$cache->shmDefault->get(['api_data', $this->request->node->pathFormat]);
            if (is_array($cacheData)) {
                if ($this->request->node->apiCache & 4) {
                    // 如果缓存了数据又缓存了页面, 则表示页面肯定没变化, 则可以直接渲染缓存页
                    $content = Dce::$cache->shmDefault->get(['page_data', $this->request->node->pathFormat]);
                } else {
                    foreach ($cacheData as $k => $v) {
                        $this->assignStatus($k, $v);
                    }
                    $content = $this->rendering();
                }
                if ($content) {
                    $this->setContentType();
                    $this->response($content);
                }
            }
        }
    }

    /** 渲染前置事件 */
    private function beforeRender(): string {
        $dataId = '';
        if ($this->request->node->apiCache & 4) {
            $dataId = md5(serialize($this->getAllAssignedStatus()));
            $cachedDataId = Dce::$cache->shmDefault->get(['api_data_id', $this->request->node->pathFormat]);
            if ($dataId === $cachedDataId && $cachedPage = Dce::$cache->shmDefault->get(['page_data', $this->request->node->pathFormat])) {
                $this->response($cachedPage);
            }
        }
        return $dataId;
    }

    /**
     * 渲染后置事件
     * @param string $dataId
     * @param string $content
     */
    private function afterRender(string $dataId, string $content): void {
        if ($this->request->node->apiCache & 1) {
            Dce::$cache->shmDefault->set(['api_data', $this->request->node->pathFormat], $this->getAllAssignedStatus());
        }
        if ($this->request->node->apiCache & 4) {
            Dce::$cache->shmDefault->set(['api_data_id', $this->request->node->pathFormat], $dataId);
            Dce::$cache->shmDefault->set(['page_data', $this->request->node->pathFormat], $content);
        }
    }

    /** 设置响应内容类型 */
    abstract protected function setContentType(): void;

    /** 渲染页面数据 */
    abstract protected function rendering(): string;
}
