<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2021/3/23 23:23
 */

namespace dce\project\render;

use dce\Dce;
use dce\project\Controller;
use dce\project\node\Node;
use dce\project\request\RawRequest;
use dce\project\request\Request;

class TemplateRenderer extends Renderer {
    /** @var string $request 当前请求 */
    protected Request $request;

    /** @var string $templatePath 模板文件路径 */
    protected string $templatePath;

    /** @var string $layoutPath 布局文件路径 */
    protected string $layoutPath;

    /** @var string $templateCacheDir 模板缓存目录 */
    protected string $templateCacheDir;

    /** @inheritDoc */
    protected function prepare(Controller $controller, bool $isResponseMode): void {
        // 需先预热, 因为父构造函数可能会直接从缓存渲染, 渲染时需要模板文件
        $this->warmUp($controller->request);
        parent::prepare($controller, $isResponseMode);
        $controller->assign('ctrl', $controller);
        $controller->assign('req', $controller->request);
    }

    /**
     * 模板预热
     * @param Request $request
     * @throws RenderException
     */
    protected function warmUp(Request $request): void {
        $this->request = $request;
        $templateRoot = "{$request->project->path}template/";
        $this->templatePath = $templateRoot . self::getRender($request);
        if (! is_file($this->templatePath) )
            throw (new RenderException(RenderException::TEMPLATE_NOTFOUND))->format($this->templatePath);
        $this->layoutPath = ($request->node->templateLayout ?? '') ? $templateRoot . $request->node->templateLayout : '';
        if ($this->layoutPath && ! is_file($this->layoutPath))
            throw (new RenderException(RenderException::LAYOUT_NOTFOUND))->format($this->layoutPath);
        $this->templateCacheDir = $request->config->cache['file']['template_dir'] ?: APP_RUNTIME . 'tpl/';
    }

    /** @inheritDoc */
    protected function setContentType(RawRequest $rawRequest): void {
        // 不一定是html, 可能是xml/text等一切其他的内容, 所以不自动header
        // $rawRequest instanceof RawRequestHttp && $rawRequest->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(Controller $controller, mixed $data): string {
        return self::renderPhp($this->template($controller), false === $data ? $controller->getAllAssigned() : $data);
    }

    /**
     * 渲染PHP模板
     * @param string $filepath
     * @param array $data
     * @return string
     */
    public static function renderPhp(string $filepath, array $data = []): string {
        extract($data);
        ob_start();
        require $filepath;
        return ob_get_clean();
    }

    /**
     * 编译模板取文件名
     * @param Controller $controller
     * @return string
     */
    protected function template(Controller $controller): string {
        $cacheTemplatePath = $this->templateCacheDir . preg_replace('/[\\/\\\:]/', '-',
                str_replace($this->request->project->path, $this->request->project->name . '/', $this->templatePath));
        // 如果缓存模板不存在, 或者未开启模板缓存, 且模板文件有变化了, 则进入编译缓存流程
        if (
            ! is_file($cacheTemplatePath)
            || ! ($controller->request->node->renderCache & Node::CACHE_TEMPLATE)
            && Dce::$cache::fileIsModified([$this->templatePath, $this->layoutPath])
        ) {
            if (! is_dir($this->templateCacheDir))
                mkdir($this->templateCacheDir, 0755, true);
            // 如果模板文件改变了, 或者未找到缓存文件, 则重建缓存
            $fullCode = $this->compile();
            file_put_contents($cacheTemplatePath, $fullCode, LOCK_EX);
        }
        return $cacheTemplatePath;
    }

    /**
     * 编译模板, 提取合并所有关系文件内容
     * @return string
     */
    protected function compile(): string {
        if ($this->layoutPath) {
            $rootDir = dirname($this->layoutPath);
            $rootContent = file_get_contents($this->layoutPath);
            // 使用布局时, 内容模板文件若有PHP脚本, 则脚本必须闭合
            $rootContent = preg_replace('/(\blayout_content\b[\s\S]+?\?>)/', "$1\n<?php require '$this->templatePath'; ?>", $rootContent);
        } else {
            $rootDir = dirname($this->templatePath);
            $rootContent = file_get_contents($this->templatePath);
        }
        return self::loadAllContent($rootContent, $rootDir);
    }

    /**
     * 递归提取合并PHP关联脚本内容
     * @param string $rootContent
     * @param string $rootDir
     * @return string
     */
    protected static function loadAllContent(string $rootContent, string $rootDir): string {
        $fragments = token_get_all($rootContent);
        $fragmentCount = count($fragments);
        $rootContent = '';
        $isInInclude = false;
        for ($i = 0; $i < $fragmentCount; $i ++) {
            $fragment = $fragments[$i];
            if (is_string($fragment)) {
                if ($isInInclude) {
                    // 如果进到了引用语句, 则关闭标志, 开启一段新的php语句
                    $isInInclude = false;
                    $rootContent .= "<?php ;";
                } else {
                    $rootContent .= $fragment;
                }
            } else {
                [$type, $content] = $fragment;
                if ($isInInclude) {
                    if ($type === T_CONSTANT_ENCAPSED_STRING) {
                        // 如果处于引用语句, 且当前部分为字符串, 则表示为引用文件地址, 则递归载入内容
                        $childPath = trim($content, '"\'');
                        ! file_exists($childPath) && $childPath = "$rootDir/$childPath";
                        $subContent = file_get_contents($childPath);
                        $rootContent .= self::loadAllContent($subContent, dirname($childPath));
                    } else if ($type === T_CLOSE_TAG) {
                        $isInInclude = false;
                    }
                } else if (in_array($type, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE])) {
                    // 如果进到引用语句, 则关掉前面的PHP语句, 如果前面部分是赋值等运算符, 则会报错fatal_error, 因此禁止在模板中将引用参与计算
                    $rootContent .= ";?>";
                    $isInInclude = true;
                } else {
                    $rootContent .= $content;
                }
            }
        }
        return $rootContent;
    }
}