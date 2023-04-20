<?php
namespace dce\db\query\builder\schema;

use dce\db\query\QueryException;
use dce\db\query\builder\RawBuilder;
use dce\db\query\builder\SchemaAbstract;

class FrameSchema extends SchemaAbstract {
    private const VALUE_REG = '\d+(?:(?:\s+\d+)?(?::\d+){0,2}(?:\.\d+)?|-\d+)?';
    private const UNITS_REG = '(?:(?:(?:SECOND|MINUTE|HOUR|DAY)_)?(?:MICRO)?SECOND|(?:(?:HOUR|DAY)_)?MINUTE|(?:DAY_)?HOUR|DAY|WEEK|(?:YEAR_)?MONTH|QUARTER|YEAR)';
    private const PART1_REG = '(?:UNBOUNDED|(?:INTERVAL\s+)?(?:(\'?)' .self::VALUE_REG. '\1)(?:\s+' .self::UNITS_REG. ')?)\s+(?:PRECEDING|FOLLOWING)';
    private const PART2_REG = '(?:UNBOUNDED|(?:INTERVAL\s+)?(?:(\'?)' .self::VALUE_REG. '\2)(?:\s+' .self::UNITS_REG. ')?)\s+(?:PRECEDING|FOLLOWING)';
    private const VALID_REG = '/^\s*(?:ROWS|RANGE)\s+(?:BETWEEN\s+)?' .self::PART1_REG. '(?:\s+AND\s+' .self::PART2_REG. ')?\s*$/i';

    /**
     * @param string|RawBuilder $frame
     * <pre>
     * string 字符串型frame，将会进行正则校验，有效值如：ROWS UNBOUNDED PRECEDING; ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING
     * RawBuilder 不会进行正则校验，如：raw('ROWS UNBOUNDED PRECEDING', false); raw('ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING', false)
     * </pre>
     * @throws QueryException
     */
    public function setFrame(string|RawBuilder $frame): void {
        if (is_string($frame)) {
            ! preg_match(self::VALID_REG, $frame) && throw new QueryException(QueryException::WINDOW_FRAME_INVALID);
            $this->pushCondition($frame);
        } else {
            $this->pushCondition($frame);
            $this->mergeParams($frame->getParams());
        }
    }

    public function __toString(): string {
        return (string) $this->getConditions()[0];
    }
}
