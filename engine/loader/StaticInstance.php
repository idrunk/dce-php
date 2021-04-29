<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-18 19:09
 */

namespace dce\loader;

use Attribute;

/**
 * 静态实例器, 自动实例化类的静态属性 (用作类接口时, 尝试扫描并实例化其下静态属性; 用作静态属性注解或类型约束时, 自动实例化该属性)
 * @package dce\base
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
interface StaticInstance {}