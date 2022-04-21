<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/23 10:28
 */

namespace dce\rpc;

use dce\Dce;
use drunk\Network;
use drunk\Structure;

final class RpcUtility {
    public const DEFAULT_NAMESPACE_WILDCARD = 'rpc\*';
    public const DEFAULT_TCP_HOST = '/var/run/rpc.sock';
    public const DEFAULT_TCP_PORT = 0;

    public const RESULT_TYPE_GENERAL = 0;
    public const RESULT_TYPE_OBJECT = 1;

    public const FORMATTER_DIRECT = 0;
    public const FORMATTER_LENGTH_DEFINE = 1;

    public const REQUEST_FORMATTER = [
        [
            [1, 8, self::FORMATTER_LENGTH_DEFINE], // token长度
        ],
        [
            [1, 8, self::FORMATTER_LENGTH_DEFINE], // class长度
            [9, 14, self::FORMATTER_LENGTH_DEFINE], // method长度
        ],
    ];

    public const RESPONSE_FORMATTER = [[
        [7, 8, self::FORMATTER_DIRECT], // 是否对象
    ]];

    /**
     * 二进制流数据编码器
     * @example encode(REQUEST_FORMATTER, 'rpc\MidGenerator', 'generation', json_encode(['mid'])); // (rpc\MidGeneratorgeneration["mid"]
     * @param array $formatter [
     *    [ // 数据包配置
     *      [ // 包组成单元配置
     *        fromBit, // 单元起始位序
     *        toBit, // 单元截至位序
     *        type, // 单元类型: {0:直存型, 1:后串长度定义, 2:If包(较复杂, 暂不启用)}
     *        [ext], // 语句扩展值 (If语句时表示比较条件)
     *        [ext2], // (If语句时表示上下文包截至包序, 若前ext参数非有效比较条件, 则表示为全等判断条件, 该值实为本值前移, 后续顺移)
     *        [ext3], // (If语句时表示Else上下文包截至包序)
     *      ]
     *    ]
     *  ]
     * @param mixed ...$data
     * @return string
     * @throws RpcException
     */
    public static function encode(array $formatter, ... $data): string {
        // 严格传递参数, 不做矫正, 提高性能
        // $formatter = self::formatFormatter($formatter);
        $streams = '';
        $dataIndex = 0;
        foreach ($formatter as $k => $pack) {
            $maxBitIndex = max(array_column($pack, 1));
            $bitLength = ceil($maxBitIndex / 8) * 8;
            $package = 0;
            $streamPackage = [];
            foreach ($pack as $k2 => $unit) {
                [$fromBit, $toBit, $type] = $unit;
                // mark $toBit参数可控制传递参数的位宽度, 为了性能此处不做校验, 需严格传递格式化参数
                $datum = $data[$dataIndex ++];
                switch ($type) {
                    case self::FORMATTER_DIRECT:
                        // mark 此处仅支持正整型, 为了性能不做校验, 需严格传递格式化参数
                        $package += self::calcPackageUnit($bitLength, $datum, $fromBit, $toBit);
                        break;
                    case self::FORMATTER_LENGTH_DEFINE:
                        $length = strlen($datum);
                        $streamPackage[] = $datum;
                        $package += self::calcPackageUnit($bitLength, $length, $fromBit, $toBit);
                        break;
                }
            }
            $streams .= pack(self::getPackFormat($bitLength), $package);
            foreach ($streamPackage as $stream) {
                $streams .= $stream;
            }
        }
        for(; $dataIndex < count($data); $dataIndex ++) {
            $streams .= $data[$dataIndex];
        }
        return $streams;
    }

