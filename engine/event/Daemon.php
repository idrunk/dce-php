<?php
/**
 * Author: Drunk (drunkce.com;idrunk.net)
 * Date: 2021/10/29 22:44
 */

namespace dce\event;

use dce\base\SwooleUtility;
use dce\Dce;
use dce\log\LogManager;
use dce\project\request\Request;
use dce\rpc\DceRpcLoader;
use dce\rpc\RpcServer;
use dce\service\cron\Crontab;
use drunk\Utility;
use Swoole\Process;

class Daemon {
    public const ServiceCron = 'cron';
    public const ServiceRpc = 'rpc';

    private const DaemonCacheKey = 'dce-daemon-%s';

    private static function isRunnable(string $serviceName): bool {
        if (! match ($serviceName) {
            self::ServiceRpc => SwooleUtility::inSwooleCli() && DceRpcLoader::parse() && ! Utility::isEmpty(Dce::$config->rpcService),
            self::ServiceCron => Crontab::inst()->getTasks(),
            default => false,
        }) return false;
        [$pid, $name] = Dce::$cache->file->get(sprintf(self::DaemonCacheKey, $serviceName)) ?: [0, ''];
        return ! $name || self::getProcessName($pid) !== $name;
    }

    private static function getProcessName(int $pid): string {
        $name = trim(@file_get_contents("/proc/$pid/cmdline"), "\0");
        return str_starts_with($name, 'daemon-') ? $name : '';
    }

    private static function logDaemon(string $serviceName): void {
        if (! $name = self::getProcessName($pid = posix_getpid()))
            cli_set_process_title($name = sprintf('daemon-%s', microtime(true) * 10000));
        Dce::$cache->file->set(sprintf(self::DaemonCacheKey, $serviceName), [$pid, $name]);
    }

    private static function newRpcServer(RpcServer $rpcServer = null): RpcServer {
        ! $rpcServer && $rpcServer = RpcServer::new();
        foreach (Dce::$config->rpcService['hosts'] as $c)
            $rpcServer->addHost(RpcServer::host($c['host'], $c['port'])->setAuth($c['password'], $c['ipWhiteList'], $c['needNative'], $c['needLocal']));
        foreach (Dce::$config->rpcService['prepares'] as ['wildcard' => $wildcard, 'root' => $root]) $rpcServer->prepare($wildcard, $root);
        foreach (Dce::$config->rpcService['preloads'] as $filename) $rpcServer->preload($filename);
        return $rpcServer;
    }

    public static function tryAutoDaemon(Request $request): void {
        // 非启动服务器，且环境可运行RpcServer时，才自动启动守护进程
        if (! preg_match('/\b(?:websocket|http|tcp|rpc)\/start\b/', $request->node->pathFormat) && self::isRunnable(self::ServiceRpc))
            self::runDaemon(false)->start();
    }

    public static function runDaemon(callable|false|null $serverCallback): Process {
        return new Process(function() use($serverCallback) {
            if (($isRpcRunnable = self::isRunnable(self::ServiceRpc)) || $serverCallback) {
                $rpcServer = RpcServer::new();
                $serverCallback && call_user_func($serverCallback, $rpcServer);
                if ($isRpcRunnable) {
                    self::logDaemon(self::ServiceRpc);
                    self::newRpcServer($rpcServer);
                }
                $rpcServer->start(false);
            }

            if ($serverCallback !== false && self::isRunnable(self::ServiceCron)) {
                self::logDaemon(self::ServiceCron);
                Crontab::start();
            }

            Event::trigger(Event::AFTER_DAEMON);
        }, false, SOCK_STREAM, true);
    }

    public static function tryRunService(string $serviceName): void {
        if (DIRECTORY_SEPARATOR === '/') {
            if (! self::isRunnable($serviceName)) {
                LogManager::dce(lang(['当前服务正在某个守护进程中运行，无需再次运行.', 'The service was running in some daemon, do not need run it again.']));
                return;
            }
            self::logDaemon($serviceName);
        }
        if (self::ServiceRpc === $serviceName) {
            self::newRpcServer()->start(true);
        } else {
            Crontab::start();
        }
    }
}