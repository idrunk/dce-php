<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-16 15:28
 */

namespace dce\sharding\id_generator\client;

/**
 * Class Increment
 * @method generate() 生成趋势递增id, 最终结构, {batchId}{uidHash}{serviceId}, 除开serviceId皆可为hash因素
 * @package dce\sharding\id_generator\client
 */
class IdgClientIncrement extends IdgClient {}
