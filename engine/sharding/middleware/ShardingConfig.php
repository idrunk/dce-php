<?php
namespace dce\sharding\middleware;

use dce\config\ConfigLibInterface;
use dce\config\Config;

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

    /** @var string|null 分库ID字段 (若配置了ID字段, 则将使用生成器生成ID, 若同时配置了sharding_column, 则该字段将作为ID的基因字段) */
    public string|null $idColumn = null;

    /** @var string|null 分库路由字段 (未配置sharding_column时将以id_column作为分库字段) */
    public string|null $shardingColumn = null;

    /** @var string 分库依据字段, 有配置 $idColumn 则取之, 否则取 $shardingColumn */
    public string $idShardingColumn;

    /** @var string 分库依据字段, 有配置 $shardingColumn 则取之, 否则取 $idColumn */
    public string $shardingIdColumn;

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
                throw new MiddlewareException("分库配置错误, 分库类型异常或未配置");
            }
            if (self::TYPE_MODULO === $config['type']) {
                $config['modulus'] = count($config['mapping']);
            }
            $config['flip_mapping'] = array_flip($config['mapping']);
            krsort($config['flip_mapping']);
            $config['cross_update'] = !! ($config['cross_update'] ?? false);
            $tables = $config['table'] ?? [];
            unset($config['table']);
            foreach ($tables as $tableName => $tableConfig) {
                // 若配置了ID字段, 则将使用生成器生成ID, 若同时配置了sharding_column, 则该字段将作为ID的基因字段
                if (! isset($tableConfig['id_column'])) {
                    $tableConfig['id_column'] = null;
                }
                // 若未配置ID字段, 则将不主动生成ID, 分库将仅以sharding_column字段划分
                if (! isset($tableConfig['sharding_column'])) {
                    $tableConfig['sharding_column'] = null;
                }
                if (! $tableConfig['id_column'] && ! $tableConfig['sharding_column']) {
                    throw new MiddlewareException("分库配置错误, 未配置{$tableName}表内容切分依据字段");
                }
                $tableConfig['id_sharding_column'] = $tableConfig['id_column'] ?? $tableConfig['sharding_column'];
                $tableConfig['sharding_id_column'] = $tableConfig['sharding_column'] ?? $tableConfig['id_column'];
                $config = $tableConfig + $config;
                $config['table_name'] = $tableName;
                $config['alias'] = $alias;
                $shardingMapping[$tableName] = new self($config);
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