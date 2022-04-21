<?php
/**
 * Author: Drunk
 * Date: 2021/12/01 01:18
 */

namespace dce\base;

// for Model
enum CoverType {
    case Unset; // unset default
    case Replace; // replace value
    case Ignore; // ignore existed
}

// for Model property
enum StorableType {
    case Array;
    case BackedEnum;
    case Scalar;
    case Serializable;
    case Unable;
}

// for Tree
enum TreeTraverResult {
    case StopChild; // 停止遍历子节点
    case StopSibling; // 停止遍历兄弟节点
    case StopAll; // 停止遍历全部
}

// for Parser
enum ParserTraverResult {
    case Exception; // 需抛异常
    case Return; // 需返回
    case Break; // 需退出循环
    case Continue; // 需跳过循环轮
    case Step; //
}