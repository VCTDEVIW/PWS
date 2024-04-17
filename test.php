<?php
ini_set('max_execution_time', 30);
ini_set('memory_limit', '1G');
date_default_timezone_set('Asia/XXXXX');
error_reporting(0);

use Swoole\Runtime;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use function Swoole\Coroutine\run;
use function Swoole\Coroutine\go;
use Workerman\Swoole;

use Workerman\Worker;
use Workerman\Timer;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
require_once __DIR__ . '/vendor/autoload.php';


run(function() {
    $context = array(
        'ssl' => array(
            'local_cert'        => 'server.pem',
            'local_pk'          => 'server.key',
            'verify_peer'       => false,
            'allow_self_signed' => true,
        )
    );

    $useSSL = 0;    // 0=OFF, 1=ON
    $bindFQDN = '0.0.0.0';
    $usePort = 8080;
    $spawnProcCnt = 2;

    if ($useSSL == 0) {
        $worker1 = new Worker("http://$bindFQDN:$usePort");
    } else {
        $worker1 = new Worker("http://$bindFQDN:$usePort", $context);
        $worker1->transport = 'ssl';
    }

    $worker1->count = $spawnProcCnt;
    $worker1->name = 'API server';
    $worker1->reusePort = true;
    $worker1->reloadable = true;

    $worker1->onWorkerStart = function(Worker $worker1)
    {
        if($worker1->id === 0) {
            // ...
        }
    };

    $coNum = 20;  // 每個 Workerman HTTP 服務進程各自啟動 20 個異步非阻塞協程處理 HTTP REST API 請求
    for ($c = $coNum; $c--;) {
        Coroutine::create(function() use($worker1) {
            $worker1->onMessage = function(TcpConnection $conn, Request $req)
            {
                usleep(10);
              
                go(function() use($conn, $req) {
                    usleep(30);
                    $routing = stripslashes(stripcslashes(urldecode($req->path())));
                    router($routing, $conn, $req);
                });

                go(function() use($conn, $req) {
                    usleep(75);
                    $log = sprintf("%sT%s %s %s %s\n", date("Y-m-d"), date("H:i:sA"), $conn->getRemoteIp(), $req->method(), $req->uri());
                    Coroutine::writeFile('/data/logs/'.'/server_'.date("Y-m-d").'.log', $log, FILE_APPEND);
                });
            };
        });
    }
});

// REST API URI Path Registry
// ● Index must be all in lowercase or fail to reflect mapping!
$routerMapper['/uptime'] = ['callback' => 'retProcessUptime', 'method' => 'get'];
$routerMapper['/co'] = ['callback' => 'retCoroutine', 'method' => 'get'];
ksort($routerMapper);

$resolveRetMapper = [
    'json' => 'application/json; charset=utf-8',
    'html' => 'text/html; charset=utf-8',
    'text' => 'text/plain; charset=utf-8',
];
function resolveRet($conn, $statusCode, $type, $ret) {
    global $resolveRetMapper;
    $schema = ($type == 'json') ? json_encode($ret, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $ret;

    $response = new Response($statusCode, [
        'Content-Type' => $resolveRetMapper[$type],
    ], $schema);

    $conn->send($response);
}

function router($url, $conn, $req) {
    global $routerMapper;
    go(function() use($routerMapper, $url, $conn, $req) {
        if (array_key_exists(strtolower($url), $routerMapper))  $routerMapper[strtolower($url)]['callback']($conn, $req);

        routerAutoTrim(strtolower($url), $conn, $req);

        resolveRet($conn, 501, 'html', '<h1>API Server</h1>Error! Requested API method/invoker not implemented.<br>');
    });
}

function routerAutoTrim($url, $conn, $req) {
    global $routerMapper;

    $lastSlashPos = strrpos($url, '/');
    $schema = substr($url, 0, $lastSlashPos);
    if (array_key_exists($schema, $routerMapper))   $routerMapper[$schema]['callback']($conn, $req);
}

function retProcessUptime($conn, $req) {
    global $bootCnt, $uptimeSince;
    ++$bootCnt;

    //$counter = custom_api("");
    $uptime_min = $counter * 5 * 0.01666;
    $uptime_hr = $uptime_min * 0.01666;
    $uptime_day = $uptime_hr * 0.04166;

    $uptime_min = round($uptime_min, 4);
    $uptime_hr = round($uptime_hr, 4);
    $uptime_day = round($uptime_day, 2);

    if ($uptime_min >= 59.9999) {
        $uptime_min = 0;
        $uptime_hr += 1;
    }

    if ($uptime_hr >= 24) {
        $uptime_hr = 0;
        $uptime_day += 1;
    }

    $ret = [
        "health"=> "up",
        "current system datetime"=> date("Y-m-d").' '.date("H:i:sA"),
        "up minutes"=> $uptime_min,
        "up hours"=> $uptime_hr,
        "up days"=> $uptime_day,
        "up has been"=> floor($uptime_day).' Day '.floor($uptime_hr).' Hour '.floor($uptime_min).' Minute',
        "alive"=> $uptimeSince,
    ];

    resolveRet($conn, 200, 'json', $ret);
}

function retCoroutine($conn, $req) {
    go(function() use($conn, $req) {
        $ret = "Swoole Coroutine 協程運作中。。。";
        resolveRet($conn, 200, 'html', $ret);
    });
    Coroutine::sleep(1);
}


Worker::$eventLoopClass = 'Workerman\Events\Swoole';
Worker::$logFile = '/dev/shm/log.out';   // Default: workerman.log   =>  workerman自身相关的日志
Worker::$stdoutFile = '/dev/shm/std.out';   // Default: stdout.log   =>  输出日志, 如echo，var_dump等
Worker::runAll();
