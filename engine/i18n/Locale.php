<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-17 01:40
 */

namespace dce\i18n;

use dce\Dce;
use dce\project\request\Request;
use dce\project\request\RequestManager;

class Locale {
    /**
     * https://zh.wikipedia.org/wiki/ISO_3166-1
     */
    public const CN = 'CN';

    public string $lang;

    public string $country;

    public function __construct(Request|null $request = null) {
        $this->lang = $this->parseLang($request);
        $this->country = $this->parseCountry($request);
    }

    /**
     * 根据请求解析当前语言码
     * @param Request|null $request
     * @return string
     */
    private function parseLang(Request|null $request): string {
        return isset(Dce::$config->app['lang_parse'])
            ? call_user_func(Dce::$config->app['lang_parse'], $request)
            : $request->request['lang'] ?? (isset($request->cookie)
                ? $request->cookie->get('lang') ?: Dce::$config->app['lang']
                : $request->rawRequest->cookie['lang'] ?? Dce::$config->app['lang']
            );
    }

    /**
     * 解析用户国家码, 可以配置国家吗解析器解析, 也可以在控制器中手动解析再赋值到request->locale上
     * @param Request|null $request
     * @return string
     */
    private function parseCountry(Request|null $request): string {
        return isset(Dce::$config->app['country_parse'])
            ? call_user_func(Dce::$config->app['country_parse'], $request)
            : Dce::$config->app['country'];
    }

    /**
     * 取当前请求客户端本地化参数
     * @return static
     */
    public static function client(): self {
        $request = RequestManager::current();
        return $request->locale ?? self::server();
    }

    /**
     * 取服务器本地化参数
     * @return static
     */
    public static function server(): self {
        static $instance;
        if (null === $instance) {
            $instance = new self;
            $instance->lang = Dce::$config->app['lang'];
            $instance->country = Dce::$config->app['country'];
        }
        return $instance;
    }
}