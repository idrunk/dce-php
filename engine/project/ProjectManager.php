<?php
/**
 * Author: Drunk
 * Date: 2016-11-27 4:40
 */

namespace dce\project;

use dce\Dce;
use dce\loader\Loader;

class ProjectManager {
    /** @var Project[] $projects 所有项目 */
    private static array $projects;

    /** @var string 内置服务项目根目录 */
    private const SERVER_DIR = DCE_ROOT . 'project/';

    /** 初始化应用项目配置 */
    public static function scanLoad() {
        $projectPaths = $projects = [];
        foreach (
            [
                self::SERVER_DIR,
                APP_PROJECT_ROOT,
                ... Dce::$config->projectPaths ?? []
            ] as $path
        ) {
            if (! is_dir($path)) {
                throw (new ProjectException(ProjectException::PROJECT_PATH_INVALID))->format($path);
            }
            $lastPathChar = substr($path, -1);
            if ($lastPathChar === '/') { // 以斜杠结尾的, 将扫描该路径下的目录作为项目
                // 应用目录下所有子目录均视为项目
                $projectPaths = [... $projectPaths, ... glob($path . '*', GLOB_ONLYDIR) ?: []];
            } else { // 非以斜杠结尾的, 则直接视为项目目录
                $projectPaths[] = $path;
            }
        }
        foreach ($projectPaths as $projectPath) {
            $projectName = pathinfo($projectPath, PATHINFO_FILENAME);
            $projects[$projectName] = new Project($projectName, realpath($projectPath) . '/');
            // 预加载项目类库
            Loader::prepareProject($projects[$projectName]);
        }
        self::$projects = $projects;
    }

    /**
     * 取指定项目
     * @param string $projectName
     * @return Project|null
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
