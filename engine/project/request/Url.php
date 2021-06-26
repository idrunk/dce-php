<?php
/**
 * Author: Drunk
 * Date: 2016-12-16 2:18
 */

namespace dce\project\request;

use dce\project\node\Node;
use dce\project\node\NodeManager;
use dce\project\ProjectManager;

class Url {
    private RawRequestHttp $request;

    private static array $argumentCodeMapping = [
        'source' => ['-', '.'],
        'encode' => ['%2D', '%2E'],
    ];

    public function __construct(RawRequestHttp $request) {
        $this->request = $request;
    }

    /**
     * 拼装url
     * @param string $path
     * @param array $arguments
     * @param string|null $suffix
     * @return string|null
     */
    public static function make(string $path, array $arguments = [], string|null $suffix = null): string|null {
        $nodeTree = NodeManager::getTreeByPath($path);
        $familyPaths = $nodeTree->getParentIds();
        $urlParts = [];
        $currentNode = null;
        foreach ($familyPaths as $nodePath) {
            // 取出当前节点配置
            $matchedNode = self::makeMatchNode($nodePath, $arguments, $suffix);
            if (! $matchedNode) {
                return null;
            }
            /**
             * @var Node $node
             * @var array $matchedArguments
             */
            [$node, $matchedArguments] = $matchedNode;
            $urlParts[] = self::makePart($node, $matchedArguments); // 当前节点url部分
            if (null === $currentNode) {
                $currentNode = $node;
            }
        }
        $url = implode('', array_reverse($urlParts));
        $url .= $suffix ?? current($currentNode->urlSuffix);
        $rewriteMode = ProjectManager::get($currentNode->projectName)->getConfig()->rewriteMode;
        $url = '/' . ($rewriteMode ? '' : '?') . $url;
        if ($arguments) {
            // 如果还有未取完的get参数, 则拼装为传统get查询字符串
            $url .= ($rewriteMode ? '?' : '&') . http_build_query($arguments);
        }
        return $url;
    }

    /**
     * 按路径, 参数与后缀取出最匹配的节点
     * @param string $path
     * @param array $arguments
     * @param string $suffix
     * @return array
     */
    private static function makeMatchNode (string $path, array & $arguments, string|null $suffix): array|null {
        $nodeTree = NodeManager::getTreeByPath($path);
        if ($nodeTree->isEmpty()) {
            return null;
        }
        $nodes = $nodeTree->nodes;
        if ($arguments) {
            $nodes = self::sortNodeByArgumentsCnt($nodes, $arguments);
        }
        $matchedArguments = [];
        foreach($nodes as $k => $node) {
            if (null !== $suffix && ! in_array($suffix, $node->urlSuffix)) {
                // 后缀不匹配, 则跳过
                continue;
            }
            foreach ($node->urlArguments as $argument) {
                if (key_exists($argument->name, $arguments)) {
                    $matchedArguments[$k][$argument->name] = $arguments[$argument->name];
                } else {
                    if ($argument->required) {
                        // 如果有必填的参数未传, 则表示当前Node不匹配, unset
                        unset($nodes[$k], $matchedArguments[$k]);
                        continue 2;
                    }
                }
            }
        }
        if (! $nodes) {
            // 如果节点unset完还未匹配到, 则匹配失败
            return null;
        }
        // 第一个节点为最佳匹配节点
        $matchedNodeKey = key($nodes);
        foreach ($matchedArguments[$matchedNodeKey] as $name => $value) {
            // 删掉已被匹配的参数
            unset($arguments[$name]);
        }
        return [$nodes[$matchedNodeKey], $matchedArguments[$matchedNodeKey]];
    }

    /**
     * 对节点集排序, 让最可能匹配到的节点移到节点集之前
     * @param Node[] $nodes
     * @param array $arguments
     * @return array
     */
    private static function sortNodeByArgumentsCnt (array $nodes, array $arguments): array {
        $argumentsKeys = array_keys($arguments);
        $intersectCntArray = [];
        foreach ($nodes as $k => $node) {
            $argsKeys = array_column($node->urlArguments, 'name');
            $intersectCntArray[$k] = count(array_intersect($argsKeys, $argumentsKeys));
        }
        array_multisort($intersectCntArray, SORT_NUMERIC, SORT_DESC, $nodes); // sort the nodes by intersect count
        return $nodes;
    }

