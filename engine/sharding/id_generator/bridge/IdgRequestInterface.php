<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-19 1:41
 */

namespace dce\sharding\id_generator\bridge;

interface IdgRequestInterface {
    /**
     * 向服务端注册, 并获取ID池配置
     * @param string $tag
     * @return IdgBatch
     */
    function register(string $tag): IdgBatch;

    /**
     * 获取ID池
     * @param string $tag
     * @return IdgBatch
     */
    function generate(string $tag): IdgBatch;
}
