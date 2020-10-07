<?php


/**
 * hotarunohikari
 * before use u need
 * composer require hotarunohikari/dr-filter
 *
 * 辅助工具函数
 * 将此文件放置于application目录下,在common.php文件中include_once即可
 */

use think\exception\ValidateException;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('APP_PATH') or define('APP_PATH', dirname(__FILE__) . DS);
defined('DROK') or define('DROK', 1);
defined('DRFAIL') or define('DRFAIL', 0);

if (!function_exists('remember')) {
    /**
     * Notes: 数据记忆
     * @param $cacheName
     * @param mixed $data
     * @param int $expire
     * @param bool $flush 是否强制刷新
     * @return array|callable|mixed|object|\think\App
     */
    function remember($cacheName, $data = [], $expire = 180, $flush = false) {
        if (!$flush) {
            $cfgValue = cache($cacheName, '', $expire);
            if ($cfgValue) {
                return $cfgValue;
            }
        }
        if (is_callable($data)) {
            $result = call_user_func($data, $cacheName);
            cache($cacheName, $result, $expire);
            return $result;
        }
        cache($cacheName, $data, $expire);
        return $data;
    }
}

if (!function_exists('get_cfg')) {
    /**
     * 从文件中加载配置项
     * 位于common的config目录下
     *
     * @param string $file 文件名称
     * @param string $ukey 配置项名称
     * @param int $expire
     * @return array|mixed
     */
    function get_cfg($file, $ukey = null, $expire = 180) {
        // 缓存名称
        $cacheName = $ukey ? 'cfg_' . $ukey : 'cfg_' . $file;
        // 优先读缓存
        $cfgValue = cache($cacheName, '', $expire);
        if ($cfgValue) {
            return $cfgValue;
        }
        // 自定义配置,位于common的config文件夹下
        $file = strpos($file, '.') ? $file : $file . '.php';
        $cfgArr = include(APP_PATH . 'common/config/' . $file);
        // 读取全部文件
        if(!$ukey){
            cache($cacheName, $cfgArr, $expire);
            return $cfgArr;
        }
        // 二级配置
        if (strpos($ukey, '.')) {
            $ukey = explode('.', $ukey);
            $ukey[0] = strtolower($ukey[0]);
            $ukey[1] = strtolower($ukey[1]);
            if (isset($cfgArr[$ukey[0]][$ukey[1]])) {
                cache($cacheName, $cfgArr[$ukey[0]][$ukey[1]], $expire);
                return $cfgArr[$ukey[0]][$ukey[1]];
            }
        } else {
            // 一级配置
            $ukey = strtolower($ukey);
            if (isset($cfgArr[$ukey])) {
                cache($cacheName, $cfgArr[$ukey], $expire);
                return $cfgArr[$ukey];
            }
        }
        return [];
    }
};

if (!function_exists('db_cfg')) {
    /**
     * 从数据库中加载配置项
     * 根据关键字匹配目标值
     * @param string $table 表名
     * @param string $ukey 关键字的值
     * @param int $expire 缓存时间
     * @param string $kField 指示关键字的字段,默认为ukey
     * @param string $vField 指示目标值的字段,默认为val
     * @return mixed
     */
    function db_cfg($table, $ukey, $expire = 180, $kField = 'ukey', $vField = 'val') {
        // 缓存名称
        $cacheName = $ukey ? 'db_cfg_' . $table . '_' . $ukey : 'db_cfg_' . $table;
        // 优先读缓存
        $cfgValue = cache($cacheName, '', $expire);
        if ($cfgValue) {
            return $cfgValue;
        }
        // 读取单个字段 或 读取多行返回数组
        $cfgValue = $ukey ? db($table)->where($kField, $ukey)->value($vField) : db($table)->column($vField, $kField);
        if ($cfgValue) {
            cache($cacheName, $cfgValue, $expire);
            return $cfgValue;
        }
    }
}