    /**
     * 二进制流数据解码器, (支持头尾拆分解析, 头部校验通过再解后续主体, 提升性能, 传入后两个参数开启)
     * @example decode(REQUEST_FORMATTER, '(rpc\MidGeneratorgeneration["mid"]'); // ["rpc\MidGenerator", "generation", '["mid"]']
     * 头尾拆分解析非常方便, 两次传入相同参数即可, 如
     * <pre>
     *  [$authToken] = RpcUtility::decode(RpcUtility::REQUEST_FORMATTER, $requestData, 1, $streamOffset); // ["token"]
     *  [$className, $methodName, $arguments] = RpcUtility::decode(RpcUtility::REQUEST_FORMATTER, $requestData, 1, $streamOffset); // ["rpc\MidGenerator", "generation", "[]"]
     * </pre>
     * @param array $formatter 格式规则集
     * @param string $streams 待解数据
     * @param int $packOffset 格式规则偏移量, (解头部时为头部包数, 解尾部时为尾部包起始偏移量, 两者为同一个值)
     * @param int $streamOffset 数据起始偏移量, (该变量为引用地址, 函数会将执行完的数据偏移量更新到此变量), (解头部时为0, 解尾部时为除开头部后的第一个字节偏移量)
     * @return array
     * @throws RpcException
     */
    public static function decode(array $formatter, string $streams, int $packOffset = 0, int|null & $streamOffset = 0): array {
        $packLength = 0;
        if ($packOffset) {
            // 若指定了规则集偏移量, 则为头尾拆分解析
            if (! $streamOffset) {
                // 若未定义待解数据起始偏移量, 则表示为解析头部, 否则为解析尾部
                $packLength = $packOffset;
                $packOffset = 0;
            }
        }
        $byteIndex = $streamOffset ?: 0;
        $data = [];
        foreach ($formatter as $k => $pack) {
            if ($packOffset && $k < $packOffset) {
                // 若未头尾拆分解析尾部, 且当前规则未至规则偏移量, 则跳过本次循环
                continue;
            }
            $maxBitIndex = max(array_column($pack, 1));
            $byteLength = ceil($maxBitIndex / 8);
            $bitLength = $byteLength * 8;
            // 修复V,P等情况下可能无法正常还原pack format的问题
            $binary = decbin($byteLength);
            $targetBinary = str_pad('1', strlen(decbin($byteLength)), '0');
            if ($binary !== $targetBinary) {
                // 如果算出的binary与目标不等, 则进位, (如11!==10, 则应为100, 因为1/2/4/8位二进制都是1开头后接零的形式, 若不是这个形式, 则表示不是有效pack format)
                $byteLength = bindec($targetBinary . '0');
            }
            $stream = substr($streams, $byteIndex, $byteLength);
            $byteIndex += $byteLength;
            $packFormat = self::getPackFormat($bitLength);
            [, $package] = unpack($packFormat, $stream);
            $streamLengths = [];
            foreach ($pack as $k2 => $unit) {
                [$fromBit, $toBit, $type] = $unit;
                switch ($type) {
                    case self::FORMATTER_DIRECT:
                        $data[] = self::subLittleEndian($bitLength, $package, $fromBit, $toBit);
                        break;
                    case self::FORMATTER_LENGTH_DEFINE:
                        $streamLengths[] = self::subLittleEndian($bitLength, $package, $fromBit, $toBit);
                        break;
                }
            }
            foreach ($streamLengths as $streamLength) {
                // 截取定长字符串部分
                $data[] = substr($streams, $byteIndex, $streamLength);
                $byteIndex += $streamLength;
            }
            if ($packLength && $k + 1 >= $packLength) {
                // 若为头尾拆分解析头部, 且头部已经解析完毕, 则记录下个待解数据起始偏移量, 并跳出循环
                $streamOffset = $byteIndex;
                break;
            }
        }
        if (! $packLength) {
            // 若非头尾拆分解析头部, 才获取剩余数据
            $streamsLength = strlen($streams);
            if ($byteIndex < $streamsLength) {
                // 取截取完剩下的部分数据
                $data[] = substr($streams, $byteIndex);
            }
        }
        return $data;
    }

    /**
     * 根据位宽计算适合pack打包的格式化参数
     * @example getPackFormat(1); // C
     * @example getPackFormat(30); // V
     * @example getPackFormat(0); // throw RpcException
     * @param int $bitWidth
     * @return string
     * @throws RpcException
     */
    private static function getPackFormat(int $bitWidth): string {
        if ($bitWidth < 1 || $bitWidth > 64) {
            throw new RpcException(RpcException::INVALID_PACKAGE_LENGTH);
        } else if ($bitWidth > 32) {
            return 'P';
        } else if ($bitWidth > 16) {
            return 'V';
        } else if ($bitWidth > 8) {
            return 'v';
        } else {
            return 'C';
        }
    }

