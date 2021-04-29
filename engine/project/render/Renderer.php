<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project\render;

use dce\Dce;
use dce\project\Controller;
use dce\project\request\RawRequest;

abstract class Renderer {
    public const TYPE_JSON = 'json';

    public const TYPE_JSONP = 'jsonp';

    public const TYPE_XML = 'xml';

    private const TYPE_TEMPLATE = 'template';

    // 后续若需要可通过动态维护这个映射表来使用用户自定义渲染器
    private static array $renderMapping = [
        self::TYPE_JSON => JsonRenderer::class,
        self::TYPE_JSONP => JsonpRenderer::class,
        self::TYPE_XML => XmlRenderer::class,
        self::TYPE_TEMPLATE => TemplateRenderer::class,
    ];

    private static array $instanceMapping = [];

    /**
     * 渲染器扩展接口
     * @param string $typeName
     * @param string $renderClass
     * @throws RenderException
     */
    final public static function extend(string $typeName, string $renderClass): void {
        if (! is_subclass_of($renderClass, self::class)) {
            throw (new RenderException(RenderException::RENDERER_EXTENDS_ERROR))->format(self::class);
        }
        self::$renderMapping[strtolower($typeName)] = $renderClass;
    }

    /**
     * 取个单例实例并赋值相关属性
     * @param Controller $controller
     * @param bool $isHttpRequest
     * @return static
     */
    final public static function inst(Controller $controller, bool $isHttpRequest): static {
        $render = key_exists($controller->request->node->render, self::$renderMapping) ? $controller->request->node->render : self::TYPE_TEMPLATE;
        if (! key_exists($render, self::$instanceMapping)) {
            self::$instanceMapping[$render] = new self::$renderMapping[$render];
        }
        self::$instanceMapping[$render]->prepare($controller, $isHttpRequest);
        return self::$instanceMapping[$render];
    }

    /**
     * Renderer constructor.
     * @param Controller $controller
     * @param bool $isHttpRequest
     */
    protected function prepare(Controller $controller, bool $isHttpRequest): void {
        $this->renderCache($controller, $isHttpRequest);
    }

    /**
     * 渲染缓存页面
     * @param Controller $controller
     * @param bool $isHttpRequest
     */
    private function renderCache(Controller $controller, bool $isHttpRequest): void {
        if ($isHttpRequest && $controller->request->node->renderCache & 1) {
            $cacheData = Dce::$cache->shmDefault->get(['api_data', $controller->request->node->pathFormat]);
            if (is_array($cacheData)) {
                if ($controller->request->node->renderCache & 4) {
                    // 如果缓存了数据又缓存了页面, 则表示页面肯定没变化, 则可以直接渲染缓存页
                    $content = Dce::$cache->shmDefault->get(['page_data', $controller->request->node->pathFormat]);
                } else {
                    $content = $this->rendering($controller, $cacheData);
                }
                if ($content) {
                    $this->setContentType($controller->request->rawRequest);
                    $controller->response($content);
                }
            }
        }
    }

    /**
     * 渲染响应内容
     * @param Controller $controller
     * @param bool $isHttpRequest
     * @param mixed|false $data
     * @param string|false|null $path
     */
    final public function render(Controller $controller, bool $isHttpRequest, mixed $data = false, string|false|null $path = null): void {
        if ($isHttpRequest) {
            if ($controller->rendered) {
                return;
            }
            $this->setContentType($controller->request->rawRequest);
            $dataId = $this->beforeRender($controller);
            if ($controller->rendered) {
                return;
            }
            $content = $this->rendering($controller, $data);
            $controller->response($content);
            $this->afterRender($controller, $dataId, $content);
        } else {
            $controller->response($this->rendering($controller, $data), $path);
        }
    }

    /**
     * 渲染前置事件
     * @param Controller $controller
     * @return string
     */
    private function beforeRender(Controller $controller): string {
        $dataId = '';
        if ($controller->request->node->renderCache & 4) {
            $dataId = md5(serialize($controller->getAllAssignedStatus()));
            $cachedDataId = Dce::$cache->shmDefault->get(['api_data_id', $controller->request->node->pathFormat]);
            if ($dataId === $cachedDataId && $cachedPage = Dce::$cache->shmDefault->get(['page_data', $controller->request->node->pathFormat])) {
                $controller->response($cachedPage);
            }
        }
        return $dataId;
    }

    /**
     * 渲染后置事件
     * @param Controller $controller
     * @param string $dataId
     * @param string $content
     */
    private function afterRender(Controller $controller, string $dataId, string $content): void {
        if ($controller->request->node->renderCache & 1) {
            Dce::$cache->shmDefault->set(['api_data', $controller->request->node->pathFormat], $controller->getAllAssignedStatus());
        }
        if ($controller->request->node->renderCache & 4) {
            Dce::$cache->shmDefault->set(['api_data_id', $controller->request->node->pathFormat], $dataId);
            Dce::$cache->shmDefault->set(['page_data', $controller->request->node->pathFormat], $content);
        }
    }

    /**
     * 设置HTTP响应内容类型
     * @param RawRequest $rawRequest
     */
    abstract protected function setContentType(RawRequest $rawRequest): void;

    /**
     * 渲染页面数据
     * @param Controller $controller
     * @param mixed $data
     * @return string
     */
    abstract protected function rendering(Controller $controller, mixed $data): string;
}