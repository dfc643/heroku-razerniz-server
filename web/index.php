<?php
/* ******************************************
          RazerNiz Proxy Server
		         ver 3.0
****************************************** */

// Server configuration
$__serviceid__ = 'fbmcproxy';     // 服务器密码
$__transmode__ = 'json';            // 传输模式：json 字符串或 zlib 压缩
$__jsonpayload__ = 'data1,data2';  // JSON 载荷：在 JSON 中包含数据的两个项（注意顺序）
$__logging__ = 1;                   // 日志记录：1 启用 0 禁用
$__hostsdeny__ = array();           // 网址黑名单：禁止访问的网站   $__hostsdeny__ = array('.youtube.com', '.youku.com');
$__timeout__ = 30;                  // 服务器读取超时：资源拉取时长（秒）

// Global Settings
$__version__  = '3.0.0';
$__content_type__ = array(
        'image/jpeg',
        'image/png',
        'image/gif',
        'video/webm',
        'font/woff2',
        'video/mp4',
        'application/font-woff',
        'application/x-gzip',
        'application/x-silverlight-app',
        'application/x-shockwave-flash')[rand(0,9)];
$__content__ = '';
set_time_limit(0);
date_default_timezone_set("PRC");

function message_html($title, $banner, $detail) {
    $error = <<<MESSAGE_STRING
{"code":"${title}","data":"${banner}","extra":"${detail}"}
MESSAGE_STRING;
    return $error;
}


function decode_request($data) {
    global $__transmode__, $__jsonpayload__;
    
    switch($__transmode__) {
        case 'zlib':
            list($headers_length) = array_values(unpack('n', substr($data, 0, 2)));
            $headers_data = gzinflate(substr($data, 2, $headers_length));
            $body = substr($data, 2+intval($headers_length));
            break;
        
        case 'json':
            $retData = (array)json_decode($data);
            $headers_data = base64_decode($retData[explode(',',$__jsonpayload__)[0]]);
            $body = base64_decode($retData[explode(',',$__jsonpayload__)[1]]);
            break;
        
        default:
            echo message_html('500', 'transmode_undefined', 'fatal_error');
            exit -1;
    }

    $method  = '';
    $url     = '';
    $headers = array();
    $kwargs  = array();

    foreach (explode("\n", $headers_data) as $kv) {
        $pair = explode(':', $kv, 2);
        $key  = $pair[0];
        $value = trim($pair[1]);
        if ($key == 'ReqMethod') {
            $method = $value;
        } else if ($key == 'DestHost') {
            $url = $value;
        } else if (substr($key, 0, 3) == 'FX-') {
            $kwargs[strtolower(substr($key, 3))] = $value;
        } else if ($key) {
            $key = join('-', array_map('ucfirst', explode('-', $key)));
            $headers[$key] = $value;
        }
    }
    
    if (isset($headers['Content-Encoding'])) {
        if ($headers['Content-Encoding'] == 'deflate') {
            $body = gzinflate($body);
            $headers['Content-Length'] = strval(strlen($body));
            unset($headers['Content-Encoding']);
        }
    }
    return array($method, $url, $headers, $kwargs, $body);
}


function echo_content($content) {
    global $__serviceid__, $__content_type__;
    if ($__content_type__ != 'image/x-png') {
        echo $content ^ str_repeat($__serviceid__[0], strlen($content));
    } else {
        echo $content;
    }
}


function curl_header_function($ch, $header) {
    global $__content__, $__content_type__;
    $pos = strpos($header, ':');
    if ($pos == false) {
        $__content__ .= $header;
    } else {
        $key = join('-', array_map('ucfirst', explode('-', substr($header, 0, $pos))));
        if ($key != 'Transfer-Encoding') {
            $__content__ .= $key . substr($header, $pos);
        }
    }
    if (preg_match('@^Content-Type: ?(audio/|image/|video/|application/octet-stream)@i', $header)) {
        $__content_type__ = 'image/x-png';
    }
    if (!trim($header)) {
        header('Content-Type: ' . $__content_type__);
    }
    return strlen($header);
}


function curl_write_function($ch, $content) {
    global $__content__;
    if ($__content__) {
        // for debug
        // echo_content("HTTP/1.0 200 OK\r\nContent-Type: text/plain\r\n\r\n");
        echo_content($__content__);
        $__content__ = '';
    }
    echo_content($content);
    return strlen($content);
}


