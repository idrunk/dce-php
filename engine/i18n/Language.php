<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-17 01:40
 */

namespace dce\i18n;

use Attribute;
use dce\base\BaseException;
use dce\Dce;
use dce\loader\attr\Constructor;
use Stringable;

/**
 * 多语种支持文本类
 * @package dce\i18n
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::TARGET_PROPERTY)]
class Language implements Stringable, Constructor {
    /**
     * https://zh.wikipedia.org/wiki/ISO_639-1
     */
    public const ZH  = 'zh';

    public const EN = 'en';

    /** @var int[] 语言码索引映射表  */
    private static array $langIndexMapping = [
        self::ZH => 0,
        self::EN => 1,
    ];

    /** @var self[] ID与Lang实例映射表 */
    private static array $idLangMapping = [];

    /** @var int|string 文本ID */
    public int|string $id;

    /** @var string 指定语种 */
    private string $lang;

    /** @var Stringable[] 语种文本映射表 */
    private array $textMapping;

    /** @var bool 是否加载过扩展语种文本表 */
    private bool $loadedMapping = false;

    /** @var Stringable[] 格式化参数集 */
    private array $parameters;

    public function __construct(string|Stringable|array $textMapping, string|int|null $id = null) {
        $this->textMapping = is_array($textMapping) ? $textMapping : [$textMapping];
        $id && $this->setMapping($id);
    }

    /**
     * 设置语言ID与实例的映射
     * @param string|int $id
     */
    private function setMapping(string|int $id): void {
        $this->id = $id;
        self::$idLangMapping[$id] = $this;
    }

    /**
     * 设置语种
     * @param string $lang
     * @return $this
     */
    public function lang(string $lang): self {
        $this->lang = $lang;
        return $this;
    }

    /**
     * 格式化
     * @param string|Stringable ...$parameters
     * @return self
     */
    public function format(string|Stringable ...$parameters): self {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * 字符串化
     * @return string
     * @throws BaseException
     */
    public function __toString(): string {
        $lang = $this->lang ?? Locale::client()->lang;
        $this->loadMapping();
        $langIndex = self::$langIndexMapping[$lang] ?? -1;
        $text = $this->textMapping[$lang] ?? $this->textMapping[$langIndex] ?? current($this->textMapping);
        if (isset($this->parameters)) {
            if (isset($this->lang)) {
                foreach ($this->parameters as $parameter) {
                    if ($parameter instanceof self) {
                        // 如果当前指定了语种, 且参数中有Language实例, 则自动指定当前语种
                        $parameter->lang($this->lang);
                    }
                }
            }
            $text = sprintf($text, ...$this->parameters);
        }
        return $text;
    }

    /**
     * 尝试加载用户自定义语种文本映射表
     * @throws BaseException
     */
    private function loadMapping(): void {
        if (! isset($this->id) || $this->loadedMapping || ! $langMappingCall = Dce::$config->app['lang_mapping'] ?? null) {
            return; // 如果当前对象无ID, 或者已加载过映射表, 或者应用未配置方法, 则返回
        }
        $this->loadedMapping = true;
        if (! is_callable($langMappingCall) || ! is_array($langMapping = call_user_func($langMappingCall, $this->id))) {
            throw new BaseException(BaseException::LANGUAGE_MAPPING_CALLABLE_ERROR);
        }
        foreach ($langMapping as $code => $text) {
            $this->textMapping[$code] = $text;
        }
    }

    /**
     * 根据ID找实例
     * @param string|int $id
     * @return static|null
     */
    public static function find(string|int $id): self|null {
        return key_exists($id, self::$idLangMapping) ? self::$idLangMapping[$id] : null;
    }
}