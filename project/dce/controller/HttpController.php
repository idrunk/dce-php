<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-02-11 03:54
 */

namespace dce\controller;

use dce\project\view\engine\ViewHttpJson;

class HttpController extends ViewHttpJson {
    public function empty() {
        $this->response('<!doctype html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<title>欢迎使用Dce (*´▽｀)ノノ</title>
</head>
<body>
<h1>欢迎使用Dce (*´▽｀)ノノ</h1>
<p>这是Dce默认HTTP响应页，当你看到它时，表示你的Dce框架成功运行。</p>
<p>本页面仅在你未配置根路径节点时才会显示，如果你配置了根节点，则会渲染显示该节点控制器的响应内容。</p>
<p>你可以通过`omissible_path`属性实现根节点效果，参考下述示例。</p>
<pre>
return [
    [
        "path" => "home",
        "omissible_path" => true,
        "controller" => "IndexController->index",
    ],
];
</pre>
<p>上述配置定义了名为`home`的项目的节点配置，通过设置`omissible_path`为`true`实现可省略路径访问，即你可以通过`http://127.0.0.1/`路径请求`IndexController->index`控制器方法</p>
</body>
</html>');
    }

    public function emptyAjax() {
        $this->assign('info', '请求成功，祝你愉快 (*^▽^*)');
    }
}