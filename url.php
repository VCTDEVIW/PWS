<?php
/**
    This module is currently under construction!
**/
namespace P3Q\PWS;

interface ServerDepOps {
    public function sd_r($fp);
    public function sd_w($fp);
}

trait ServerDep {
    static $config, $mapper = [];
    
    public function __construct() {
        echo "\nServerDep loaded.\n\n";
    }
}

abstract class ServerDepDriver implements ServerDepOps {
    use ServerDep;
    
    public function __construct() {
        error_reporting(E_ALL);
    }

    public function sd_r($fp) {
        
    }
    
    public function sd_w($fp) {
        
    }
}

class Server extends ServerDepDriver {
    public static function create($schema, $proto) {
        self::$config = [
                'proto' => $proto,
                'bind' => $schema,
            ];
        
        self::$mapper = ['/' => [
                'get' => 'default'
            ]];
        
        //var_export(self::$config);
        //var_export(self::$mapper);
    }
    
    private function respond($json) {
        header('Content-type: application/json charset=utf-8;');
        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    final static function debug($target) {
        $opt['config'] = 'var_export(self::$config); echo "\n\n";';
        $opt['mapper'] = 'var_export(self::$mapper);';
        
        if (!! $opt[$target]) {
            eval($opt[$target]);
        } else {
            echo "Error parameter invoked with debug().\n\n";
        }
    }
    
    public static function append($api, $method, $eval) {
        $arr = self::$mapper;
        $arr[$api][$method] = $eval;
        
        self::$mapper = $arr;
    }
    
    public static function query($api, $method) {
        //var_export(self::$mapper[$api][$method]);
        
        try {
            if (isset(self::$mapper[$api][$method])) {
                eval(self::$mapper[$api][$method]);
            } else {
                throw new exception('Error 404! Not found.');
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}

function get($uriPath, $code) {
    global $servInt;
    $compose = <<< CODE
        global \$app$servInt;
        \$app$servInt::append('$uriPath', 'get', \$code);
    CODE;
    //var_dump($compose);
    eval($compose);
}

function post($uriPath, $code) {
    global $servInt;
    $compose = <<< CODE
        global \$app$servInt;
        \$app$servInt::append('$uriPath', 'post', \$code);
    CODE;
    //var_dump($compose);
    eval($compose);
}

function emu_req($api, $method) {
    global $servInt;
    $compose = <<< CODE
        global \$app$servInt;
        \$app$servInt::query('$api', '$method');
    CODE;
    //var_dump($compose);
    eval($compose);
}




// ******** ******** ******** ********

$servInt = 1;
$app1 = new Server;
$app1::create('127.0.0.1:8080', 'https');


$v1_test_func1 = '
    $dt = time();
    echo "$dt";
    echo "中文內容";
';
get('/api/v1/test/func1', $v1_test_func1);

$v1_test_func1 = '
    echo PHP_VERSION;
';
post('/api/v1/test/func1', $v1_test_func1);


$api = '/api/v1/test/func1';
$method = 'get';
emu_req($api, $method);


$app1::debug('config');
$app1::debug('mapper');

