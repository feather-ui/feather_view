<?php
require dirname(__FILE__) . '/Feather_View_Plugin.class.php';

class Feather_View{
    //默认后缀
    const DEFAULT_SUFFIX = '.tpl';

    //模版目录，可为数组
    public $template_dir = '';
    //插件目录，不可为数组
    public $plugins_dir = '';
    public $suffix = self::DEFAULT_SUFFIX;

    protected $data = array();
    protected $plugins = array();

    //设置值
    public function set($name, $value = ''){
        if(is_array($name)){
            foreach($name as $key => $value){
                $this->data[$key] = $value;
            }
        }else{
            $this->data[$name] = $value;
        }
    }

    //获取值
    public function get($name = null){
        return $name ? isset($this->data[$name]) ? $this->data[$name] : '' : $this->data;
    }

    public function __set($name, $value = ''){
        $this->set($name, $value);
    }

    public function __get($name){
        return $this->get($name);
    }

    //执行模版返回
    public function fetch($path, $data = null, $call_plugins = true){
        if(!self::checkHasSuffix($path)){
            $path = $path . $this->suffix;
        }

        $content = $this->loadFile($path);

        //if need to call plugins, call!
        if($call_plugins){
            $content = $this->callPlugins($path, $content);
        }

        return $this->evalContent($data ? $data : $this->get(), $content);
    }

    //显示模版
    public function display($path, $charset = 'utf-8', $type = 'text/html'){
        if(!headers_sent()){
            header("Content-type: {$type}; charset={$charset}");
        }

        echo $this->fetch($path);
    }

    //调用component
    public function load($path, $data = null){
        echo $this->fetch("{$path}", $data, false);
    }

    //加载某一个文件内容
    protected function loadFile($path){
        foreach((array)$this->template_dir as $dir){
            $_path = $dir . '/' . $path;

            if(($content = @file_get_contents($_path)) !== false){
                break;
            }
        }

        //如果content获取不到，则直接获取path，path可为绝对路径
        if($content === false && ($content = @file_get_contents($path)) === false){
            throw new Exception($path . ' is not exists!');
        }

        return $content;
    }

    //注册一个插件
    public function registerPlugin($name, $opt = array()){
        $this->plugins[] = array($name, $opt);
    }

    //调用插件
    protected function callPlugins($path, $content){
        foreach($this->plugins as $plugin){
            if(is_array($plugin)){
                $classname = __CLASS__ . '_Plugin_' . preg_replace_callback('/(?:^|_)\w/', 'self::toUpperCase', $plugin[0]);
                
                if(!class_exists($classname)){
                    require $this->plugins_dir . '/' . strtolower($classname) . '.php';
                }

                $obj = new $classname($plugin[1]);
            }else{
                $obj = $plugin;
            }

            $content = $obj->exec($path, $content, $this);
        }

        return $content;
    }

    //evaluate content
    protected function evalContent($data489bc39ff0, $content489bc39ff0){
        ob_start();
        //extract data
        extract($data489bc39ff0);
        //evaluate code
        eval("?> {$content489bc39ff0}");
        //return ob content
        $content489bc39ff0 = ob_get_clean();
        //if can flush, flush!
        ob_get_level() > 0 && ob_end_flush();
        //return
        return $content489bc39ff0;
    }

    protected static function checkHasSuffix($str){
        return !!preg_match('/\.[^\.]+$/', $str);
    }

    protected static function toUpperCase($match){
        return strtoupper($match[0]);
    }
}