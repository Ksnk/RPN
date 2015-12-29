<?php
/**
 * helper class to check template modification time
 * <%=point('hat','jscomment','
 *
 *
 *
 *
 *'); %>
 */

/**
 * helper function to check if value is empty
 */
class template_compiler
{

    static $filename = '';

    static private $opt = array(
        'templates_dir' => 'templates/',
        'TEMPLATE_EXTENSION' => 'twig'
    );

    static public function options($options = '', $val = null, $default = '')
    {
        if (is_array($options))
            self::$opt = array_merge(self::$opt, $options);
        else if (!is_null($val))
            self::$opt[$options] = $val;
        else if (isset(self::$opt[$options]))
            return self::$opt[$options];

        return $default;
    }

    /**
     * функция компиляции текста. Результатом будет
     * текст функции, для вставки в шаблон
     * @param file $tpl
     * @param string $name
     * @return mixed|null|string
     */
    static function compile_tpl($tpl, $name = 'compiler')
    {
        static $calc;
        if (empty($calc)) {
            $calc = new twig2php_class();
        }
        //compile it;
        $fp = false;
        try {
            $fp = fopen($tpl, "r");
            $calc->filename = $tpl;
            $calc->handler = $fp;
            //   set_error_handler(function ($c, $m /*, $f, $l*/) {
            //      throw new Exception($m, $c);
            //  }, E_ALL & ~E_NOTICE);
            $result = '' . $calc->ev(array('tplcalc', 'compiler', $name));
            fclose($fp);
        } catch (Exception $e) {
            echo $e->getMessage();
            fclose($fp);
            $result = null;
        }
        restore_error_handler();
        //execute it
        return $result;

    }

    /**
     * проверка даты изменения шаблона-образца
     */
    static function checktpl($options = '')
    {
        static $include_done;

        if (defined('TEMPLATE_PATH')) {
            self::options('TEMPLATE_PATH', TEMPLATE_PATH);
            self::options('PHP_PATH', TEMPLATE_PATH);
        }
        if (!empty($options))
            self::options($options);

        $ext = self::options('TEMPLATE_EXTENSION', null, 'twig');

        if (!class_exists('tpl_base'))
            include_once(self::options('templates_dir') . 'tpl_base.php');
//$time = microtime(true);
        $templates = glob(self::options('TEMPLATE_PATH') . DIRECTORY_SEPARATOR . '*.' . $ext);
        //print_r('xxx'.$templates);echo " !";
        $xtime = filemtime(__FILE__);
        $include_dir = dirname(__FILE__);

        if (!empty($templates)) {
            foreach ($templates as $v) {
                $name = basename($v, "." . $ext);
                $phpn = self::options('PHP_PATH') . DIRECTORY_SEPARATOR . 'tpl_' . $name . '.php';
                //echo($phpn.' '.$v);
                if (!file_exists($phpn)
                    ||
                    (max($xtime, filemtime($v)) > filemtime($phpn))
                ) {
                    //php_compiler::$filename = $v;
                    $x = self::compile_tpl($v, $name);
                    if (!!$x)
                        file_put_contents($phpn, $x);
                }
            }
        }
        // $time = microtime(true) - $time; echo $time.' sec spent';
    }

}

