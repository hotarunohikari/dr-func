<?php

/**
 * hotarunohikari
 * 辅助工具函数
 */

if (!function_exists('get_cfg')) {
    /**
     * 从文件中加载配置项
     * 位于common的config目录下
     *
     * @param $file 文件名称
     * @param $ukey 配置项名称
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
     * @return mixed|null
     */
    function sss($key) {
        $in    = input($key);
        $deny  = ['/', '\\', ';', '<', '>', '\'', '\"', '%', '(', ')', '&', '+', '=', '||', '&quot;', '&apos;', '&amp;', '&lt;', '&gt;'];
        $lower = strtolower($in);
        foreach ($deny as $chr) {
            if (strpos($lower, $chr) > -1) {
                return null;
            }
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