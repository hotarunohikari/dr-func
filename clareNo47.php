<?php

/**
 * hotarunohikari
 * just named for lucky , 38726239
 *
 * 辅助工具函数
 * 将此文件放置于application目录下,在common.php文件中include_once即可
 */

use think\exception\ValidateException;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('APP_PATH') or define('APP_PATH', dirname(__FILE__) . DS);
defined('DROK') or define('DROK', 1);
defined('DRFAIL') or define('DRFAIL', 0);

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
    function get_cfg($file, $ukey, $expire = 180) {
        // 缓存名称
        $cacheName = $ukey ? 'cfg_' . $ukey : 'cfg_' . $file;
        // 优先读缓存
        $cfgValue = cache($cacheName, '', $expire);
        if ($cfgValue) {
            return $cfgValue;
        }
        // 其次读TP配置,当ukey为空时此处拦截
        $cfgValue = config($ukey, null);
        if ($cfgValue) {
            cache($cacheName, $cfgValue, $expire);
            return $cfgValue;
        }
        // 自定义配置,位于common的config文件夹下
        $file   = strpos($file, '.') ? $file : $file . '.php';
        $cfgArr = \think\Config::load(APP_PATH . 'common/config/' . $file);
        // 二级配置
        if (strpos($ukey, '.')) {
            $ukey    = explode('.', $ukey, 2);
            $ukey[0] = strtolower($ukey[0]);
            $ukey[1] = strtolower($ukey[1]);
            if (isset($cfgArr[$ukey[0]][$ukey[1]])) {
                cache($cacheName, $cfgArr[$ukey[0]][$ukey[1]], $expire);
                return $cfgArr[$ukey[0]][$ukey[1]];
            }
        }
        // 一级配置
        $ukey = strtolower($ukey);
        if (isset($cfgArr[$ukey])) {
            cache($cacheName, $cfgArr[$ukey], $expire);
            return $cfgArr[$ukey];
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

if (!function_exists('drUpload')) {
    function drUpload($cfg = [], $file = null) {
        $dir       = str_replace('\\', '/', ROOT_PATH . 'public/uploads');
        $save_path = '/public/uploads/';
        $cfg       = array_merge(['size' => 1024 * 1024 * 5, 'ext' => 'jpg,png,gif,bmp,heic'], $cfg);
        $files     = $file ? request()->file($file) : request()->file();
        $paths     = [];
        if ($files) {
            foreach ($files as $file) {
                $info = $file->validate($cfg)->move($dir);
                if ($info) {
                    $paths[] = str_replace('\\', '/', $save_path . $info->getSaveName());
                } else {
                    return false;
                }
            }
            return implode(',', $paths);
        }
        return false;
    }
}

if (!function_exists('apiPack')) {
    /**
     * API通用返回信息打包
     * @param array $data 数据
     * @param int $code 状态码
     * @param null $msg 通用信息
     * @return array
     */
    function apiPack($data = [], $code = DROK, $msg = null) {
        return [
            'code' => $code,
            'msg'  => $msg ?? ($code == DROK ? 'success' : 'fail'),
            'data' => $data
        ];
    }
}

if (!function_exists('tooFast')) {
    /**
     * 基于缓存的判定,判断执行间隔是否太快
     * @param string $ukey 确保每个会员全局唯一
     * @param int $second 间隔(秒)
     * @return bool
     */
    function tooFast($ukey, $second) {
        $lastOperatorTime = cache($ukey);
        if ($lastOperatorTime && time() - $lastOperatorTime < $second) {
            return true;
        }
        cache($ukey, time());
        return false;
    }
}

if (!function_exists('makeTree')) {
    /**
     * 构建树形结构
     * @param array $items 数据集
     * @param string $id 主键
     * @param string $pid 父ID
     * @param string $son
     * @return array
     */
    function makeTree($items, $id = 'id', $pid = 'pid', $son = 'son') {
        $tree   = [];
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

if (!function_exists('aaa')) {
    /**
     *  批量打印变量
     */
    function aaa() {
        $args = func_get_args();
        for ($i = 0, $len = count($args); $i < $len; $i++) {
            dump($args[$i]);
        }
    }
}

if (!function_exists('sss')) {
    /**
     * Safe on xSS
     * 简易防xss,前台用此函数替代TP的input函数获取输入
     * @param $key
     * @param null $default
     * @param string $filter
     * @param int $strict 严格程度, 0 任意, 1 数字字母下划线中日韩文, 2 数字字母下划线中文, 3 数字字母下划线, 4 数字
     * @return mixed|null
     */
    function sss($key, $default = null, $filter = '', $strict = 1) {
        $in = input($key, $default, $filter);
        array_walk($in, function (&$val) use ($strict) {
            $val = _filterInput($val, $strict);
        });
        return $in;
    }
}

if (!function_exists('_filterInput')) {
    /**
     * 过滤输入内容,违规则返回null
     * @param $in
     * @param int $strict 严格程度, 0 任意, 1 数字字母下划线中日韩文, 2 数字字母下划线中文, 3 数字字母下划线, 4 数字
     * @return string|string[]|null
     */
    function _filterInput($in, $strict = 1) {
        $deny = ['/', '\\', ';', '<', '>', '\'', '\"', '%', '(', ')', '&', '+', '=', '||', '&quot;', '&apos;', '&amp;', '&lt;', '&gt;'];
        foreach ($deny as $chr) {
            if (strpos($in, $chr) > -1) {
                return null;
            }
        }
        $patMap = [
            0 => '/[\s\S]/',
            1 => '/^[\x{2E80}-\x{9FFF}0-9a-zA-Z_]+$/u',
            2 => '/^[\x{4e00}-\x{9fa5}0-9a-zA-Z_]+$/u',
            3 => '/^[A-Za-z0-9]+$/',
            4 => '/^[0-9]+$/',
        ];
        $pat    = empty($patMap[$strict]) ? $patMap[3] : $patMap[$strict];
        if (!preg_match($pat, $in)) {
            return null;
        }
        $search  = array(" ", "　", "\n", "\r", "\t");
        $replace = array("", "", "", "", "");
        return str_replace($search, $replace, $in);
    }
}

if (!function_exists('zzz')) {
    /**
     * 批量打印变量,并在打印完后中断
     */
    function zzz() {
        $args = func_get_args();
        for ($i = 0, $len = count($args); $i < $len; $i++) {
            if ($i == $len - 1) {
                halt($args[$i]);
            } else {
                dump($args[$i]);
            }
        }
    }
}