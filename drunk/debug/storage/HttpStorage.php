<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2020/2/3 2:01
 */

namespace drunk\debug\storage;

class HttpStorage extends DebugStorage {
    public function push(string $path, string $content, string $logType = self::LogTypeAppend): void {
        $url = $this->genPath($path);
        $content .= "\n";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ('https' === strtolower(parse_url($url, PHP_URL_SCHEME))) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain', 'Dce-Debug: 1', "Log-Type: $logType"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
    }
}