if (!function_exists('line_cfg')) {
    /**
     * 多行配置项转数组,一行一个
     * 5
     * 4
     * 3
     * 21
     * 支持二级分隔转关联数组
     * 1|100
     * 2|200
     * 3|300
     * 4|400
     * @param string|array $raw 原始数据
     * @param null $delimiter 二级分隔符
     * @param bool $filterNull 过滤空值,默认保留空值
     * @return array|string
     */
    function line_cfg($raw, $delimiter = null, $filterNull = false) {
        if (empty($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $cfgArr = explode("\n", str_replace(' ', '', $raw));
            if (!empty($delimiter)) {
                $reArr = [];
                array_walk($cfgArr, function ($val) use ($delimiter, &$reArr) {
                    $arr = explode($delimiter, $val);
                    $reArr[$arr[0]] = $arr[1];
                });
                return $filterNull ? array_filter($reArr) : $reArr;
            }
            return $filterNull ? array_filter($cfgArr) : $cfgArr;
        }
        return $filterNull ? array_filter($raw) : $raw;
    }
}

if (!function_exists('vali')) {
    /**
     * 通用验证函数
     * @param string $validateKlass 验证器类名
     * @param array $data 参与验证的数据
     * @return bool
     */
    function vali($validateKlass, $data) {
        try {
            $validate = validate($validateKlass)->check($data);
        } catch (ValidateException $exception) {
            $judge = $exception->getMessage();
            exit(json_encode(apiPack([], DRFAIL, $judge), JSON_UNESCAPED_UNICODE));
        }
        return $validate;
    }
}

if (!function_exists('dr_upload')) {
    /**
     * 文件上传
     * @param array $cfg ['size'=>1024*1024*5,'ext'=>'jpg,png,gif,bmp,heic']
     * @param null $name
     * @param string $save_path
     * @return bool|string
     */
    function dr_upload($cfg = [], $name = null, $save_path = '/public/uploads/') {
        $dir = str_replace('\\', '/', ROOT_PATH . 'public/uploads');
        $savePath = $save_path ?? '/public/uploads/';
        $cfg = array_merge(['size' => 1024 * 1024 * 5, 'ext' => 'jpg,png,gif,bmp,heic'], $cfg);
        $files = $name ? request()->file($name) : request()->file();
        $paths = [];
        if ($files) {
            foreach ((array)$files as $file) {
                $info = $file->validate($cfg)->move($dir);
                if ($info) {
                    $paths[] = str_replace('\\', '/', $savePath . $info->getSaveName());
                } else {
                    return false;
                }
            }
            return implode(',', $paths);
        }
        return false;
    }
}

if (!function_exists('api_pack')) {
    /**
     * API通用返回信息打包
     * @param array $data 数据
     * @param int $code 状态码
     * @param null $msg 通用信息
     * @return array
     */
    function api_pack($data = [], $code = DROK, $msg = null) {
        return [
            'code' => $code,
            'msg'  => $msg ?? ($code == DROK ? 'success' : 'fail'),
            'data' => $data
        ];
    }
}
if (!function_exists('ok')) {
    /**
     * API通用返回信息打包-成功
     * @param array $data 数据
     * @param int $code 状态码
     * @param null $msg 通用信息
     * @return array
     */
    function ok($data = [], $code = DROK, $msg = null) {
        echo json_encode(api_pack($data, $code, $msg), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('no')) {
    /**
     * API通用返回信息打包-失败
     * @param string $msg 通用信息
     * @param int $code 状态码
     * @param array $data 数据
     * @return array
     */
    function no($msg = 'fail', $code = DRFAIL, $data = []) {
        echo json_encode(api_pack($data, $code, $msg), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('out')) {
    /**
     * API通用返回信息打包-输出
     * @param array $data 数据
     * @param int $code 状态码
     * @param null $msg 通用信息
     * @return array
     */
    function out($data = [], $msg = null) {
        return $data ? ok($data) : no($msg);
    }
}

if (!function_exists('too_fast')) {
    /**
     * 基于缓存的判定,判断执行间隔是否太快
     * @param string $ukey 确保每个会员全局唯一
     * @param int $second 间隔(秒)
     * @param null $tag
     * @return bool
     */
    function too_fast($ukey, $second = 5, $tag = null) {
        if (cache($ukey)) {
            return true;
        }
        cache($ukey, time(), $second, $tag);
        return false;
    }
}

if (!function_exists('make_tree')) {
    /**
     * 构建树形结构
     * @param array $items 数据集
     * @param string $id 主键
     * @param string $pid 父ID
     * @param string $son
     * @return array
     */
    function make_tree($items, $id = 'id', $pid = 'pid', $son = 'son') {
        $tree = [];
        $tmpMap = [];
        foreach ($items as $item) {
            $tmpMap[$item[$id]] = $item;
        }
        foreach ($items as $item) {
            if (isset($tmpMap[$item[$pid]])) {
                $tmpMap[$item[$pid]][$son][] = &$tmpMap[$item[$id]];
            } else {
                $tree[] = &$tmpMap[$item[$id]];
            }
        }
        unset($tmpMap);
        return $tree;
    }
}

if (!function_exists('aa')) {
    /**
     *  批量打印变量
     */
    function aa() {
        $args = func_get_args();
        for ($i = 0, $len = count($args); $i < $len; $i++) {
            dump($args[$i]);
        }
    }
}

/**
 * 控制台协助纠错函数
 * Nameless
 * 2020/5/27 15:26
 * @param string $var
 * @param null $tag
 * @param bool $output
 * @return bool
 */
if (!function_exists('ee')) {
    function ee($var = 'FLY', $tag = null, $output = true) {
        if ($output) {
            $endline  = '<br/>' . PHP_EOL;
            $debug    = debug_backtrace()[0];
            $file     = $debug['file'];
            $line     = $debug['line'];
            $assert   = $var ? '-TRUE' : 'FALSE';
            $position = $file . ' ' . $line;
            $tagStr   = $tag ? $tag : '--';
            switch ($var) {
                case is_object($var) || is_array($var):
                    $varStr = json_encode($var, JSON_UNESCAPED_UNICODE);
                    break;
                case is_numeric($var):
                    $varStr = $var ?: 0;
                    break;
                case is_string($var):
                    $varStr = $var ? $var : '';
                    break;
                default:
                    $varStr = '--';
                    break;
            }
            echo $assert
                . ' : '
                . $tagStr
                . '>'
                . $varStr
                . ' '
                . $position
                . ' @ '
                . date("H:i:s")
                . $endline;

        }
    }
}

if (!function_exists('ss')) {
    /**
     * Safe on xSS
     * 简易防xss,前台用此函数替代TP的input函数获取输入
     * @param string $key
     * @param null $default
     * @param string $filter
     * @param int $strict 严格程度, 0 任意, 1 数字字母下划线中日韩文, 2 数字字母下划线中文, 3 数字字母下划线, 4 数字
     * @return mixed|null
     */
    function ss($key = '', $default = null, $filter = '', $strict = 1) {
        $input = (array)input($key, $default, $filter);
        return (new \dr\filter\DrFilter($strict))->filter($input);
    }
}

if (!function_exists('zz')) {
    /**
     * 批量打印变量,并在打印完后中断
     */
    function zz() {
        $args = func_get_args();
        for ($i = 0, $len = count($args); $i < $len; $i++) {
            if ($i == $len - 1) {
                dump($args[$i]);
                exit;
            } else {
                dump($args[$i]);
            }
        }
    }
}

if (!function_exists('chunk')) {
    /**
     * 分块处理函数
     * $data = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
     * $re = chunk($data, 5, function ($item) {
     *      return $item + 100;
     * });
     * @param $data
     * @param $size
     * @param $callback
     * @return array
     * Nameless
     * 2020/6/2 16:50
     */
    function chunk($data, $size, $callback) {
        $res = [];
        array_map(function (&$sub_arr) use ($callback, &$res) {
            foreach ($sub_arr as $key => &$item) {
                $item = $callback($item, $key);
            }
            $res = array_merge($res, $sub_arr);
        }, array_chunk($data, $size));
        return $res;
    }
}

if (!function_exists('noxss')) {
    function noxss($val) {
        // remove all non-printable characters. CR(0a) and LF(0b) and TAB(9) are allowed
        // this prevents some character re-spacing such as <java\0script>
        // note that you have to handle splits with \n, \r, and \t later since they *are* allowed in some inputs
        $val = preg_replace('/([\x00-\x08,\x0b-\x0c,\x0e-\x19])/', '', $val);

        // straight replacements, the user should never need these since they're normal characters
        // this prevents like <IMG SRC=@avascript:alert('XSS')>
        $search = 'abcdefghijklmnopqrstuvwxyz';
        $search .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $search .= '1234567890!@#$%^&*()';
        $search .= '~`";:?+/={}[]-_|\'\\';
        for ($i = 0; $i < strlen($search); $i++) {
            // ;? matches the ;, which is optional
            // 0{0,7} matches any padded zeros, which are optional and go up to 8 chars

            // @ @ search for the hex values
            $val = preg_replace('/(&#[xX]0{0,8}' . dechex(ord($search[$i])) . ';?)/i', $search[$i], $val); // with a ;
            // @ @ 0{0,7} matches '0' zero to seven times
            $val = preg_replace('/(�{0,8}' . ord($search[$i]) . ';?)/', $search[$i], $val);                // with a ;
        }

        // now the only remaining whitespace attacks are \t, \n, and \r
        $ra1 = array('javascript', 'vbscript', 'expression', 'applet', 'meta', 'xml', 'blink', 'link', 'style', 'script', 'embed', 'object', 'iframe', 'frame', 'frameset', 'ilayer', 'layer', 'bgsound', 'title', 'base');
        $ra2 = array(
            'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend', 'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter', 'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange', 'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop', 'onsubmit', 'onunload'
        );
        $ra = array_merge($ra1, $ra2);

        $found = true; // keep replacing as long as the previous round replaced something
        while ($found == true) {
            $val_before = $val;
            for ($i = 0; $i < sizeof($ra); $i++) {
                $pattern = '/';
                for ($j = 0; $j < strlen($ra[$i]); $j++) {
                    if ($j > 0) {
                        $pattern .= '(';
                        $pattern .= '(&#[xX]0{0,8}([9ab]);)';
                        $pattern .= '|';
                        $pattern .= '|(�{0,8}([9|10|13]);)';
                        $pattern .= ')*';
                    }
                    $pattern .= $ra[$i][$j];
                }
                $pattern .= '/i';
                $replacement = substr($ra[$i], 0, 2) . '<x>' . substr($ra[$i], 2); // add in <> to nerf the tag
                $val = preg_replace($pattern, $replacement, $val);                 // filter out the hex tags
                if ($val_before == $val) {
                    // no replacements were made, so exit the loop
                    $found = false;
                }
            }
        }
        return $val;
    }
}

if (!function_exists('dcopy')) {
    /**
     * 深度复制目录和文件
     * @param $src 源
     * @param $dst 目标
     */
    function dcopy($src, $dst) {
        if (is_file($src)) {
            !is_dir(dirname($dst)) && mkdir(dirname($dst), 0777, true);
            copy($src, $dst);
        } else {
            !file_exists($dst) && mkdir($dst, 0777, true);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $srcf = $src . '/' . $file;
                    $dstf = $dst . '/' . $file;
                    dcopy($srcf, $dstf);
                }
            }
        }
    }
}