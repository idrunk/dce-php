<?php
namespace dce\sharding\middleware;

use dce\config\ConfigLibInterface;
use dce\config\Config;
use JetBrains\PhpStorm\ArrayShape;

class ShardingConfig extends Config implements ConfigLibInterface {
    /** @var string 按模分库 */
    public const TYPE_MODULO = 'modulo';

    /** @var string 按区间分库 */
    public const TYPE_RANGE = 'range';

    /** @var string 数据库类型 */
    public string $dbType = 'mysql';

    /** @var string 分库类型 */
    public string $type = self::TYPE_MODULO;

    /** @var string 数据表名 */
    public string $tableName;

    /** @var int 分库目标模数 */
    public int $modulus;

    /** @var bool 是否允许跨库更新 */
    public bool $crossUpdate = false;

    /** @var bool 是否允许联表查询 */
    public bool $allowJoint = true;

    /** @var string 分库别名(规则类名) */
    public string $alias;

    /** @var array 分库规则字典, {modulo: 数据库别名与目标模数映射表, range: 数据库别名与区间起始值的映射表} */
    public array $mapping;

    /** @var array 键值翻转的分库规则字典 */
    public array $flipMapping;

    /** @var string|null 分库ID字段 (未配置idTag时此字段仅作为分库依据, 否则可自动生成ID) */
    public string|null $idColumn = null;

    /** @var string|null 若配置了idTag, 则将自动生成ID, 若同时配置了shardingColumn, 则该字段将作为ID的基因字段 */
    public string|null $idTag = null;

    /** @var string|null 分库路由字段 (未配置时将以idColumn作为分库字段, 否则以shardingColumn作为分库字段) */
    public string|null $shardingColumn = null;

    /** @var string|null 若配置了shardingColumn则将使用ID生成器处理该字段，否则按crc32处理 */
    public string|null $shardingTag = null;

    /** @var array 分库依据字段, 有配置 $shardingColumn 则取之, 否则取 $idColumn */
    #[ArrayShape(['name' => 'string', 'tag' => 'string'])]
    public string|null $shardingIdColumn;

    public string|null $shardingIdTag;

    /** @var int 拓库时分库目标模数 */
    public int $targetModulus;

    /** @var array 拓库时分库规则字典 */
    public array $targetMapping;

    /** @var array 拓库分库规则字典 */
    public array $extendMapping = [];

    /** @var self[] 表名与分库规则映射表 */
    private array $instanceMapping;

    public static function load(array $data): self {
        $shardingMapping = [];
        foreach ($data as $alias => $config) {
            $config['type'] = strtolower($config['type'] ?? null);
            if (! in_array($config['type'], [self::TYPE_MODULO, self::TYPE_RANGE])) {
                throw new MiddlewareException(MiddlewareException::CONFIG_SHARDING_TYPE_INVALID);
            }
            if (self::TYPE_MODULO === $config['type']) {
                $config['modulus'] = count($config['mapping']);
            }
            $config['flip_mapping'] = array_flip($config['mapping']);
            krsort($config['flip_mapping']);
            $config['cross_update'] = !! ($config['cross_update'] ?? false);
            $tables = $config['table'] ?? [];
            unset($config['table']);

            foreach ($tables as $tableName => $table) {
                if (isset($table['id_tag']) && ! isset($table['id_column'])) {
                    // 配置了tag则必须配置column
                    throw (new MiddlewareException(MiddlewareException::CONFIG_ID_COLUMN_EMPTY))->format($alias, $tableName);
                }
                if (isset($table['sharding_tag']) && ! isset($table['sharding_column'])) {
                    // 配置了tag则必须配置column
                    throw (new MiddlewareException(MiddlewareException::CONFIG_SHARDING_COLUMN_EMPTY))->format($alias, $tableName);
                }
                if (! isset($table['id_column']) && ! isset($table['sharding_column'])) {
                    // 必须至少配置一个分库规则
                    throw (new MiddlewareException(MiddlewareException::CONFIG_TABLE_SHARDING_RULE_EMPTY))->format($tableName);
                }
                [$table['sharding_id_column'], $table['sharding_id_tag']] = isset($table['sharding_column'])
                    ? [$table['sharding_column'], $table['sharding_tag'] ?? null] : [$table['id_column'] ?? null, $table['id_tag'] ?? null];

                $shardingMapping[$tableName] = new self(['table_name' => $tableName, 'alias' => $alias, ] + $table + $config);
            }
        }
        $instance = self::inst();
        $instance->instanceMapping = $shardingMapping;
        return $instance;
    }

    /**
     * 是否按模分库
     * @return bool
     */
    public function isModulo(): bool {
        return self::TYPE_MODULO === $this->type;
    }

    /**
     * 是否区间分库
     * @return bool
     */
    public function isRange(): bool {
        return self::TYPE_RANGE === $this->type;
    }

    /**
     * @return self[]
     */
    public function all(): array {
        return $this->instanceMapping;
    }

    /**
     * @param array $conditions
     * @return self[]
     */
    public function filter(array $conditions): array {
        $instances = $this->all();
        $matchedInstances = [];
        foreach ($instances as $tableName => $instance) {
            $matched = true;
            foreach ($conditions as $k => $v) {
                if ($instance->get($k) !== $v) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                $matchedInstances[$tableName] = $instance;
            }
        }
        return $matchedInstances;
    }

    /**
     * @param string $tableName
     * @return self|null
     */
    public function getConfig(string $tableName): self|null {
        $mapping = $this->all();
        return $mapping[$tableName] ?? null;
    }
}