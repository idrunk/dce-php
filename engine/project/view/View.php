<?php
/**
 * Author: Drunk
 * Date: 2020-04-13 11:02
 */

namespace dce\project\view;

use dce\project\request\Request;

abstract class View {
    public Request $request;

    private array $data = [];

    public function __construct(Request $request) {
        $this->request = $request;
    }

    /**
     * 将变量指派到视图
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function assign(string $key, mixed $value): self {
        $this->data['data'][$key] = $value;
        return $this;
    }

    /**
     * 将变量批量指派到视图
     * @param array $mapping
     * @return $this
     */
    public function assignMapping(array $mapping): self {
        foreach ($mapping as $k=>$v) {
            $this->assign($k, $v);
        }
        return $this;
    }

    /**
     * 取指派的变量值
     * @param string $key
     * @return mixed
     */
    public function getAssigned(string $key): mixed {
        return $this->data['data'][$key] ?? null;
    }

    /**
     * 取指派的全部值
     * @return array
     */
    public function getAllAssigned(): array {
        return $this->data['data'] ?? [];
    }

    /**
     * 清除所有指派的值
     */
    public function clearAssigned (): void {
        unset($this->data['data']);
    }

    /**
     * 设置外层状态数据
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function assignStatus (string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * 取全部状态数据
     * @return array
     */
    public function getAllAssignedStatus (): array {
        return $this->data;
    }

    /**
     * 调用控制器方法
     * @param string $method
     */
    public function call(string $method): void {
        $this->$method();
    }
}
