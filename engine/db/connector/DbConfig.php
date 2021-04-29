<?php
namespace dce\db\connector;

use dce\config\ConfigException;
use dce\config\ConfigLibInterface;
use dce\config\Config;

class DbConfig extends Config implements ConfigLibInterface {
    /** @var string 别名 */
    public string $label;

    /** @var string Mysql主机名 */
    public string $host;

    /** @var string Mysql用户名 */
    public string $dbUser;

    /** @var string Mysql密码 */
    public string $dbPassword;

    /** @var null|string Mysql库名 */
    public string|null $dbName;

    /** @var int Mysql端口 */
    public int $dbPort = 3306;

    /** @var bool 是否主库 */
    public bool $isMaster = false;

    /** @var int 实例池连接容量 */
    public int $maxConnection = 16;

    public const DEFAULT_DB = 'default';

    /** @var array */
    private array $databases = [];

    public static function load(array $databases): self {
        if ($databases) {
            if (! is_array(current($databases))) { // 将普通单库配置通用化
                $databases = [self::DEFAULT_DB => $databases];
            }
            foreach ($databases as $dbAlias => $dbNode) {
                if (! $dbNode) {
                    throw (new ConfigException(ConfigException::DB_CONFIG_EMPTY))->format($dbAlias);
                } else if (! isset($dbNode[0])) { // 若节点为单库, 则通用化为多库形式
                    $dbNode = [$dbNode];
                }
                $dbNodeMs = ['slave' => []];
                foreach ($dbNode as $db) {
                    $config = new self($db);
                    if ($config->isMaster) {
                        $dbNodeMs['master'][] = $config;
                    } else {
                        $dbNodeMs['slave'][] = $config;
                    }
                }
                if (! isset($dbNodeMs['master'])) { // 如果未配置主库, 则从库亦皆为主库
                    $dbNodeMs['master'] = $dbNodeMs['slave'];
                } else if (! $dbNodeMs['slave']) {
                    $dbNodeMs['slave'] = $dbNodeMs['master'];
                }
                $databases[$dbAlias] = $dbNodeMs;
            }
        }
        $instance = new self([]);
        $instance->databases = $databases;
        return $instance;
    }

    public function all(): array {
        return $this->databases;
    }

    public function filter(string|null $dbName = null, bool|null $needMaster = true): array|null {
        $databases = $this->all();
        if (null !== $needMaster) {
            foreach ($databases as $dbAlias=>$database) {
                if ($needMaster) {
                    $databases[$dbAlias] = $database['master'];
                } else {
                    $databases[$dbAlias] = $database['slave'];
                }
            }
        }
        if (null === $dbName) {
            return $databases;
        }
        return $databases[$dbName] ?? null;
    }

    /**
     * @param string|null $dbName
     * @param bool $needMaster
     * @return self[]
     */
    public function getConfig(string|null $dbName = null, bool $needMaster = true): array {
        if (null === $dbName) {
            $dbName = array_key_first(self::all());
        }
        return $this->filter($dbName, $needMaster);
    }

    public function getDefault(bool $needMaster = true): array {
        return $this->getConfig(self::DEFAULT_DB, $needMaster);
    }
}