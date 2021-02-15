<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/6/4 18:12
 */

namespace drunk\debug;

use drunk\debug\output\CliDebug;

abstract class DebugMatrix {
    private int $stepThreshold = 100;

    /** @var array 标记数据 */
    protected array $data = [];

    /**
     * 标记数据
     * @return $this
     * @throws DebugException
     */
    public function mark(): self {
        $context = self::extractBacktrace();
        $this->makeContext($context);
        return $this;
    }

    /**
     * 逐步点印, 用于cli模式下逐步输出, 而不用等到最后dump才输出, 可以更方便的追踪调试
     * @throws DebugException
     */
    public function point(): void {
        if (! $this instanceof CliDebug) {
            throw new DebugException('Point不能用在非Cli模式下');
        }
        $context = self::extractBacktrace();
        $context = $this->makeContext($context);
        if (count($this->data) > 100) {
            $this->data = array_slice($this->data, -2);
        }
        $data = self::formatData([$context], 1);
        $this->output($data);
    }

    /**
     * 按阶批量打印, 达到设定量时打印并清空一波
     * @throws DebugException
     */
    public function step(): void {
        if (count($this->getData()) >= $this->stepThreshold) {
            $this->dump();
        } else {
            $this->mark();
        }
    }

    /**
     * 组装上下文环境
     * @param array $context
     * @return array
     */
    private function makeContext(array $context): array {
        $timeRequest = ceil($_SERVER['REQUEST_TIME_FLOAT'] * 1000); // 请求时间时间戳
        $context['time'] = ceil(microtime(1) * 1000); // 当前时间戳
        $context['time_string'] = date('Y-m-d H:i:s');
        $context['filename'] = pathinfo($context['file'], PATHINFO_BASENAME); // 文件名
        $context['time_total'] = self::humanTime($context['time'] - $timeRequest); // 总耗时
        if(empty($this->getData())){
            $context['number'] = 1; // 标记点统计序号
            $context['time_prev'] = $timeRequest; // 前步时间戳
        }else{
            $context['number'] = count($this->getData()) + 1; // 标记点统计序号
            $context['time_prev'] = $this->getData()[$context['number'] - 2]['time'];
        }
        $context['time_step'] = self::humanTime($context['time'] - $context['time_prev']); // 步耗毫秒
        $this->pushData($context);
        return $context;
    }

    /**
     * 提取标记点上下文信息
     * @return array
     * @throws DebugException
     */
    private static function extractBacktrace(): array {
        $backtrace = debug_backtrace(0, 5);
        for ($i = count($backtrace) - 1; $i >= 0; $i --) {
            $class = $backtrace[$i]['class'] ?? '';
            $function = $class ? '' : $backtrace[$i]['function'] ?? '';
            if (false !== strpos($class, 'drunk\\debug')) {
                return $backtrace[$i];
            } else if (false !== strpos($function, 'test')) {
                return $backtrace[$i];
            }
        }
        throw new DebugException('获取上下文失败，请勿包装debug方法');
    }

    /**
     * 压入标记数据
     * @param array $context
     */
    private function pushData(array $context): void {
        array_push($this->data, $context);
    }

    /**
     * 清除标记数据
     */
    private function clearData(): void {
        $this->data = [];
    }

    /**
     * 取全部标记数据
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * 以var_dump格式输出标记内容
     */
    public function dump(): void {
        $dataFormatted = self::formatData($this->getData(), 1);
        $this->empty($dataFormatted);
    }

    /**
     * 以php变量输出标记内容
     */
    public function dumpVar(): void {
        $dataFormatted = self::formatData($this->getData(), 2);
        $this->empty($dataFormatted);
    }

    /**
     * 以json格式输出标记内容
     */
    public function dumpJson(): void {
        $dataFormatted = self::formatData($this->getData(), 3);
        $this->empty($dataFormatted);
    }

    /**
     * 格式化数据
     * @param array $data
     * @param int $formatType {1: dump, 2: var, 3: json}
     * @return array
     */
    private static function formatData(array $data, int $formatType): array {
        foreach ($data as &$context) {
            $context['args_formatted'] = [];
            foreach ($context['args'] as $arg) {
                if ($formatType === 1) {
                    ob_start();
                    var_dump($arg);
                    $arg = ob_get_clean();
                    // 删除多余的换行符节省视觉空间
                    $arg = preg_replace('/]=>\n(\s+)/m', '] => ', trim($arg));
                    $context['args_formatted'][] = $arg;
                } else if ($formatType === 2) {
                    $context['args_formatted'][] = var_export($arg, true);
                } if ($formatType === 3) {
                    $context['args_formatted'][] = json_encode($arg, JSON_UNESCAPED_UNICODE);
                }
            }
        }
        return $data;
    }

    /**
     * 输出并清空
     * @param array $dataFormatted
     */
    private function empty(array $dataFormatted): void {
        $this->output($dataFormatted);
        $this->clearData();
    }

    /**
     * 输出标记内容
     * @param array $dataFormatted
     * @return mixed
     */
    abstract protected function output(array $dataFormatted): void;

    /**
     * 以对人类友好化的方式处理返回时长
     * @param int $millisecond
     * @return string
     */
    private static function humanTime(int $millisecond): string {
        if ($millisecond < 1000) {
            return $millisecond . 'ms';
        }
        return floatval(sprintf('%.2f', $millisecond / 1000)) . 's';
    }
}