function curl_request_options($method, $url, $header, $body = '') {
    global $__content__, $__content_type__;
    $__content__ = '';
    $__ret_header__ = '';
    header('Content-Type: ' . $__content_type__);
    
    foreach ($header as $key => $value) {
        $__ret_header__ .= $value . PHP_EOL;
    }
    
    $context = [
        'http' => [
            'method' => $method,
            'header' => $__ret_header__,
            'content' => $body
        ],
        
        'ssl' => [
            'verify_peer' => false,
            'allow_self_signed'=> true
        ]
    ];
    $context = stream_context_create($context);
    $resp = file_get_contents($url, false, $context);
    
    foreach ($http_response_header as $key => $value) {
        $__content__ .= $value . "\r\n";
    }
    $__content__ .= "\r\n" . $resp;
    
    echo_content($__content__);
}


function post() {
    list($method, $url, $headers, $kwargs, $body) = @decode_request(@file_get_contents('php://input'));

    $password = $GLOBALS['__serviceid__'];
    if ($password) {
        if (!isset($kwargs['password']) || $password != $kwargs['password']) {
            header("HTTP/1.0 403 Forbidden");
            echo message_html('403', 'serviceid_undefined', 'fatal_error');
            exit(-1);
        }
    }

    $hostsdeny = $GLOBALS['__hostsdeny__'];
    if ($hostsdeny) {
        $urlparts = parse_url($url);
        $host = $urlparts['host'];
        foreach ($hostsdeny as $pattern) {
            if (substr($host, strlen($host)-strlen($pattern)) == $pattern) {
                echo_content("HTTP/1.0 403\r\n\r\n" . message_html('403', "dest_site_blocked:".$host, $url));
                exit(-1);
            }
        }
    }
	
	// Loging access log to file
	if($GLOBALS['__logging__'] == 1) {
        $logdir = dirname('__FILE__');
        $fp_accesslog = fopen($logdir."/fwprxy2_log/".date("Ymd").".log", "a+");
        fputs($fp_accesslog, date("H:i:s")."@spit@".($_SERVER['HTTP_CF_CONNECTING_IP']?:$_SERVER['REMOTE_ADDR'])."@spit@$method@spit@$url\r\n");
        fclose($fp_accesslog);
	}

    if ($body) {
        $headers['Content-Length'] = strval(strlen($body));
    }
    if (isset($headers['Connection'])) {
        $headers['Connection'] = 'close';
    }

    $header_array = array();
    foreach ($headers as $key => $value) {
        $header_array[] = join('-', array_map('ucfirst', explode('-', $key))).': '.$value;
    }

    $timeout = $GLOBALS['__timeout__'];

    $curl_opt = array();

    switch (strtoupper($method)) {
        case 'HEAD':
            $curl_opt[CURLOPT_NOBODY] = true;
            break;
        case 'GET':
            break;
        case 'POST':
            $curl_opt[CURLOPT_POST] = true;
            $curl_opt[CURLOPT_POSTFIELDS] = $body;
            break;
        case 'PATCH':
        case 'PUT':
        case 'DELETE':
        case 'OPTIONS':
            curl_request_options($method, $url, $header_array, $body);
            return;
            break;
        default:
            echo_content("HTTP/1.0 502\r\n\r\n" . message_html('502', 'invalid_method_'.$method, $url));
            exit(-1);
    }

    $curl_opt[CURLOPT_HTTPHEADER] = $header_array;
    $curl_opt[CURLOPT_RETURNTRANSFER] = true;
    $curl_opt[CURLOPT_BINARYTRANSFER] = true;

    $curl_opt[CURLOPT_HEADER]         = false;
    $curl_opt[CURLOPT_HEADERFUNCTION] = 'curl_header_function';
    $curl_opt[CURLOPT_WRITEFUNCTION]  = 'curl_write_function';

    $curl_opt[CURLOPT_FAILONERROR]    = false;
    $curl_opt[CURLOPT_FOLLOWLOCATION] = false;

    $curl_opt[CURLOPT_CONNECTTIMEOUT] = $timeout;
    $curl_opt[CURLOPT_TIMEOUT]        = $timeout;

    $curl_opt[CURLOPT_SSL_VERIFYPEER] = false;
    $curl_opt[CURLOPT_SSL_VERIFYHOST] = false;

    $ch = curl_init($url);
    curl_setopt_array($ch, $curl_opt);
    $ret = curl_exec($ch);
    $errno = curl_errno($ch);
    if ($GLOBALS['__content__']) {
        echo_content($GLOBALS['__content__']);
    } else if ($errno) {
        if (!headers_sent()) {
            header('Content-Type: ' . $__content_type__);
        }
        $content = "HTTP/1.0 502\r\n\r\n" . message_html('502', "curl_error_$errno",  curl_error($ch));
        echo_content($content);
    }
    curl_close($ch);
}

function get() {
    header("HTTP/1.0 451 I'm Hatsune Miku");
    exit -1;
}


function main() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        post();
    } else {
        get();
    }
}

main();
