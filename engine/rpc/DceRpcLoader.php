<?php
/**
 * Author: Drunk
 * Date: 2020-05-07 15:34
 */

namespace dce\rpc;

use dce\Dce;
use dce\project\Project;
use drunk\Structure;
use drunk\Utility;

final class DceRpcLoader {
    public static bool $isParsed = false;

    public static function parse(): bool {
        if (self::$isParsed) return true;

        self::parseServices();
        self::parseConnections();
        return self::$isParsed = true;
    }

    /**
     *  'rpcService' => [
     *      'hosts' => [['host'=>'', 'port'=>0, 'password'=>'', 'ipWhiteList'=>['{{ip}}', ], 'needNative'=>false, 'needLocal'=>false], ],
     *      'prepares' => [['wildcard'=>'rpc\\*', 'root'=>'{{rootDir}}'], ],
     *      'preloads' => ['{{phpFile}}', ],
     *  ]
     */
    private static function parseServices(): void {
        if (Utility::isEmpty($config = Structure::arrayChangeKeyCase(Dce::$config->rpcService))) return;

        foreach ($config['hosts'] as $i => $host) {
            ! (isset($host['host']) && isset($host['port'])) && throw new RpcException(RpcException::INVALID_RPC_SERVICE_CONFIG);
            $config['hosts'][$i] = ['host' => $host['host'], 'port' => $host['port'], 'password' => $host['password'] ?? '',
                'ipWhiteList' => $host['ipWhiteList'] ?? [], 'needNative' => $host['needNative'] ?? false, 'needLocal' => $host['needLocal'] ?? false];
        }
        foreach ($config['prepares'] ?? [] as $i => $host) {
            ! (isset($host['wildcard']) && isset($host['root'])) && throw new RpcException(RpcException::INVALID_RPC_SERVICE_CONFIG);
            $config['prepares'][$i] = ['wildcard' => $host['wildcard'], 'root' => $host['root']];
        }

        ! ($config['prepares'] ?? []) && ! ($config['preloads'] ?? []) && throw new RpcException(RpcException::INVALID_RPC_SERVICE_CONFIG);
        Dce::$config->rpcService = $config;
    }

    /**
     * @param Project $project
     * <pre>
     * Dce配置的Rpc服务将在接收处理Request后生效, 可在项目prepare阶段或者后续的controller与service中使用, 配置格式如下
     * 'rpc_connection' => [
     *    [
     *        'hosts' => [
     *            ['host' => 'host1', 'port' => 'port1', 'token' => ''],
     *            ['host' => 'host2', 'port' => 'port2', ],
     *        ],
     *        'wildcards' => ['rpc\*', 'http\server\*'],
     *    ]
     * ]
     * </pre>
     * @throws null
     */
    private static function parseConnections(): void {
        // 如果没有配置Rpc服务器, 则表示不自动启动Rpc服务, 则不比理会
        if (Utility::isEmpty($config = Structure::arrayChangeKeyCase(Dce::$config->rpcConnection))) return;

        ! isset($config[0]['hosts'][0]['host']) && throw new RpcException(RpcException::INVALID_RPC_CONNECTION_CONFIG);
        foreach ($config as $item) {
            $hosts = $item['hosts'] ?? null;
            $wildcards = $item['wildcards'] ?? null;
            ! (is_array($hosts) && is_array($wildcards))  && throw new RpcException(RpcException::INVALID_RPC_CONNECTION_CONFIG2);
            ! is_array(current([$hosts])) && $hosts = [$hosts];

            foreach ($hosts as $host) {
                ! isset($host['host']) && throw new RpcException(RpcException::INVALID_RPC_CONNECTION_CONFIG3);
            }
            RpcClient::prepare($wildcards, $hosts);
        }
    }
}
