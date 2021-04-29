<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021-04-18 19:09
 */

namespace dce\loader;

use dce\base\BaseException;
use dce\event\Event;
use dce\i18n\Language;
use dce\model\Model;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

class ClassDecoratorManager {
    public static function bindDceClassLoad(): void {
        Event::on(Loader::EVENT_ON_CLASS_LOAD, [self::class, 'decorate']);
    }

    /**
     * 装饰一个类
     * @param string $className
     * @throws BaseException
     * @throws ReflectionException
     */
    public static function decorate(string $className): void {
        $refClass = new ReflectionClass($className);
        if ($refClass->implementsInterface(ClassDecorator::class) || $refClass->getAttributes(ClassDecorator::class)) {
            // 如果刚加载的类实现了装饰器接口, 或者标记了装饰器注解, 则尝试执行各种装饰动作
            self::initStaticInstance($refClass);
            self::initLanguage($refClass);
            self::initModel($refClass);
        }
    }

    /**
     * 初始实例化类静态属性
     * @param ReflectionClass $refClass
     */
    private static function initStaticInstance(ReflectionClass $refClass): void {
        $refProperties = $refClass->getProperties(ReflectionProperty::IS_STATIC);
        foreach ($refProperties as $refProperty) {
            $typeClass = self::getTypeClass($refProperty->getType());
            if ($typeClass && (($attrs = $refProperty->getAttributes(StaticInstance::class)) || is_subclass_of($typeClass, StaticInstance::class))) {
                $refProperty->setAccessible(true);
                $params = $attrs ? $attrs[0]->getArguments() : [];
                if ($refProperty->hasDefaultValue()) {
                    array_unshift($params, $refProperty->getDefaultValue());
                }
                $refProperty->setValue(new $typeClass(... $params));
            }
        }
    }

    /**
     * 从类型约束中找出类名
     * @param ReflectionType $refType
     * @return string|null
     */
    private static function getTypeClass(ReflectionType $refType): string|null {
        if ($refType instanceof ReflectionUnionType) {
            foreach ($refType->getTypes() as $refType) {
                if (class_exists($refType->getName())) {
                    return $refType;
                }
            }
        } else if (class_exists($refType->getName())) {
            return $refType;
        }
        return null;
    }

    /**
     * 初始化多语文本映射表
     * @param ReflectionClass $refClass
     * @throws BaseException
     */
    private static function initLanguage(ReflectionClass $refClass): void {
        foreach ($refClass->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $refConst) {
            if ($langAttrs = $refConst->getAttributes(Language::class)) {
                $args = $langAttrs[0]->getArguments();
                if (! $args) {
                    throw new BaseException(BaseException::LANGUAGE_MAPPING_ERROR);
                }
                new Language($args[0], $refConst->getValue());
            }
        }
    }

    /**
     * 初始化模型属性实例表
     * @param ReflectionClass $refClass
     * @throws ReflectionException
     */
    private static function initModel(ReflectionClass $refClass): void {
        if ($refClass->isSubclassOf(Model::class)) {
            $refMethod = $refClass->getMethod('initProperties');
            $refMethod->setAccessible(ReflectionMethod::IS_PUBLIC);
            $refMethod->invoke(null, $refClass); // 执行模型内置的初始化方法
        }
    }
}