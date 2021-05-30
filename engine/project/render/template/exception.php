<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/5/30 17:47
 */

/**
 * @var bool $status
 * @var int $code
 * @var string $message
 */
?>
<!doctype html>
<html lang="zh">
<head>
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0,user-scalable=no">
    <meta charset="UTF-8">
    <title><?=($code ?? 0) >= 500 ? '💥' : '💢'?> <?=$message ?? ''?></title>
    <style>
        * {
            margin: 0;
        }
        body{
            margin: 0;
            display: flex;
            align-items: center;
            align-content: center;
            justify-content: center;
            height: 100vh;
        }
        dt {
            font-size: 30px;
        }
        dd {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<dl>
    <dt>
        <span><?=($code ?? 0) >= 500 ? '💥' : '💢'?></span>
        <strong><?=$code ?? ''?></strong>
    </dt>
    <dd><?=$message ?? ''?></dd>
</dl>
</body>
</html>