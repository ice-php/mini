<?php
declare(strict_types=1);

namespace icePHP;
class MiniBase
{
    // 控制最大的请求长度,1M
    private static $maxRequest = 1048576;

    private static $mcaName = array('m', 'c', 'a');

    public function init()
    {
        return true;
    }

    public function destruct()
    {
        return true;
    }

    /**
     * 监听请求
     * @throws \Exception
     */
    public function listen()
    {
        // 无运行时间限制,驻留
        set_time_limit(0);

        // 创建一个Socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new \Exception("can't create socket!" . PHP_EOL . socket_strerror(socket_last_error()));
        }

        // 绑定监听端口
        if (socket_bind($socket, '10.131.171.178', 8001) === false) {
            throw new \Exception("socket_bind() failed :reason:" . socket_strerror(socket_last_error($socket)) . PHP_EOL);
        }

        // 最多允许多少个并发连接
        if (socket_listen($socket, 512) === false) {
            throw new \Exception("socket_bind() failed :reason:" . socket_strerror(socket_last_error($socket)) . PHP_EOL);
        }

        ob_end_flush();
        echo 'running...';

        while (true) {
            // 得到一个链接
            if (($sock = socket_accept($socket)) === false) {
                echo "socket_accepty() failed :reason:" . socket_strerror(socket_last_error($socket)) . PHP_EOL;
                break;
            }

            // 读取请求内容
            $request = self::getRequest($sock);

            $response = new SMiniResponse();
            self::dispatch($request, $response);

            socket_write($sock, $response->getBuffer());

            if (isDebug()) {
                socket_write($sock, Debug::end(''));
            }
            // 关闭连接
            socket_close($sock);
        }

