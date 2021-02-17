# DCE

Dce是一款基于PHP8开发的网络编程框架，支持传统Cgi式Web编程及命令行工具编程，也支持Swoole下常驻内存式Web编程与长连接服务器编程，并且设计了一套通用的RCR架构处理所有类型网络编程，让你的应用项目保持清晰整洁，助你轻松编写出易复用、好维护的代码。

![RCR架构流程图](https://drunkce.com/assets/img/rcr.728a5b53.svg)

Dce还有很多特色功能，其中最为重量级的是分库中间件，可以让你轻松实现分库查询。除此之外还提供有负载均衡连接池、远程过程调用、ID生成器、并发锁等特色功能，这些功能依赖Swoole，Dce作者也强烈推荐你在Swoole下使用Dce，配合其多进程协程模式，可以将你的服务器性能发挥到极致。

当然，除了上述功能外，如模型、校验器、缓存器、事件、查询器、活动记录等这些常规的功能模块也必不可能缺少。



### 开始使用


#### 获取

```shell
composer create-project idrunk/dce-app:@dev
```

取用例
```shell
composer create-project idrunk/dce-app:dev-sample dce-sample
```


#### 使用命令行工具

执行一个空命令
```shell
./dce
# 或者在windows下执行:
.\dce.bat
# 将响应:
#
# 你正在cli模式以空路径请求Dce接口
```

在有Swoole的Linux下启动Websocket服务器
```shell
dce websocket start
# 将响应
# Websocket server started with 0.0.0.0:20461.
```

JS连接Websocket服务
```js
const ws = new WebSocket('ws://127.0.0.1:20461');
ws.onopen = () => ws.send('');
ws.onmessage = msg => console.log(msg.data);
// 若连接成功，将在控制台打印出下述消息
/*

{"data":{"info":"恭喜！服务端收到了你的消息并给你作出了回应"}}
*/
```


#### 使用Redis连接池

```php
// 从连接池取一个Redis实例
$redis = RedisPool::inst()->setConfigs(Dce::$config->redis)->fetch();
$redis->set('homepage', 'https://drunkce.com');
// 将实例归还连接池
RedisPool::inst()->put($redis);
```


#### 数据库查询

分库查询需要进行分库规则配置，但查询方法和普通查询没区别，所以下述示例也适用于分库查询。
```php
// 查一条
$row = db('member')->where('mid', 4100001221441)->find();
// db方法位实例化查询器的快捷方法

// 简单联合查询
$list = db('member', 'm')->join('member_role', 'mr', 'mr.mid = m.mid')->select();

// 较复杂的嵌套条件查询
$list = db('member')->where([
    ['is_deleted', 0],
    ['register_time', 'between', ['2021-01-01', '2021-01-31 23:59:59']],
    [
        ['level', '>', 60],
        'or',
        ['vip', '>', 1],
    ],
    ['not exists', raw('select 1 from member_banned where mid = member.mid')],
])->select();
```

<br>

通过上述的简介，相信你对Dce已经有了一个初步认识，Dce的玩法远不止这些，你可以[点击这里](https://drunkce.com/guide/)继续深入了解。