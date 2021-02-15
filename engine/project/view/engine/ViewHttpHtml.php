<?php
/**
 * Author: Drunk
 * Date: 2016-12-18 5:20
 */

namespace dce\project\view\engine;

use dce\Dce;
use dce\project\request\Request;
use dce\project\view\ViewException;
use dce\project\view\ViewHttp;

abstract class ViewHttpHtml extends ViewHttp {
    /** @var string $viewRoot 项目视图根目录 */
    private string $viewRoot;

    /** @var string $templatePath 模板文件路径 */
    private string $templatePath;

    /** @var string $layoutPath 布局文件路径 */
    private string $layoutPath;

    /** @var string $templateCacheDir 模板缓存目录 */
    private string $templateCacheDir;

    /**
     * HtmlViewCgi constructor.
     * @param Request $request
     * @throws ViewException
     */
    function __construct(Request $request) {
        $this->warmUp($request);
        parent::__construct($request);
        $this->assign('request', $request);
    }

    /**
     * 模板预热
     * @param Request $request
     * @throws ViewException
     */
    private function warmUp(Request $request): void {
        $this->viewRoot = "{$request->project->path}view/";
        if (! ($request->node->phpTemplate ?? 0)) {
            throw new ViewException("当前节点{$request->node->pathFormat}未配置php_template属性");
        }
        $this->templatePath = $this->viewRoot . $request->node->phpTemplate;
        if (! is_file($this->templatePath) ) {
            throw new ViewException("模版文件 {$this->templatePath} 不存在");
        }
        $this->layoutPath = ($request->node->templateLayout ?? '') ? $this->viewRoot . $request->node->templateLayout : '';
        if ($this->layoutPath && ! is_file($this->layoutPath)) {
            throw new ViewException("布局文件 {$this->layoutPath} 不存在");
        }
        $this->templateCacheDir = $request->config->cache['file']['template_dir'] ?: APP_RUNTIME . 'tpl/';
    }

    /** @inheritDoc */
    protected function setContentType(): void {
        @$this->httpRequest->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** @inheritDoc */
    protected function rendering(): string {
        extract($this->getAllAssigned());
        ob_start();
        require $this->template();
        return ob_get_clean();
    }

    /**
     * 编译模板取文件名
     * @return string
     */
    private function template(): string {
        $cacheTemplatePath = $this->templateCacheDir . hash('md5', $this->templatePath) . '.php';
        // 如果缓存模板不存在, 或者未开启模板缓存, 且模板文件有变化了, 则进入编译缓存流程
        if (
            ! is_file($cacheTemplatePath)
            || ! ($this->request->node->apiCache & 2)
            && Dce::$cache::fileIsModified([$this->templatePath, $this->layoutPath])
        ) {
            if (! is_dir($this->templateCacheDir)) {
                mkdir($this->templateCacheDir, 0777, true);
            }
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
    private function compile() {
        if ($this->layoutPath) {
            $rootDir = dirname($this->layoutPath);
            $rootContent = file_get_contents($this->layoutPath);
            // 使用布局时, 内容模板文件若有PHP脚本, 则脚本必须闭合
            $rootContent = preg_replace('/(\blayout_content\b[\s\S]+?\?>)/', '$1'."\n".'<?php require \''.$this->templatePath.'\'; ?>', $rootContent);
        } else {
            $rootDir = dirname($this->templatePath);
            $rootContent = file_get_contents($this->templatePath);
        }
        return $this->loadAllContent($rootContent, $rootDir);
    }

    /**
     * 递归提取合并PHP关联脚本内容
     * @param string $rootContent
     * @param string $rootDir
     * @return string
     */
    private function loadAllContent(string $rootContent, string $rootDir) {
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
                        $childPath = $childPath === $this->templatePath ? $childPath : $rootDir . '/' . $childPath;
                        $subContent = file_get_contents($childPath);
                        $subRootDir = dirname($childPath);
                        $rootContent .= $this->loadAllContent($subContent, $subRootDir);
                    }
                } else {
                    if (in_array($type, [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE])) {
                        // 如果进到引用语句, 则关掉前面的PHP语句, 如果前面部分是赋值等运算符, 则会报错fatal_error, 因此禁止在模板中将引用参与计算
                        $rootContent .= ";?>";
                        $isInInclude = true;
                    } else {
                        $rootContent .= $content;
                    }
                }
            }
        }
        return $rootContent;
    }
}
