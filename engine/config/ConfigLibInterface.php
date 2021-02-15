<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020-10-02 18:24
 */

namespace dce\config;

interface ConfigLibInterface {
    /**
     * 加载配置集
     * @param array $data
     * @return self
     */
    public static function load(array $data): self;

    /**
     * 取配置库
     * @return array
     */
    public function all(): array;
}