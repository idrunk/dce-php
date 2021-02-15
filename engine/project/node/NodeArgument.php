<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/4/13 23:42
 */

namespace dce\project\node;

use dce\base\TraitModel;

class NodeArgument {
    use TraitModel;

    /** @var string get参数名 */
    public string $name;

    /** @var string 匹配用参数前缀 */
    public string $prefix = '';

    /** @var string|array|callable 参数匹配器, {[regexp]: 以正则匹配, [array]: in_array方法匹配, [callable]: 自定义方法匹配} */
    public $match;

    /** @var bool 参数是否必传 */
    public bool $required = true;

    /** @var string 参数分割器 */
    public string $separator = '-';

    /** @var bool 生成url时是否自动从当前url中提取应入 */
    public bool $autoGet = true;

    public function __construct(array $properties) {
        $this->setProperties($properties);
    }
}
