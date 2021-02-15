<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 4:40
 */

namespace dce\project;

use dce\base\Exception;
use dce\Dce;
use dce\Loader;

class ProjectManager {
    /** @var Project[] $projects 所有项目 */
    private static array $projects;

    /**
     * 长驻服务项目根目录
     */
    private const SERVER_DIR = DCE_ROOT . 'project/';

    /**
     * 初始化应用项目配置
     */
    public static function scanLoad() {
        $projectPaths = Dce::$config->projectPaths ?? [];
        // 内置服务项目
        $projectPaths[] = self::SERVER_DIR;
        if (is_dir(APP_PROJECT_ROOT)) {
            $projectPaths[] = APP_PROJECT_ROOT;
        }
        if (empty($projectPaths)) {
            throw new Exception('未定义任何项目');
        }
        $projectsPaths = $projects = [];
        // 扫描项目路径
        foreach ($projectPaths as $v) {
            $lastPathChar = substr($v, -1);
            if ($lastPathChar === '/') { // 以斜杠结尾的, 将扫描该路径下的目录作为项目
                if (! is_dir($v)) {
                    throw new Exception("项目目录 {$v} 不存在");
                }
                $currentProjects = glob($v . '*', GLOB_ONLYDIR); // 应用目录下所有子目录均视为项目
                if (! empty($currentProjects)) {
                    $projectsPaths = array_merge($projectsPaths, $currentProjects);
                }
            } else { // 非以斜杠结尾的, 则直接视为项目目录
                $projectsPaths[] = $v;
            }
        }
        // 校验项目有效性并初始化
        foreach ($projectsPaths as $v) {
            if (! file_exists($v . '/config/nodes.php')) {
                continue; // 必须有节点配置
            }
            $projectName = pathinfo($v, PATHINFO_FILENAME);
            $projects[$projectName] = new Project($projectName, realpath($v) . '/');
            // 预加载项目类库
            Loader::prepareProject($projects[$projectName]);
        }
        self::$projects = $projects;
    }

    /**
     * 取指定项目
     * @param string $projectName
     * @return Project
     */
    public static function get(string $projectName): Project|null {
        return self::$projects[$projectName] ?? null;
    }

    /**
     * 取所有项目
     * @return Project[]
     */
    public static function getAll(): array {
        return self::$projects;
    }
}
