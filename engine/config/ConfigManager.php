<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 3:46
 */

namespace dce\config;

use dce\project\Project;
use drunk\Structure;

final class ConfigManager {
    private const EXTENDS_KEY = '#extends';

    /**
     * 取全局配置
     * @return DceConfig
     */
    public static function getCommonConfig(): DceConfig {
        static $instance;
        if (null === $instance) {
            $instance = self::newCommonConfig();
        }
        return $instance;
    }

    /**
     * 实例化一个新的公共配置
     * @return DceConfig
     */
    public static function newCommonConfig(): DceConfig {
        return new DceConfig(self::parseFile(APP_COMMON . 'config/config.php'));
    }

    /**
     * 取项目配置
     * @param Project $project
     * @return DceConfig
     */
    public static function getProjectConfig(Project $project): DceConfig {
        static $configs = [];
        if (! key_exists($project->name, $configs)) {
            $configs[$project->name] = clone self::getCommonConfig();
            $configs[$project->name]->extend(self::getPureProjectConfig($project)->arrayify());
        }
        return $configs[$project->name];
    }

    /**
     * 取未与公共配置合并的项目配置
     * @param Project $project
     * @return DceConfig
     */
    public static function getPureProjectConfig(Project $project): DceConfig {
        static $configs = [];
        if (! key_exists($project->name, $configs)) {
            $configs[$project->name] = new DceConfig(self::parseFile("{$project->path}config/config.php"));
        }
        return $configs[$project->name];
    }

    /**
     * 从文件载入配置
     * @param string $path
     * @return array
     */
    public static function parseFile(string $path): array {
        if (! is_file($path)) {
            return [];
        }
        $config = include($path);
        return self::loadExtends(is_array($config) ? $config : []);
    }

    /**
     * 加载扩展配置
     * @param array $config
     * @return array
     */
    private static function loadExtends(array $config): array {
        if ($extendFiles = ($config[self::EXTENDS_KEY] ?? [])) {
            foreach ($extendFiles as $file) {
                $extendConfig = self::parseFile($file);
                $config = Structure::arrayMerge($config, $extendConfig);
            }
            unset($config[self::EXTENDS_KEY]);
        }
        return $config;
    }
}