    /**
     * 拼装Url组件
     * @param Node $node
     * @param array $matchedArguments
     * @return string
     */
    private static function makePart (Node $node, array $matchedArguments): string {
        $nodeName = $node->omissiblePath ? null : $node->pathName;
        $urlArgs = '';
        foreach ($node->urlArguments as $i => $argument) {
            $argumentValue = $matchedArguments[$argument->name] ?? null;
            if (($i || $nodeName) && (null !== $argumentValue || $node->urlPlaceholder)) {
                $urlArgs .= $argument->separator;
            }
            if (null !== $argumentValue) {
                $urlArgs .= $argument->prefix;
                $urlArgs .= self::argumentEncode($argumentValue);
            }
        }
        // 移除空参数留下的尾部分割符
        $urlArgs = rtrim($urlArgs, '-/');
        // 如果即为可省略路径, 又没有必传参数, 则置为空
        $urlPart = ! $nodeName && ! $urlArgs ? '' : '/';
        if (null !== $nodeName) {
            $urlPart .= $nodeName;
        }
        if ($urlArgs) {
            $urlPart .= $urlArgs;
        }
        return $urlPart;
    }

    /**
     * 参数安全编码
     * @param string $argument
     * @return mixed
     */
    private static function argumentEncode (string $argument): string {
        $argument = rawurlencode($argument);
        return str_replace(self::$argumentCodeMapping['source'], self::$argumentCodeMapping['encode'], $argument);
    }

    /**
     * 参数安全解码
     * @param string $argument
     * @return string
     */
    public static function argumentDecode (string $argument): string {
        $argument = str_replace(self::$argumentCodeMapping['encode'], self::$argumentCodeMapping['source'], $argument);
        return rawurldecode($argument);
    }

    /**
     * 判断是否有效地址
     * @param string $url
     * @param bool $isFull    是否作全路径判断
     * @return bool|int
     */
    public static function validate (string $url, bool $isFull = true): bool {
        return $isFull ? preg_match('/^https?:\/\//i', $url): ! preg_match('/^(?:#+|(?:js|javascript).+|\s*)$/i', $url);
    }

    /**
     * 将url转为全路径
     * @param string $url
     * @param string $urlReference  所依据的全路径url
     * @return string
     */
    public static function fill (string $url, string $urlReference): string {
        $url = preg_replace('/[\r\n]+/', '', $url);
        if (self::validate($url)) {
            return $url; // 若为全地址, 则无需处理
        }
        if (! self::validate($urlReference)) {
            throw new RequestException(RequestException::INVALID_URL);
        }
        $isAbsolute = substr($url, 0, 1) === '/'; // 是否绝对路径
        $parse = parse_url($urlReference);
        if (empty($parse)) {
            throw new RequestException(RequestException::CANNOT_PARSE_URL);
        }
        if (! $isAbsolute) { // 若非绝对路径, 则需取参考url的路径补上当前处理的相对路径url作为绝对路径url
            if (substr($url, 0, 2) === './') {
                $url = substr($url, 2);
            }
            $url =  preg_replace('/[^\/]*$/', '', $parse['path']) . $url;
        }
        $port = empty($parse['port']) ? '' : ':'.$parse['port'];
        return $parse['scheme'] .'://'. $parse['host'] . $port . $url;
    }

    /**
     * 根据url取根域名
     * @param string $url
     * @return mixed
     */
    public function getRootDomain (string|null $url = null): string {
        if (! $url) {
            $url = self::getCurrent();
        }
        $host = parse_url($url, PHP_URL_HOST) ;
        if (is_numeric(substr($host, -1))) {
            return $host; // 若是ip, 则返回整个ip
        }
        $suffixMapReg = '/\b[a-z0-9-]+(?:\.(?:com|net|cn|gov|edu|co|cm|us|uk))?\.[a-z]+$/i';
        return preg_match($suffixMapReg, $host, $root) ? $root[0] : $host; // 如果匹配到则返回根域名, 否则返回整个域名
    }

    /**
     * 取url域名
     * @param string $url
     * @return mixed
     */
    public function getDomain (string|null $url = null): string {
        if (! $url) {
            $url = self::getCurrent();
        }
        return parse_url($url, PHP_URL_HOST);
    }

    /**
     * 返回当前url
     * @return string
     */
    public function getCurrent (): string {
        $host = 'http' . ($this->request->isHttps ? 's' : '') . '://' . $this->request->host; // 自带port
        return self::fill($this->request->requestUri, $host);
    }
}
