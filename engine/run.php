<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 2:02
 */

define('DCE_CLI_MODE', stristr(php_sapi_name(), 'cli'));
// dce框架基址
define('DCE_ROOT', __DIR__ . '/../');
// 应用根目录
defined('APP_ROOT') or define('APP_ROOT', realpath(DCE_CLI_MODE ? dirname($_SERVER['PHP_SELF']): "{$_SERVER['DOCUMENT_ROOT']}/..") .'/');
// 应用公共目录
defined('APP_COMMON') or define('APP_COMMON', APP_ROOT . 'common/');
// 应用默认项目目录
defined('APP_PROJECT_ROOT') or define('APP_PROJECT_ROOT', APP_ROOT . 'project/');
// 应用运行时目录
defined('APP_RUNTIME') or define('APP_RUNTIME', APP_ROOT .'runtime/');
// Cgi入口/静态文件目录
defined('APP_WWW') or define('APP_WWW', APP_ROOT .'www/');

require_once DCE_ROOT . 'engine/Loader.php';
dce\Loader::init();
