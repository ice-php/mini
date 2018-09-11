<?php
declare(strict_types=1);

namespace icePHP;
class MiniRequest
{

    public $gets, $posts, $files, $requests;

    public function __construct($url)
    {
        $parts = explode('?', $url);
        $gets = [];
        if ($parts[0]) {
            list ($gets, ) = self::parseMVC($parts[0]);
        }
        if (isset($parts[1])) {
            parse_str($parts[1], $gets2);
            $gets = array_merge($gets, $gets2);
        }
        // 所有GET参数
        $this->gets = new \ArrayObject($gets);
    }

    private function parseMVC($path)
    {
        // 系统配置的路径根,通常是 /
        $path_root = config('system', 'host');

        // 从Path中去除路径根, home/index
        if ($path_root == substr($path, 0, strlen($path_root))) {
            $path = substr($path, strlen($path_root));
        }

        // 去除首尾的斜线
        $path = trim($path, '/');

        // 如果此地址被配置为忽略解析,则直接包含此文件.
        if (Router::ignore($path)) {
            return require_once(config('system', 'dir_public') . $path);
        }

        // 路由解析,解析结果会存储到$_REQUEST,$_GET中.
        $_GET = $_REQUEST = [];
        Router::decode($path);
        return [$_GET, $_REQUEST];
    }
}