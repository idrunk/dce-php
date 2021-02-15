<?php
/**
 * Author: Drunk
 * Date: 2019/7/31 15:49
 */

namespace dce\db\query\builder;

use dce\db\query\QueryException;

class RawBuilder extends StatementAbstract {
    private string $statement;

    private bool $autoParenthesis;

    public function __construct(string $statement, bool|array $autoParenthesis = true, array $params = []) {
        if (is_array($autoParenthesis)) {
            $params = $autoParenthesis;
            $autoParenthesis = true;
        }
        if (preg_match_all('/(\?|:\w+)/ui', $statement, $matches, PREG_PATTERN_ORDER)) {
            $isQuestionPlaceholder = $isNamingPlaceholder = true;
            $convertedParams = [];
            foreach ($matches[1] as $k=>$placeholder) {
                if ('?' === $placeholder) {
                    $isNamingPlaceholder = false;
                } else {
                    $isQuestionPlaceholder = false;
                    $k = $placeholder;
                    $statement = preg_replace("/$placeholder\b/", '?', $statement, 1);
                }
                if (! key_exists($k, $params) && (($k = ltrim($k, ':')) && ! key_exists($k, $params))) {
                    throw new QueryException("缺少对应占位符的参数{$k}");
                }
                $convertedParams[] = $params[$k];
            }
            if (! $isQuestionPlaceholder && ! $isNamingPlaceholder) {
                throw new QueryException('请勿使用混合占位符');
            }
            $this->mergeParams($convertedParams);
        }
        $this->statement = $statement;
        $this->autoParenthesis = $autoParenthesis;
    }

    protected function valid(): void {}

    public function __toString(): string {
        return sprintf($this->autoParenthesis ? '(%s)' : '%s', $this->statement);
    }
}
