<?php
/**
 * Author: Drunk
 * Date: 2017-2-15 16:39
 */

namespace drunk;

class Network {
    private array $response;

    private $curl;

    private array $options;

    /**
     * 远程请求
     * request constructor.
     * @param array $options curl请求配置
     */
    function __construct(array|null $options = null) {
        $this->curl = curl_init();
        $this->options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'UTF-8',
        ];
        if (is_array($options)) {
            $this->options = $options + $this->options;
        }
        curl_setopt_array($this->curl, $this->options);
    }

    /**
     * 设置请求参数
     * @param int|array $key
     * @param mixed $value
     * @return $this
     */
    public function setOption(int|array $key, mixed $value = null): self {
        $options = is_array($key) ? $key : [$key => $value];
        curl_setopt_array($this->curl, $options);
        return $this;
    }

    /**
     * 发送请求取返回数据, 若响应不正常则返回假
     * @param string $url
     * @param array|string|null $postData 若非假, 则发送post请求, 若为数组或字符串, 则作为post数据发送
     * @return bool|string
     */
    public function send(string $url, array|string|null $postData = null): bool|string {
        curl_setopt($this->curl, CURLOPT_URL, $url);
        if ($postData) {
            curl_setopt($this->curl, CURLOPT_POST, true);
            if (is_array($postData) || is_string($postData)) {
                curl_setopt($this->curl, CURLOPT_POSTFIELDS, $postData);
            }
        }
        $content = curl_exec($this->curl);
        $this->response = curl_getinfo($this->curl);
        $this->response['content_type'] = (new \finfo)->buffer($content, FILEINFO_MIME_TYPE);
        if ($this->response['http_code'] != 200) {
            return false;
        }
        return $content;
    }

    /**
     * 发送请求并将返回数据存为文件
     * @param string|null $path 若为目录, 则自动生成文件名保存, 若为空, 则返回失败
     * @param string $url
     * @param array|string|null $postData
     * @return bool
     */
    public function sendAndSave(string|null $path, string $url, array|string|null $postData = null): bool {
        if (is_dir($path)) {
            $path = $path . '/' . uniqid('', true);
        }
        if (empty($path)) {
            return false;
        }
        $content = $this->send($url, $postData);
        return File::write($path, $content);
    }

    /**
     * 取请求响应信息
     * @param string|null $option
     * @return mixed
     */
    public function response(string|null $option = null): mixed {
        return empty($option) ? $this->response : $this->response[$option];
    }

    /**
     * 析构关闭curl
     */
    function __destruct() {
        curl_close($this->curl); // 关闭请求句柄
    }

    /**
     * 发送get请求并取返回数据
     * @param string $url
     * @return bool|string
     */
    public static function sendGet(string $url): bool|string {
        return (new Network)->send($url);
    }

    /**
     * 发送post请求并取返回数据
     * @param string $url
     * @param string|array|bool $postData
     * @return bool|string
     */
    public static function sendPost(string $url, string|array|bool $postData = true): bool|string {
        return (new Network)->send($url, $postData);
    }

    public static function isLocalIp(string $ip): bool {
        if (in_array($ip, ['', '127.0.0.1'])) {
            return true;
        }
        $long = sprintf('%u', ip2long($ip));
        if ($long >= 167772160 && $long <= 184549375) {
            return true; // 10.0.0.0 – 10.255.255.255
        } else if ($long >= 2886729728 && $long <= 2887778303) {
            return true; // 172.16.0.0 – 172.31.255.255
        } else if ($long >= 3232235520 && $long <= 3232301055) {
            return true; // 192.168.0.0 – 192.168.255.255
        } else if ($long >= 2851995904 && $long <= 2852060927) {
            return true; // 169.254.1.0 - 169.254.254.255
        }
        return false;
    }
}
