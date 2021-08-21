<?php
/**
 * Author: Drunk
 * Date: 2019/9/11 10:04
 */

namespace dce\model;

use Attribute;
use dce\model\validator\checker\DateValidator;
use dce\model\validator\TypeAssignment;
use dce\model\validator\TypeChecker;
use dce\model\validator\TypeEnding;
use dce\model\validator\TypeFilter;
use dce\model\validator\ValidatorAbstract;
use dce\model\validator\ValidatorException;
use Throwable;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class Validator {
    /** @var string 默认值校验器, 填充默认属性值 */
    public const RULE_DEFAULT = 'default';

    /** @var string 过滤校验器, 过滤筛选字符 */
    public const RULE_FILTER = 'filter';

    /** @var string 日期校验器 */
    public const RULE_DATE = 'date';

    /** @var string 日期时间校验器 */
    public const RULE_DATETIME = 'datetime';

    /** @var string 邮箱校验器 */
    public const RULE_EMAIL = 'email';

    /** @var string IP4校验器 */
    public const RULE_IP = 'ip';

    /** @var string IP6校验器 */
    public const RULE_IP6 = 'ip6';

    /** @var string 数值校验器 */
    public const RULE_NUMBER = 'number';

    /** @var string 整数校验器 */
    public const RULE_INTEGER = 'integer';

    /** @var string 正则校验器 */
    public const RULE_REGULAR = 'regular';

    /** @var string 集合校验器 */
    public const RULE_SET = 'set';

    /** @var string 位集校验器 */
    public const RULE_BITSET = 'bitset';

    /** @var string 字符串校验器 */
    public const RULE_STRING = 'string';

    /** @var string URL校验器 */
    public const RULE_URL = 'url';

    /** @var string 必填校验器 */
    public const RULE_REQUIRED = 'required';

    /** @var string 必传校验器 (允许传空值) */
    public const RULE_REQUIRED_EMPTY = 'required_empty';

    /** @var string 唯一校验器 (活动记录专用) */
    public const RULE_UNIQUE = 'unique';

    private static array $validatorMap = [
        self::RULE_DEFAULT => 'dce\model\validator\assignment\DefaultValidator',
        self::RULE_FILTER => 'dce\model\validator\filter\FilterValidator',
        self::RULE_DATE => 'dce\model\validator\checker\DateValidator',
        self::RULE_DATETIME => [
            'class' => 'dce\model\validator\checker\DateValidator',
            'type' => DateValidator::TYPE_DATETIME,
        ],
        self::RULE_EMAIL => 'dce\model\validator\checker\EmailValidator',
        self::RULE_IP => 'dce\model\validator\checker\IpValidator',
        self::RULE_IP6 => [
            'class' => 'dce\model\validator\checker\IpValidator',
            'isIp4' => false,
        ],
        self::RULE_NUMBER => 'dce\model\validator\checker\NumberValidator',
        self::RULE_INTEGER => [
            'class' => 'dce\model\validator\checker\NumberValidator',
            'decimal' => false,
            'negative' => false,
        ],
        self::RULE_REGULAR => 'dce\model\validator\checker\RegularValidator',
        self::RULE_SET => 'dce\model\validator\checker\SetValidator',
        self::RULE_BITSET => 'dce\model\validator\checker\BitsetValidator',
        self::RULE_STRING => 'dce\model\validator\checker\StringValidator',
        self::RULE_URL => 'dce\model\validator\checker\UrlValidator',
        self::RULE_REQUIRED => 'dce\model\validator\ending\RequiredValidator',
        self::RULE_REQUIRED_EMPTY => [
            'class' => 'dce\model\validator\ending\RequiredValidator',
            'allowEmpty' => true,
        ],
        self::RULE_UNIQUE => 'dce\model\validator\ending\UniqueValidator',
    ];

    public array $scenario;

    public Property $property;

    /** @var string|self::RULE_* $rule */
    public string $rule;

    public ValidatorAbstract $validator;

    /** @var self[] */
    private array $orValidators = [];

    /**
     * Validator constructor.
     * @param string|array $rule 规则名, 见头部定义的常量
     * @param string|array $scenario 适用场景名
     * @param int|float|string|array|null $max 最大字符串长度或最大有效数值
     * @param int|float|string|array|null $min 最小字符串长度或最小有效数值
     * @param array|null $set 合法值集
     * @param string|array|null $regexp 匹配用正则表达式
     * @param string|array|null $keyword 搜索用字符串
     * @param string|null $error 规则通用校验失败异常提示模板
     * @param array|null $combined 唯一记录校验器联合唯一索引其他字段集
     * @param string|array|null $type 日期校验器类型
     * @param array|null $formatSet 日期校验器合法格式集
     * @param bool|null $decimal 数值校验器是否允许小数
     * @param bool|null $negative 数值校验器是否允许负数
     * @param string|int|float|false|null $default 默认校验器默认值
     */
    public function __construct(
        string|array $rule,
        string|array $scenario = [Model::SCENARIO_DEFAULT],
        int|float|string|array|null $max = null,
        int|float|string|array|null $min = null,
        array|null $set = null,
        string|array|null $regexp = null,
        string|array|null $keyword = null,
        string|array|null $error = null,
        array|null $combined = null,
        string|array|null $type = null,
        array|null $formatSet = null,
        bool|array|null $decimal = null,
        bool|array|null $negative = null,
        string|int|float|null|false $default = false,
    ) {
        $this->scenario = is_array($scenario) ? $scenario : [$scenario];
        if (is_array($rule)) {
            // 若规则为数组, 则表示传入的或逻辑规则组, 则实例化剩下的或逻辑规则验证器
            foreach ($rule as $k => $subProperties) {
                $subRule = array_shift($subProperties);
                if ($k) {
                    $this->orValidators[] = new self(
                        $subRule,
                        $this->scenario,
                        $subProperties['max'] ?? null,
                        $subProperties['min'] ?? null,
                        $subProperties['set'] ?? null,
                        $subProperties['regexp'] ?? null,
                        $subProperties['keyword'] ?? null,
                        $subProperties['error'] ?? null,
                        $subProperties['combined'] ?? null,
                        $subProperties['type'] ?? null,
                        $subProperties['formatSet'] ?? null,
                        $subProperties['decimal'] ?? null,
                        $subProperties['negative'] ?? null,
                        $subProperties['default'] ?? false,
                    );
                } else {
                    $rule = $subRule;
                    $properties = $subProperties;
                }
            }
        } else {
            null !== $max && $properties['max'] = $max;
            null !== $min && $properties['min'] = $min;
            null !== $set && $properties['set'] = $set;
            null !== $regexp && $properties['regexp'] = $regexp;
            null !== $keyword && $properties['keyword'] = $keyword;
            null !== $error && $properties['error'] = $error;
            null !== $combined && $properties['combined'] = $combined;
            null !== $type && $properties['type'] = $type;
            null !== $formatSet && $properties['formatSet'] = $formatSet;
            null !== $decimal && $properties['decimal'] = $decimal;
            null !== $negative && $properties['negative'] = $negative;
            null !== $default && $properties['default'] = $default;
        }
        $validator = self::$validatorMap[$rule] ?? null;
        if (! is_array($validator)) {
            $validator = ['class' => $validator];
        }
        $validatorClass = $validator['class'] ?? null;
        unset($validator['class']);
        $properties = array_merge($validator, $properties ?? []);
        $this->validator = $validatorClass::inst($properties);
        $this->rule = $rule;
    }

    public function setProperty(Property $property): self {
        $this->property = $property;
        return $this;
    }

    /**
     * 验证模型
     * @param Model|string|int|float|bool|null $modelValue
     * @param array $validatorEchelon
     * @throws Throwable
     * @throws ValidatorException
     */
    public static function valid(Model|string|int|float|bool|null & $modelValue, array $validatorEchelon): void {
        self::validQueue($modelValue, $validatorEchelon['assignment'] ?? []);
        self::validQueue($modelValue, $validatorEchelon['filter'] ?? []);
        self::validQueue($modelValue, $validatorEchelon['checker'] ?? []);
        self::validQueue($modelValue, $validatorEchelon['ending'] ?? []);
    }

    /**
     * 验证队列
     * @param Model|string|int|float|bool|null $modelValue
     * @param self[] $validators
     * @throws Throwable
     * @throws ValidatorException
     */
    private static function validQueue(Model|string|int|float|bool|null & $modelValue, array $validators) {
        $model = $modelValue instanceof Model ? $modelValue : null;
        foreach ($validators as $validator) {
            $value = $model ? $validator->property->getValue($model) : $modelValue;
            try {
                // 校验并取值
                $newValue = $validator->validator->checkGetValue($value, $validator->property->name, $validator->property->alias, $model);
            } catch (Throwable $throwable) {
                // 处理或验证条件, 全部验证不通过则抛出第一个验证器的异常
                foreach ($validator->orValidators as $orValidator) {
                    try {
                        $newValue = $orValidator->validator->checkGetValue($value, $validator->property->name, $validator->property->alias, $model);
                        $throwable = null;
                        break;
                    } catch (Throwable) {}
                }
                $throwable && throw $throwable;
            }
            if ($value !== $newValue) {
                $model ? $model->{$validator->property->name} = $newValue : $modelValue = $newValue;
            }
        }
    }

    /**
     * 将验证器按照类型分组
     * @param self[] $validators
     * @return self[][]
     * @throws ValidatorException
     */
    public static function validatorsClassify(array $validators): array {
        $validatorEchelon = [];
        foreach ($validators as $validator) {
            if (! $validator instanceof self) {
                throw (new ValidatorException(ValidatorException::VALIDATOR_INVALID))->format($validator::class);
            } else if ($validator->validator instanceof TypeEnding) {
                $validatorEchelon['ending'][] = $validator;
            } else if ($validator->validator instanceof TypeChecker) {
                $validatorEchelon['checker'][] = $validator;
            } else if ($validator->validator instanceof TypeFilter) {
                $validatorEchelon['filter'][] = $validator;
            } else if ($validator->validator instanceof TypeAssignment) {
                $validatorEchelon['assignment'][] = $validator;
            } else {
                throw (new ValidatorException(ValidatorException::VALIDATOR_MUST_BE_EXTENDS))->format($validator->validator::class);
            }
        }
        return $validatorEchelon;
    }

    /**
     * 扩展验证器表
     * @param array $map
     */
    public static function extendMap(array $map): void {
        foreach ($map as $name => $item) {
            self::$validatorMap[$name] = $item;
        }
    }
}
