<?php
/**
 * Author: Drunk
 * Date: 2020-05-07 15:34
 */

namespace dce\rpc;

use dce\project\Project;

final class DceRpcClient {
    /**
     * @param Project $project
     * <pre>
     * Dce配置的Rpc服务将在接收处理Request后生效, 可在项目prepare阶段或者后续的controller与service中使用, 配置格式如下
     * [
     *   'rpc_services' => [
     *      [
     *          'hosts' => [
     *              ['host' => 'host1', 'port' => 'port1', 'token' => ''],
     *              ['host' => 'host2', 'port' => 'port2', ],
     *          ],
     *          'wildcards' => ['rpc\*', 'http\server\*'],
     *      ]
     *   ]
     * ]
     * </pre>
     * @throws null
     */
    final public static function prepare(Project $project): void {
        $rpcServers = $project->getConfig()->rpcServers;
        if (! $rpcServers) {
            // 如果没有配置Rpc服务器, 则表示不自动启动Rpc服务, 则不比理会
            return;
        }
        if (! isset($rpcServers[0]['hosts'][0]['host'])) {
            throw new RpcException('rpc_servers配置异常');
        }
        foreach ($rpcServers as $rpcServer) {
            $hosts = $rpcServer['hosts'] ?? null;
            $wildcards = $rpcServer['wildcards'] ?? null;
            if (! is_array($hosts) || ! is_array($wildcards)) {
                throw new RpcException('rpc_servers[]配置异常');
            }
            if (! is_array(current([$hosts]))) {
                $hosts = [$hosts];
            }
            foreach ($hosts as $host) {
                if (! isset($host['host'])) {
                    throw new RpcException('rpc_servers[][]配置异常');
                }
            }
            RpcClient::prepare($hosts, $wildcards);
        }
    }
}
