<?php
/**
 * Author: Drunk
 * Date: 2017-1-29 8:11
 */

use dce\i18n\Language;

drunk\debug\Debug::enableShortcut();

dce\db\Query::enableShortcut();

if (! function_exists('lang')) {
    /**
     * 快捷的生成一个Language实例, 若第一个参数为int, 则将其作为id, 且将textMapping置为空映射表
     * @param int|string|Stringable|array $textMapping
     * @param string|int|null $id
     * @return Language
     */
    function lang(int|string|Stringable|array $textMapping, string|int|null $id = null): Language {
        if (! $id && is_int($textMapping)) {
            $id = $textMapping;
            $textMapping = [];
        }
        return  $id && ($lang = Language::find($id)) ? $lang : new Language($textMapping, $id);
    }
}