        // 结束总的SOCKET
        socket_close($socket);
        throw new \Exception(PHP_EOL . "exit");
    }

    /**
     * 派发请求
     * @param MiniRequest $request
     * @param SMiniResponse $response
     * @return mixed
     */
    private function dispatch(MiniRequest $request, SMiniResponse $response)
    {
        Debug::clear();

        $request = $request->requests;
        list ($m, $c, $a) = self::$mcaName;
        $controllerClassName = 'C' . ucfirst($request[$c]);

        // 先在当前模块下查找
        $file = '../../program/module/' . $request[$m] . '/controller/' . $request[$c] . '.controller.php';

        // 如果有,使用当前模块的控制器
        require_once($file);

        $class = new $controllerClassName();

        // 动作名称
        $actionName = $request[$a];
        return $class->$actionName($request, $response);
    }

    /**
     * 从SOCKET中读取HTTP请求的参数
     * @param resource $sock
     * @return  mixed
     * @throws \Exception
     */
    private function getRequest($sock)
    {
        $content = self::getRequestContent($sock);
        if ($content === false) {
            return false;
        }

        $lines = explode("\r\n", $content);

        $line = array_shift($lines);
        list (, $request, ) = self::getRequestMethod($line);

        while (true) {
            $line = array_shift($lines);
            if (!$line)
                break;
            $headers[] = explode(':', $line);
        }

        $lines = explode('&', $lines[0]);

        $posts = [];
        while (true) {
            $line = array_shift($lines);
            if (!$line)
                break;
            list ($k, $v) = explode('=', $line);
            $posts[$k] = $v;
        }
        $request->posts = new \ArrayObject($posts, \ArrayObject::ARRAY_AS_PROPS);
        $request->requests = new \ArrayObject(array_merge((array)$request->gets, (array)$request->posts));
        return $request;
    }

    /**
     * @param $line
     * @return array
     * @throws \Exception
     */
    private function getRequestMethod($line)
    {
        $parts = explode(' ', $line);
        if (count($parts) != 3) {
            throw new \Exception('error http method line:' . $line);
        }
        $method = strtoupper($parts[0]);
        if (!in_array($method, ['GET', 'POST'])) {
            throw new \Exception('not support method:' . $method);
        }
        return [$method, new MiniRequest($parts[1]), $parts[2]];
    }

    /**
     * 从SOCKET中读取全部请求数据
     *
     * @param resource $sock
     * @return boolean|string
     */
    private function getRequestContent($sock)
    {

        // 读取8K的内容
        $result = socket_read($sock, self::$maxRequest + 100);

        // 读取失败
        if ($result === false) {
            return false;
        }

        // 读取结束
        if (!$result) {
            return '';
        }

        // 如果请求长度太长,返回失败
        if (strlen($result) > self::$maxRequest) {
            return false;
        }

        return $result;
    }

    /*
*  函数:     parse_http
*  描述:     解析http协议
*/
    function parse_http($http)
    {
        // 初始化
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();
        $GLOBALS['HTTP_RAW_POST_DATA'] = '';
        // 需要设置的变量名
        $_SERVER = array(
            'QUERY_STRING' => '',
            'REQUEST_METHOD' => '',
            'REQUEST_URI' => '',
            'SERVER_PROTOCOL' => '',
            'SERVER_SOFTWARE' => '',
            'SERVER_NAME' => '',
            'HTTP_HOST' => '',
            'HTTP_USER_AGENT' => '',
            'HTTP_ACCEPT' => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE' => '',
            'HTTP_CONNECTION' => '',
            'REMOTE_ADDR' => '',
            'REMOTE_PORT' => '0',
        );

        // 将header分割成数组
        list($http_header, $http_body) = explode("\r\n\r\n", $http, 2);
        $header_data = explode("\r\n", $http_header);

        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ', $header_data[0]);

        unset($header_data[0]);
        foreach ($header_data as $content) {
            // \r\n\r\n
            if (empty($content)) {
                continue;
            }
            list($key, $value) = explode(':', $content, 2);
            $key = strtolower($key);
            $value = trim($value);
            switch ($key) {
                // HTTP_HOST
                case 'host':
                    $_SERVER['HTTP_HOST'] = $value;
                    $tmp = explode(':', $value);
                    $_SERVER['SERVER_NAME'] = $tmp[0];
                    if (isset($tmp[1])) {
                        $_SERVER['SERVER_PORT'] = $tmp[1];
                    }
                    break;
                // cookie
                case 'cookie':
                    $_SERVER['HTTP_COOKIE'] = $value;
                    parse_str(str_replace('; ', '&', $_SERVER['HTTP_COOKIE']), $_COOKIE);
                    break;
                // user-agent
                case 'user-agent':
                    $_SERVER['HTTP_USER_AGENT'] = $value;
                    break;
                // accept
                case 'accept':
                    $_SERVER['HTTP_ACCEPT'] = $value;
                    break;
                // accept-language
                case 'accept-language':
                    $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $value;
                    break;
                // accept-encoding
                case 'accept-encoding':
                    $_SERVER['HTTP_ACCEPT_ENCODING'] = $value;
                    break;
                // connection
                case 'connection':
                    $_SERVER['HTTP_CONNECTION'] = $value;
                    break;
                case 'referer':
                    $_SERVER['HTTP_REFERER'] = $value;
                    break;
                case 'if-modified-since':
                    $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $value;
                    break;
                case 'if-none-match':
                    $_SERVER['HTTP_IF_NONE_MATCH'] = $value;
                    break;
                case 'content-type':
                    if (!preg_match('/boundary="?(\S+)"?/', $value, $match)) {
                        $_SERVER['CONTENT_TYPE'] = $value;
                    } else {
                        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data';
                        $http_post_boundary = '--' . $match[1];
                    }
                    break;
            }
        }

        // 需要解析$_POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'multipart/form-data') {
                self:: parse_upload_files($http_body, $http_post_boundary);
            } else {
                parse_str($http_body, $_POST);
                // $GLOBALS['HTTP_RAW_POST_DATA']
                $GLOBALS['HTTP_RAW_POST_DATA'] = $http_body;
            }
        }

        // QUERY_STRING
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($_SERVER['QUERY_STRING']) {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        } else {
            $_SERVER['QUERY_STRING'] = '';
        }

        // REQUEST
        $_REQUEST = array_merge($_GET, $_POST);

        return array('get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'server' => $_SERVER, 'files' => $_FILES);
    }

    /*
    *  函数:     parse_upload_files
    *  描述:     解析上传的文件
    */
    function parse_upload_files($http_body, $http_post_boundary)
    {
        $http_body = substr($http_body, 0, strlen($http_body) - (strlen($http_post_boundary) + 4));
        $boundary_data_array = explode($http_post_boundary . "\r\n", $http_body);
        if ($boundary_data_array[0] === '') {
            unset($boundary_data_array[0]);
        }
        foreach ($boundary_data_array as $boundary_data_buffer) {
            list($boundary_header_buffer, $boundary_value) = explode("\r\n\r\n", $boundary_data_buffer, 2);
            // 去掉末尾\r\n
            $boundary_value = substr($boundary_value, 0, -2);
            foreach (explode("\r\n", $boundary_header_buffer) as $item) {
                list($header_key, $header_value) = explode(": ", $item);
                $header_key = strtolower($header_key);
                switch ($header_key) {
                    case "content-disposition":
                        // 是文件
                        if (preg_match('/name=".*?"; filename="(.*?)"$/', $header_value, $match)) {
                            $_FILES[] = array(
                                'file_name' => $match[1],
                                'file_data' => $boundary_value,
                                'file_size' => strlen($boundary_value),
                            );
                            continue;
                        } // 是post field
                        else {
                            // 收集post
                            if (preg_match('/name="(.*?)"$/', $header_value, $match)) {
                                $_POST[$match[1]] = $boundary_value;
                            }
                        }
                        break;
                }
            }
        }
    }
}