    /**
     * 计算打包单元的二进制位值
     * @example calcPackageUnit(8, 3, 2, 5); // 计算单元放入8位字节包后的值 // 0b01100000
     * @param int $bitWidth
     * @param int $unit
     * @param int $fromBit
     * @param int $toBit
     * @return int
     */
    private static function calcPackageUnit(int $bitWidth, int $unit, int $fromBit, int $toBit): int {
        static $log2;
        if (! $unit) {
            $binaryBitWidth = 1;
        } else {
            if (null === $log2) {
                $log2 = log(2);
            }
            $binaryBitWidth = floor(log($unit) / $log2) + 1;
        }
        $allowBitWidth = $toBit - $fromBit + 1;
        $widthDiff = $binaryBitWidth - $allowBitWidth;
        $leftWidth = $bitWidth - $fromBit - $allowBitWidth + 1;
        if ($widthDiff > 0) {
            $unit >>= $widthDiff;
        }
        $unit <<= $leftWidth;
        return $unit;
    }

    /**
     * 在小端序二进制值中从左至右提取指定的区间值
     * @example subLittleEndian(8, 96, 1, 2); // 从0b01100000中提取第2-3位的值 // 3
     * @param int $bitWidth
     * @param int $binary
     * @param int $fromBit
     * @param int $toBit
     * @return int
     */
    private static function subLittleEndian(int $bitWidth, int $binary, int $fromBit, int $toBit): int {
        return ($binary & ((2 << $bitWidth - $fromBit) - 1)) >> ($bitWidth - $toBit);
    }

    /**
     * 矫正格式化配置
     * @param array $formatter
     * @return array
     * @throws RpcException
     */
    private static function formatFormatter(array $formatter): array {
        foreach ($formatter as $k => $pack) {
            $maxBitIndex = max(array_column($pack, 1));
            $byteLength = ceil($maxBitIndex / 8);
            if ($byteLength > 8) {
                throw new RpcException(RpcException::PACKAGE_TOO_LONG);
            }
            foreach ($pack as $k2 => $unit) {
                if (is_int($unit)) {
                    $unit = [$unit, $unit, self::FORMATTER_DIRECT];
                } else if (is_array($unit)) {
                    if (! isset($unit[0])) {
                        throw new RpcException(RpcException::FORMATTER_MISSING);
                    } else if (! isset($unit[1])) {
                        $unit[1] = $unit[0];
                    }
                    if (! isset($unit[2])) {
                        $unit[2] = self::FORMATTER_DIRECT;
                    }
                } else {
                    throw new RpcException(RpcException::FORMATTER_TYPE_INVALID);
                }
                $formatter[$k][$k2] = $unit;
            }
        }
        return $formatter;
    }

    /**
     * @param string $ip
     * @return bool
     */
    public static function isLocalIp(string|null $ip): bool {
        return Network::isLocalIp($ip);
    }

    /**
     * 生成Rpc令牌
     * @param string $password
     * @return string
     */
    public static function genToken(string $password): string {
        return hash('md5', $password);
    }

    /**
     * 校验Rpc令牌
     * @param string $password
     * @param string $token
     * @return bool
     */
    public static function verifyToken(string $password, string $token): bool {
        return self::genToken($password) === $token;
    }

    /**
     * 格式化Hosts入参
     * @param array $hosts
     * @return array[]
     * @throws RpcException
     */
    public static function hostsFormat(array $hosts): array {
        $firstKey = array_key_first($hosts);
        null === $firstKey && throw new RpcException(RpcException::EMPTY_RPC_HOSTS);
        0 !== $firstKey && $hosts = [$hosts];
        foreach ($hosts as $k => $host) {
            ! (isset($host['host']) && isset($host['port'])) && throw new RpcException(RpcException::INVALID_RPC_HOSTS);
            $hosts[$k]['host'] = self::uniqueSock($host['host']);
        }
        return $hosts;
    }

    /**
     * 合并多组主机
     * @param array $hosts
     * @param array $hostsToMerge
     * @return array
     * @throws RpcException
     */
    public static function hostsMerge(array $hosts, array $hostsToMerge): array {
        $hostsToMerge = self::hostsFormat($hostsToMerge);
        foreach ($hostsToMerge as $hostToMerge) {
            if (false !== $index = Structure::arraySearchMatrix(['host' => $hostToMerge['host'], 'port' => $hostToMerge['port']], $hosts)) {
                $hosts[$index] = $hostToMerge;
            } else {
                $hosts[] = $hostToMerge;
            }
        }
        return $hosts;
    }

    public static function uniqueSock(string $sockPath): string {
        return str_ends_with($sockPath, '.sock') && ! str_contains($sockPath, Dce::getId())
            ? preg_replace('/(^|\/)([^\/]+)$/ui', '${1}'. Dce::getId() . '-$2', $sockPath) : $sockPath;
    }
}
