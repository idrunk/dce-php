<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-18 19:09
 */

namespace dce\loader\attr;

use Attribute;

/**
 * 实例器接口, 自动实例化类的静态属性 (用作静态属性注解或类型约束时将自动实例化该属性)
 * @package dce\base
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
interface Constructor {}