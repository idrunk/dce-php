<?php
/**
 * Author: Drunk
 * Date: 2020-05-14 11:29
 */

namespace dce\service\extender;

use dce\project\Controller;

abstract class ModuloExtender extends Extender {
    protected string $shardingType = 'modulo';

    final public static function run(Controller $cli): void {
        static $instance;
        if (null !== $instance) {
            return;
        }
        $instance = new static();
        $instance->cli = $cli;
        $instance->go();
    }

    /**
     * 执行拓库迁移数据脚本
     * @throws null
     */
    private function go(): void {
        $this->prepare();
        $this->fillExtend();
        $this->applyExtend();
        $this->applyDoneSync();
        $this->clearRedundant();
    }

    /**
     * 准备工作, 校验扩展配置并检查扩展库是否正常
     * @throws ExtenderException
     */
    private function prepare(): void {
        if (! $this->checkExtendConfig()) {
            throw (new ExtenderException(ExtenderException::PLEASE_PREPARE_EXTENDS_CONFIG))->format($this->dbType);
        }
        $this->print("扩展配置校验通过");
        if (! $this->dbsExists()) {
            throw new ExtenderException(ExtenderException::EXTEND_DATABASE_NOT_EXISTS);
        }
        $this->print("数据库已连接");
    }

    /**
     * 建表, 并将源库待迁移数据写入到扩展库
     * @throws ExtenderException
     */
    private function fillExtend(): void {
        if (! $this->createExtendTable()) {
            throw new ExtenderException(ExtenderException::EXTEND_TABLE_CREATE_FAILED);
        }
        $this->print("扩展表已就绪");
        if (! $this->runSync()) {
            throw new ExtenderException(ExtenderException::EXTEND_DATA_TRANSFER_FAILED);
        }
    }

    /**
     * 分批迁移全部待迁数据
     * @return bool
     */
    protected function runSync(): bool {
        do {
            $wasDone = $this->insertIntoExtend();
        } while (! $wasDone);
        $this->print("拓展库数据迁移完毕");
        return true;
    }

    /**
     * 应用新的分库配置, (若接入了配置中心, 则此处可全自动实现热拓库)
     * @throws ExtenderException
     */
    private function applyExtend(): void {
        if (! $this->checkApplyExtendConfig()) {
            $this->applyExtendConfig();
            if (! $this->checkApplyExtendConfig()) {
                throw new ExtenderException(ExtenderException::EXTENDS_CONFIG_NOT_APPLIED);
            }
        }
    }

    /**
     * 迁移应用新配期间的可能写入到源库的待迁移数据
     * @throws ExtenderException
     */
    private function applyDoneSync(): void {
        if (! $this->runSync()) {
            throw new ExtenderException(ExtenderException::DATA_TRANSFER_FAILED);
        }
    }

    /**
     * 删除源库中已被迁移到新库的冗余数据
     * @throws ExtenderException
     */
    private function clearRedundant(): void {
        if (! $this->hashClear()) {
            throw new ExtenderException(ExtenderException::REDUNDANT_CLEAR_FAILED);
        }
        $this->print("源冗余数据清除完毕");
        $this->print("分库拓展完毕!");
    }

    /**
     * 定义为单例类
     */
    abstract protected function __construct();

    /**
     * 校验拓库配置
     * @return bool
     */
    abstract protected function checkExtendConfig(): bool;

    /**
     * 校验扩展库是否可正常连接
     * @return bool
     */
    abstract protected function dbsExists(): bool;

    /**
     * 在拓展库中创建扩展表
     * @return bool
     */
    abstract protected function createExtendTable(): bool;

    /**
     * 将源库中待迁移到扩展库的数据写入到扩展库
     * @return bool
     */
    abstract protected function insertIntoExtend(): bool;

    /**
     * 将扩展配置应用到分库配置
     * @return bool
     */
    abstract protected function applyExtendConfig(): bool;

    /**
     * 校验扩展配置是否已应用到分库配置
     * @return bool
     */
    abstract protected function checkApplyExtendConfig(): bool;

    /**
     * 删除源库中已迁移到扩展库的冗余数据
     * @return bool
     */
    abstract protected function hashClear(): bool;
}
