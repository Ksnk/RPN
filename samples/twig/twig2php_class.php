<?php
/**
 * PHP twig-templates translator.
 * <%=point('hat','jscomment');
 *
 *
 *
 *
 * %>
 */

function pps(&$x, $default = '')
{
    if (empty($x)) return $default; else return $x;
}

class twig2php_class extends rpn_class
{
    const
        TYPE_SENTENSE = 101,
        TYPE_LITERAL = 102;

    const BUFLEN= 1024;

    var $filename = '',
        $handler='',
        /** состояние сборщика лексем getnext */
        $state=0,

        $BLOCK_START='{%',
        $BLOCK_END = '%}',
        $VARIABLE_START = '{{',
        $VARIABLE_END = '}}',
        $COMMENT_START = '{#',
        $COMMENT_END = '#}',
        $COMMENT_LINE = '##',
        $trim = true,
        $COMPRESS_START_BLOCK =true;

    /**
     * Масив для хранения упрощенных способов трансляции конструкции
     * @var operand[] array
     */
    var $pattern = array();

    /**
     * то же самое для унарных операций
     * @var array
     */
    var $unpattern = array(),
        /** var operand[] */
        $opensentence = array(); // комплект открытых тегов, для портирования

    function __construct($options = array())
    {
        // parent::__construct();
        $this->option(array(
            'flags' => 0 //**/+rpn_class::SHOW_DEBUG+rpn_class::SHOW_ERROR
                + rpn_class::ALLOW_STRINGS
                + rpn_class::ALLOW_REAL
                + rpn_class::ALLOW_ID,
            'evaluateTag' => array($this, '_calcOpr'),
            'executeOp' => array($this, '_calcOp'),

            // Enviroment setting
/*            'BLOCK_START' => '{%',
            'BLOCK_END' => '%}',
            'VARIABLE_START' => '{{',
            'VARIABLE_END' => '}}',
            'COMMENT_START' => '{#',
            'COMMENT_END' => '#}',
            'COMMENT_LINE' => '##',
            'trim' => true,
            'COMPRESS_START_BLOCK' => true*/
        ));
        $this->t_conv['E'] = self::TYPE_SENTENSE;
        // $this->error_msg['unexpected construction'] = 'something strange catched.';
        $this
            ->newOp2('- +', 4)
            ->newOp2('* / %', 5)
            ->newOp2('//', 5, 'ceil(%s/%s)')
            ->newOp2('**', 7, 'pow(%s,%s)')
            ->newOp2('..', 3, array($this, 'reprange'))
            ->newOp2('.', 12, array($this, 'function_point'))
            ->newOp2('|', 11, array($this, 'function_filter'))
            ->newOp2('is', 11, array($this, 'function_filter'), 11)
            ->newOp2('== != > >= < <=', 2, null, 'B**')
            ->newOp2('and', 1, '(%s) && (%s)', 'BBB')
            ->newOp2('or', 1, '(%s) || (%s)', 'BBB')
            ->newOp2('~', 2, '(%s).(%s)', 'SSS')
            ->newOp1('not', '!(%s)', 'BB')
            ->newOp2('& << >>', 3)
            // однопараметровые фильтры
            // ну очень служебные функции
            ->newFunc('defined', 'defined(%s)', 'SB')
            //->newOpR('loop', array($this, 'operand_loop'))
            ->newOpR('self', 'self', self::TYPE_XID)
            ->newOpR('_self', 'self', self::TYPE_XID)
            ->newOp1('now', 'date(%s)')
            // фильтры и тесты
            ->newFunc('e', 'htmlspecialchars(%s)', 'SS')
            ->newFunc('raw', '%s', 'SS')
            ->newFunc('escape', 'htmlspecialchars(%s)', 'SS')
            ->newFunc('replace', array($this, 'function_replace'), 'SSSS')
            ->newFunc('is_dir', 'is_dir(%s)', 'SI')
            ->newFunc('length', 'count(%s)', 'DI')
            ->newFunc('lipsum', '$this->func_lipsum(%s)')
            ->newFunc('min')
            ->newFunc('max')
            ->newFunc('trim')
            ->newFunc('join', '$this->filter_join(%s)')
            ->newFunc('explode', 'explode(%s)')
            ->newFunc('price', 'number_format(%s,0,"."," ")')
            ->newFunc('default', '$this->filter_default(%s)')
            ->newFunc('justifyleft', '$this->func_justifyL(%s)')
            ->newFunc('slice', '$this->func_slice(%s)')
            ->newFunc('range', '$this->func_range(%s)')
            ->newFunc('keys', '$this->func_keys(%s)')
            ->newFunc('callex', '$this->callex(%s)')
            ->newFunc('attribute', '$this->attr(%s)')
            ->newFunc('call', '$this->call($par,%s)')
            ->newFunc('translit', 'translit(%s)')
            ->newFunc('format', 'sprintf(%s)')
            ->newFunc('truncate', '$this->func_truncate(%s)')
            ->newFunc('tourl', '$this->func_2url(%s)')
            ->newFunc('date', '$this->func_date(%s)')
            ->newFunc('finnumb', '$this->func_finnumb(%s)')
            ->newFunc('right', '$this->func_rights(%s)')
            ->newFunc('russuf', '$this->func_russuf(%s)')
            ->newFunc('in_array', '$this->func_in_array(%s)')
            ->newFunc('is_array', '$this->func_is_array(%s)')
            // ->newFunc('parent', 'parent::_styles(%s)')
            ->newFunc('parent', array($this, 'function_parent'))
            ->newFunc('debug', 'ENGINE::debug(%s)')
            ->newOp1('_echo_', array($this, '_echo_'));
    }

    protected function execOp($op, $_1, $_2, $unop = false)
    {
        if($op->unop && method_exists($this,$op->val)){
            return call_user_func(array($this,$op->val),$_2);
        }
        if (!$op->unop && isset($this->pattern[$op->val])) {
            $op = $this->pattern[$op];
            $type = $op->type;
            return $this->oper(sprintf($op->val, $this->execTag($_1, $type{1}), $this->execTag($_2, $type{2})), $type{0});
        }
    }

    protected function execTag($op, $type = '*')
    {
        return $this->to($type, $op);
    }

    /**
     * универсальный сеттер унарных-бинарных операций
     * @param string $op - операция|список операций через пробел
     * @param integer $prio - приоритет операции
     * @param $array
     * @param $phpeq - PHP эквивалент - паттерн для spritf'а с параметрами
     * @param string $types
     * @return twig2php_class
     */
    private function &new_Op($op, $prio, &$array, $phpeq, $types = '*')
    {
        if (strpos($op, ' ') !== false) {
            foreach (explode(' ', $op) as $oop) {
                $this->new_Op($oop, $prio, $array, $phpeq, $types);
            }
            return $this;
        }
        $op = strtoupper($op);
        if ($prio < 10 || !isset($this->operation[$op])) $this->operation[$op] = $prio;
        if (is_string($phpeq)) {
            $array[$op] = $this->oper(str_replace('~~~', str_replace('%', '%%', $op), $phpeq));
        } else {
            $array[$op] = $this->oper($phpeq);
        }
        $array[$op]->types = $types . '****';
        return $this;
    }

    /**
     * определить функцию с параметрами
     * @param $op - имя операции
     * @param string $phpeq - PHP эквивалент - паттерн для sprintf'а с одним параметром
     * @param string $types
     * @return twig2php_class
     */
    function &newFunc($op, $phpeq = '~~~(%s)', $types = '*')
    {
        $this->operation[$op] = $this->oper(str_replace('~~~', $op, $phpeq));
        return $this;
        //return $this->new_Op($op,10,&$this->func,pps($phpeq,'~~~(%s)'),$types);
    }

    /**
     * определить операнд
     * @param $op - имя операнда
     * @param $phpeq - PHP эквивалент
     * @param int $type
     * @return twig2php_class
     */
    function &newOpr($op, $phpeq = null, $type = self::TYPE_OPERAND)
    {
        if (strpos($op, ' ') !== false) {
            foreach (explode(' ', $op) as $oop)
                $this->newOpr($oop, $phpeq, $type);
            return $this;
        }
        if (is_object($phpeq))
            $this->ids[$op] = $phpeq;
        else
            $this->ids[$op] = $this->oper($phpeq, $type);
        return $this;
    }

    /**
     * определить унарную операциию
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для sprintf'а с одним параметром
     * @param string $types
     * @return twig2php_class
     */
    function &newOp1($op, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, 10, $this->unpattern, pps($phpeq, '~~~(%s)'), $types);
    }

    /**
     * определить бинарную операциию
     * @param $op - имя операции
     * @param int $prio - приоритет операции
     * @param $phpeq - PHP эквивалент - паттерн для spritf'а с двумя параметром
     * @param string $types
     * @return twig2php_class
     */
    function &newOp2($op, $prio = 10, $phpeq = null, $types = '*')
    {
        return $this->new_Op($op, $prio, $this->pattern, !is_null($phpeq) ? $phpeq : '(%s)~~~(%s)', $types);
    }
    /*
        function error($msgId, $lex = null)
        {
            $mess = pps($this->error_msg[$msgId], $msgId);
            if (is_null($lex)) {
                $lex = $this->op;
            }
            if (!is_null($lex)) {
                // count a string
                $lexpos = 0;
                $line = 0;
                foreach ($this->lines as $k => $v) {
                    if ($k >= $lex->pos) break;
                    $lexpos = $k;
                    $line = $v;
                }

                $mess .= sprintf("\n" . 'file: %s<br>line:%s, pos:%s lex:"%s"'
                    , self::$filename
                    , $line + 1, pps($lex->pos, -1) - $lexpos, pps($lex->val, -1));
            }
            throw new Exception($mess);
        }
    */

    /**
     * Создание нового операнда. Для возможности переопределения класса операнда
     * Единственное место, где встречается слово operand - имя класса
     */
    function oper($value, $type = self::TYPE_NONE, $pos = 0)
    {
        return new operand($value, $type, $pos);
    }


    function getnext(){
        static $reg0='',$xtag=false;

        if($this->has_back) {
            $this->has_back=false;
            return $this->currenttag;
        }

        if($this->state==0){
            // выедаем конструкты вне twig'а
            if(empty($reg0)){
                $reg0 = "~(.*?)(";
                foreach (array('COMMENT_LINE', 'VARIABLE_START', 'COMMENT_START','BLOCK_START') as $r)
                    $reg0 .= preg_quote($this->$r, '#~') . '|';
                $reg0 .= '$)(\-?)~si';
            }

            $this->getcode();
            while ($this->code && preg_match($reg0, $this->code, $m, 0, $this->start)) {
                // найти начало следующего тега шаблонизатора
                $l=strlen($m[1]);
                if( $m[1]{$l-1}>"\xC0" || $m[1]{$l-1}=="{" ) {
                    $l-=1;
                }
                $this->syntax_tree[] = $this->oper(substr($m[1],0,$l-1), self::TYPE_STRING, $this->start);
                $tag=$this->oper('_echo_',self::TYPE_OPERATION,$this->start);
                $tag->unop = 1;
                $this->syntax_tree[] = $tag;
                $this->start+=$l;

                if ( $m[2] == $this->COMMENT_LINE ) {
                    // читаем до следующего конца строки
                    $this->skiptill('/\r?\n/s');
                } else if ( $m[2] == $this->COMMENT_START ) {
                    $this->skiptill('/'.preg_quote($this->COMMENT_END).'/s');
                } else if ( $m[2] == $this->VARIABLE_START ) {
                    $this->skiptill('/'.preg_quote($this->COMMENT_END).'/s');
                }
                //    break; // что-то незаладилось в реге
                $this->getcode();
            }
            return $this->currenttag=$xtag;
        } else {
            return parent::getnext();
        }
    }

    function skiptill($s){
        do {
            $this->getcode();
            if(preg_match($s, $this->code, $mm,0, $this->start)){
                $this->start+=strlen($mm[0]);
                break;
            }
        } while($this->code!='');
    }

    /**
     * конвертирование операнда в то или иное состояние
     * @param array $types - массив с именами типов для конвертирвоания
     * @param operand $res - операнд
     * @return mixed|\operand
     * @see nat2php/parser::to()
     */
    function to($types, &$res)
    {
        //конвертер операнда в то или иное состояние
        if (!is_array($types)) $types = array($types);
        if (is_string($res)) {
            $res = $this->oper($res, self::TYPE_STRING);
        }
        if (!is_object($res)) return $res;

        foreach ($types as $type)
            switch ($type) {
                /*
                * служебные операции
                */
                case 'trimln': // удалить первый NewLine из строки
                    $res->val = preg_replace('/^[ \t]*\r?\n/', '', $res->val);
                    break;
                case 'triml':
                    $res->val = preg_replace("/^\s*/s", '', $res->val);
                    break;
                case 'value':
                    if ($res->type == self::TYPE_OBJECT) {
                        return call_user_func($res->handler, $res, '', 'value');
                    }
                    return $res->val;
                /*
                * операции внешнего уровня
                */
                case self::TYPE_SENTENSE:
                    if ($res->type == self::TYPE_SENTENSE) break;
                    $this->to('*', $res);
                    if ($res->val == "''") $res->val = '';
                    else {
                        $this->template('sentence', array('data' => $res->val));
                    }
                    $res->type = self::TYPE_SENTENSE;
                    break;
                /*
                * просто литерал
                */
                case self::TYPE_LITERAL:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                    } else {
                        $this->error('plain literal expected');
                    }
                    break;
                /*
                * преобразования
                */
                case 'I':
                case self::TYPE_XID:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                        if ($this->checkId($res->val)) {
                            $res->val = '$' . $res->val;
                            $res->type = self::TYPE_OPERAND;
                        } else {
                            $res->val = '$par[\'' . $res->val . '\']';
                            $res->type = self::TYPE_XID;
                        };
                    } elseif ($res->type == self::TYPE_SLICE) {
                        if (!empty($res->list)) {
                            $this->to('I', $res->list[0]);
                            $res->val = $res->list[0]->val;
                            $condition = sprintf('$this->func_bk(%s', $res->list[0]->val);

                            array_shift($res->list);
                            foreach ($res->list as $el) {
                                if ($el->type == self::TYPE_ID) {
                                    $el->type = self::TYPE_STRING; // вырезка через точку - это вырезка через индекс
                                }
                                $this->to('S', $el);
                                $condition .= sprintf(',%s', $el->val);
                            }
                            $res->val = $condition . ')';
                            unset($res->list);
                        }
                        $res->type = self::TYPE_XSTRING;
                    } elseif ($res->type == self::TYPE_LIST) {
                        $this->to(self::TYPE_XLIST, $res);
                    } /*elseif ($res->type!=self::TYPE_XID){
	    		$this->error('waiting for ID')	;
	    	}*/;
                    break;
                case self::TYPE_XLIST:
                    $this->to('L', $res);
                    $res->val = 'array(' . $res->val . ')';
                    $res->type = self::TYPE_XID;
                    break;

                case 'B':
                case self::TYPE_XBOOLEAN:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                        $this->to('I', $res);
                    } elseif ($res->type == self::TYPE_STRING) {
                        $this->to('S', $res);
                    }
                    if ($res->type == self::TYPE_XID) {
                        $res->val = '(isset(' . $res->val . ') && !empty(' . $res->val . '))';
                        break;
                    } elseif ($res->type == self::TYPE_XSTRING) {
                        $res->val = '!empty(' . $res->val . '))';
                        break;
                    }
                // продолжаем то, что ниже!!!!!!!!!
                case 'L':
                    if ($res->type == self::TYPE_LIST) {
                        $op = array();
                        for ($i = 0; $i < count($res->value['keys']); $i++) {
                            $x = '';
                            if (!empty($res->value['value'][$i])) {
                                $x .= $this->to('*', $res->value['value'][$i])->val . '=>';
                            }
                            $x .= $this->to('*', $res->value['keys'][$i])->val;
                            $op[] = $x;
                        }
                        $res->val = implode(',', $op);
                        $res->type = self::TYPE_XLIST;
                    }
                // break не нужен !!!
                case '*':
                case 'S':
                case 'D':
                case self::TYPE_OPERAND:
                case self::TYPE_STRING:
                    if ($res->type == self::TYPE_ID) $this->to('I', $res);
                    if ($res->type == self::TYPE_OBJECT) {
                        $res->val = call_user_func($res->handler, $res, '', 'value');
                        $res->type = self::TYPE_OPERAND;
                    }
                    if ($res->type == self::TYPE_SLICE) $this->to('I', $res);
                    if ($res->type == self::TYPE_LIST) {
                        $arr = array();
                        if (isset($res->value['keys'])) {
                            for ($i = 0; $i < count($res->value['keys']); $i++) {
                                $arr[] = $this->to('S', $res->value['keys'][$i])->val;
                            }
                        } else {
                            for ($i = 0; $i < count($res->value); $i++) {
                                $arr[] = $this->to('S', $res->value[$i])->val;
                            }
                        }
                        $res->val = implode(',', $arr);
                        $res->type = self::TYPE_XSTRING;
                    }
                    if ($res->type == self::TYPE_STRING || $res->type == self::TYPE_STRING1) {
                        $res->val = "'" . addcslashes($res->val, "'\\") . "'";
                        $res->type = self::TYPE_XSTRING;
                    }
                    if ($res->type == self::TYPE_XID) {
                        $res->val = '(isset(' . $res->val . ')?' . $res->val . ':"")';
                        $res->type = self::TYPE_XSTRING;
                    }
                    //

                    break;
            }
        return $res;
    }

    protected function getcode(){
        if($this->start>self::BUFLEN){
            $this->code=substr($this->code,$this->start);
            if($this->code===false) $this->code='';
            $this->xstart+=$this->start;
            $this->start=0;
        }
        if(strlen($this->code)<self::BUFLEN && !feof($this->handler)){
            $this->code.=fread($this->handler,self::BUFLEN);
            if(strlen($this->code)<2*self::BUFLEN && !feof($this->handler)){
                $this->code.=fread($this->handler,self::BUFLEN);
            }
        }
    }

    function _echo_($op)
    {
        return $this->to('S', $op);
    }

    /**
     * функция компиляции одного подшаблона.
     * + сборка на стеке операндов готовой конструкции
     * + лексический анализ
     * @param string $class
     * @return mixed|string
     */
    function tplcalc($class = 'compiler')
    {
        $tag = array('tag' => 'class', 'import' => array(), 'macro' => array(), 'name' => $class, 'data' => array());
        $this->opensentence[] = & $tag;

        $tagx = array('tag' => 'block', 'name' => ' ', 'operand' => count($this->operand), 'data' => array());

        $this->block_internal(array(), $tagx);


        array_pop($this->opensentence);
        // TODO: разобраться с правильным наследованием _
        // сейчас просто удаляем метод _ из отнаследованного шаблона
        if (!empty($tag['extends'])) {
            for ($x = 0; $x < count($tag['data']); $x++) {
                if (preg_match('/function\s+_\s*\(/i', $tag['data'][$x]))
                    break;
            }
            unset($tag['data'][$x]);
        }
        return $this->template('class', $tag);
    }


    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     * @return \operand
     */
    function function_replace($op1, $op2)
    {
        $op1->val = 'str_replace(' . $this->to('S', $op2->value['keys'][1])->val
            . ',' . $this->to('S', $op2->value['keys'][2])->val
            . ',' . $this->to('S', $op2->value['keys'][0])->val
            . ')';
        $op1->type = "TYPE_OPERAND";
        return $op1;
    }

    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     * @return \operand
     */
    function function_parent($op1, $op2)
    {
        $value = array();
        foreach ($op2->value['keys'] as &$v) {
            $value[] = $this->to('S', $v)->val;
        }
        array_unshift($value, '$par');

        $op1->val = 'parent::_' . $this->currentFunction . '(' . implode(',', $value) . ')';
        $op1->type = "TYPE_OPERAND";
        return $op1;
    }

    function utford($c)
    {
        if (ord($c{0}) > 0xc0) {
            $x = unpack('N', mb_convert_encoding($c, 'UCS-4BE', 'UTF-8'));
            return $x[1];
            /*           $x = 0;
          $i = 0;
          while (isset($c{$i})) {
              $x += $x * 256 + ord($c{$i++});
          }
          return $x; */
        } else
            return ord($c{0});
    }

    function utfchr($i)
    {
        if ($i < 256) return chr($i);
        /* $x = '';
       while ($i > 0) {
           $x .= chr($i % 256);//.$x;
           $i=$i>>8;
       } */
        return mb_convert_encoding('&#' . $i . ';', 'UTF-8', 'HTML-ENTITIES');

    }

    function reprange($op1, $op2)
    {
        if ($op1->type == self::TYPE_DIGIT && $op2->type == self::TYPE_DIGIT) {
            $i = $op2->val;
            $y = $op1->val;
            $step = $i > $y ? -1 : 1;
            for (; $i != $y; $i += $step) {
                $this->pushOp($this->oper($i, self::TYPE_DIGIT));
            }
            $this->pushOp($this->oper($i, self::TYPE_DIGIT));
            return false;
        } elseif (($op1->type == self::TYPE_STRING && $op2->type == self::TYPE_STRING) or ($op1->type == self::TYPE_STRING1 && $op2->type == self::TYPE_STRING1)) {
            $i = $this->utford($op2->val);
            $y = $this->utford($op1->val);
            $step = $i > $y ? -1 : 1;
            for (; $i != $y; $i += $step) {
                $this->pushOp($this->oper($this->utfchr($i), self::TYPE_STRING));
            }
            $this->pushOp($this->oper($this->utfchr($i), self::TYPE_STRING));
            return false;
        } else {
            return $this->oper('$this->func_reprange(%s,%s)', self::TYPE_LIST);
        }
    }

    protected

        $locals = array(), // стек идентификаторов с областью видимости
        $ids_low = 0; // нижняя граница области видимости

    public
        $currentFunction = '', // имя текущей функции block или macro
        //для корректной работы parent()
        /** @var string - скрипт для выполнения */
        $script,
        /** @var boolean - сохранять-несохранять */
        $storeparams;

    /**
     * Вызов функции op1 с параметрами op2
     * @param  operand $op1
     * @param  operand $op2
     * @return operand
     */
    function function_callstack($op1, $op2)
    {
        //вырезка из массива
        if ($op1->type == self::TYPE_OBJECT) {
            $op1 = call_user_func($op1->handler, $op1, $op2, 'call');
            if ($op1)
                $this->pushOp($op1);
        } elseif (isset($this->func[$op1->val])) {
            //$op2 схлоп
            $x = $this->func[$op1->val]->val;
            if (is_callable($x)) {
                $op = call_user_func($x, $op1, $op2);
                if ($op)
                    $this->pushOp($op);
            } elseif (is_string($x)) {
                $op1->val = sprintf($x, $this->to('S', $op2)->val);
                $op1->type = self::TYPE_OPERAND;
            } else
                $this->error('wtf3!!!');
        } else {
            //вызов макрокоманды
            if ($op2->type == self::TYPE_LIST) {
                // call macro
                $arr = array();
                $arrkeys = array();
                for ($i = 0, $max = count($op2->value['value']); $i < $max; $i++) {
                    if (is_null($op2->value['value'][$i])) {
                        $arr[] = $this->to('S', $op2->value['keys'][$i])->val;
                    } else {
                        $arrkeys[] = array(
                            'key' => $op2->value['value'][$i]->val,
                            'value' => $this->to('S', $op2->value['keys'][$i])->val
                        );
                    }
                }
                if ($op1->type == self::TYPE_SLICE) {
                    // вызов объектного макроса
                    $this->to('I', $op1->list[0]);
                    //$op1->val =$op1->list[0]->val;
                    $op1->val = $this->template('callmacroex', array('par1' => $op1->list[0]->val, 'mm' => $op1->list[1]->val, 'param' => $arr));
                    $op1->type = self::TYPE_OPERAND;
                } else {
                    $op1->val = $this->template('callmacro', array('name' => $op1, 'param' => $arr, 'parkeys' => $arrkeys));
                    $op1->type = self::TYPE_SENTENSE;
                }
                return $op1;
            } else
                $this->error('have no function ' . $op1);
        }
        return $op1;
    }

    function back(){
        $this->has_back=true;
    }

    /**
     * отработка тега block, блок верхнего уровня является "корневым" элементом
     * @param operand[]|null $tag_waitingfor
     * @param operand|null $tag
     */
    function block_internal($tag_waitingfor = array(), &$tag = null)
    {
        if (empty($tag))
            $tag = array('tag' => 'block', 'operand' => count($this->operand));
        $data = array();
        $this->pushop('(');
        do {
            if ($this->getNext() === false ) {
                $this->back();
                break;
            }
            if (!empty($tag_waitingfor) && in_array($this->currenttag->val, $tag_waitingfor)) {
                $this->back();
                break;
            }
            if ($this->currenttag->type == self::TYPE_COMMA && empty($this->currenttag->val)) {

            } elseif ($this->exec_tag($this->currenttag->val)) {
                $op = $this->popOp();
                if (!empty($op)) {
                    $op = $this->to('S', $op);
                    if ($op->type == self::TYPE_SENTENSE)
                        $data[] = array('data' => $op->val);
                    else {
                        $data[] = array('string' => '(' . $op->val . ')');
                    }
                }
                //              break;
            } else {
                $this->back(); // goto TYPE_ECHO
                $this->getExpression();
                /* if ($this->op->val != '') {
                   $this->error('unexpected construction2');
               } */
                if (!($op = $this->popOp())) break;
                $op = $this->to('S', $op);
                if ($op->type == self::TYPE_SENTENSE)
                    $data[] = array('data' => $op->val);
                else if ($op->type == self::TYPE_XSTRING) {
                    if (!empty($op->val))
                        $data[] = array('string' => $op->val);
                } else {
                    $data[] = array('string' => '(' . $op->val . ')');
                }
                //$this->getNext();
            }
        } while (true);
        $this->pushop(')'); // свернуть все операции

        /**
         * оптимизация данных для вывода блока в шаблоне
         */
        array_unshift($data, array('string' => "''"));
        array_push($data, array('string' => "''"));
        $tag['data'] = array();
        $laststring = false;
        $lastidx = -1;
        foreach ($data as $d) {
            if (empty($d['data'])) {
                if ($laststring) {
                    if ($d['string'] != '' && $d['string'] != "''") {
                        if (is_array($tag['data'][$lastidx]['string'])) {
                            array_push($tag['data'][$lastidx]['string'], $d['string']);
                        } else {
                            if ($tag['data'][$lastidx]['string'] == "''")
                                $tag['data'][$lastidx]['string'] = array(
                                    $d['string']
                                );
                            else
                                $tag['data'][$lastidx]['string'] = array(
                                    $tag['data'][$lastidx]['string'],
                                    $d['string']
                                );
                        }
                    }
                } else {
                    $laststring = true;
                    $tag['data'][] = $d;
                    $lastidx++;
                }
            } else {
                $laststring = false;
                $tag['data'][] = $d;
                $lastidx++;
            }
        }

        if (!empty($tag['name'])) {
            $t =& $this->opensent('class');
            $t['data'][] = $this->template('block', $tag);
            $this->pushOp($this->oper($this->template('callblock', $tag), self::TYPE_OPERAND));
        } else
            $this->pushOp($this->oper($this->template('block', $tag), self::TYPE_SENTENSE));
    }

    /**
     * функция проверяем комплект локальных переменных
     * @param $id
     * @return bool
     */
    function checkId($id)
    {
        for ($i = $this->ids_low; $i < count($this->locals); $i++) {
            if ($this->locals[$i] == $id)
                return true;
        }
        return false;
    }

    /**
     * первый проход компилятора - свертка лексем;
     * Здесь и только здесь определяется внешний вид оформления тегов шаблонизатора;
     * регулярные пляски тоже только здесь
     * @param $script
     */
    function makelex($script)
    {
        $this->script = $script;
        $this->operand = array();
        $this->operation = array();
        $this->lex = array();
        $this->curlex = 0;
        $types = array();
        $curptr = 0;

        // привязываем номер строки к позиции транслятора
        $this->scanNl($script);

        // забираем лексемную регулярку
        $reg = $this->get_reg($types);
        $reg0 = "~(.*?)(";
        foreach (array('COMMENT_LINE', 'VARIABLE_START', 'COMMENT_START') as $r)
            $reg0 .= preg_quote($this->options[$r], '#~') . '|';
        $reg0 .= preg_quote($this->options['BLOCK_START'], '#~') . '|$)(\-?)~si';

        $total = strlen($script);

        $triml = false;
        $strcns = '';
        // найти начало следующего тега шаблонизатора
        while ($curptr < $total && preg_match($reg0, $script, $m, 0, $curptr)) {
            if ($m[0] == '')
                break; // что-то незаладилось в реге
            $strcns .= $m[1];
            if (!empty($strcns)) {
                if ($m[3] == '-') {
                    $strcns = preg_replace('/\s+$/s', '', $strcns);
                } else if ($m[2] == $this->options['BLOCK_START'] && $this->isOption('COMPRESS_START_BLOCK')) {
                    $strcns = preg_replace('/(\s*\r?\n?|^)\s*$/', '', $strcns);
                }
            }

            if ($triml) {
                $strcns = preg_replace('/^\s+/s', '', $strcns);
                $triml = false;
            }

            if ($m[1] !== '') {
                if ($m[2] != $this->options['COMMENT_LINE'] && $m[2] != $this->options['COMMENT_START']) {
                    $this->lex[] = $this->oper('_echo_', self::TYPE_OPERATION, $curptr);
                    $this->lex[] = $this->oper($strcns, self::TYPE_STRING, $curptr);
                    $this->lex[] = $this->oper('', self::TYPE_COMMA, $curptr);
                    $strcns = '';
                } else {
                    $strcns = preg_replace('/\s\s+$/m', " ", $strcns);
                }
            }

            $curptr += strlen($m[0]);

            if ($m[2] == "") break; // нашли финальный кусок

            if ($m[2] == $this->options['COMMENT_LINE']) { // комментарий на всю линию
                if (preg_match('~(.*?)\r?\n~i', $script, $mm, 0, $curptr)) {
                    $curptr += strlen($mm[1]);
                    continue;
                }
            } elseif ($m[2] == $this->options['COMMENT_START']) { // комментарий? - ищем пару и продолжаем цирк
                //$rreg='~.*?'.preg_quote($this->options['COMMENT_END'],'#~').'~si';
                if (preg_match('~.*?' . preg_quote($this->options['COMMENT_END'], '#~') . '~si', $script, $m, 0, $curptr)) {
                    $curptr += strlen($m[0]);
                    continue;
                }
            } else {
                if ($m[2] != $this->options['BLOCK_START']) {
                    $this->lex[] = $this->oper('_echo_', self::TYPE_OPERATION, $curptr);
                    //   $this->lex[] = $this->oper('(', self::TYPE_COMMA, $curptr);
                }
            }

            // отрезаем следующую лексему шаблонизатора
            $first = true;
            while ($curptr < $total && preg_match($reg, $script, $m, 0, $curptr)) {
                $pos = $curptr;
                $curptr += strlen($m[0]);
                if (!empty($m[1])) {
                    $op = $this->oper(stripslashes($m[2]), self::TYPE_STRING, $pos);
                    if ($m[1] == "'")
                        $op->type = self::TYPE_STRING1;
                    elseif ($m[1] == "`")
                        $op->type = self::TYPE_STRING2;

                    $this->lex[] = $op;
                } else {
                    for ($x = count($types) - 1; $x > 2; $x--) {
                        if (isset($m[$x]) && $m[$x] != "") {
                            if ($types[$x] == self::TYPE_COMMA && strlen($m[$x]) > 1) {
                                if ($m[$x]{0} == '-') {
                                    $triml = true;
                                    $m[$x] = substr($m[$x], 1);
                                }
                                if ($m[$x] == $this->options['VARIABLE_END']) {
                                    // $this->lex[] = $this->oper(')', self::TYPE_COMMA, $curptr);
                                }
                                $this->lex[] = $this->oper('', self::TYPE_COMMA, $curptr);
                                break 2;
                            }
                            $op = $this->oper(strtolower($m[$x]), $types[$x], $pos);
                            $op->orig = $m[$x];
                            $this->lex[] = $op;

                            // разбираемся с тегом RAW
                            if ($first && $m[$x] == 'raw') {
                                // ищем закрывающий тег raw
                                if (!preg_match('~.*?'
                                    . preg_quote($this->options['BLOCK_END'], '#~')
                                    . '(.*?)'
                                    . preg_quote($this->options['BLOCK_START'], '#~')
                                    . '\s*endraw\s*'
                                    . preg_quote($this->options['BLOCK_END'], '#~')
                                    . '~si',
                                    $script, $m, 0, $curptr)
                                )
                                    $this->error('endraw missed');
                                $curptr += strlen($m[0]);
                                array_pop($this->lex);
                                array_pop($this->lex);
                                $this->lex[] = $this->oper($m[1], self::TYPE_STRING, $curptr);
                                break 2;
                            } else
                                break;
                        }
                    }
                }
                $first = false;
            }
        }
        $this->lex[] = $this->oper("\x1b", self::TYPE_EOF, $curptr);
    }

    function newId($op)
    {
        // установить ID как новый идентификатор
        if (is_string($op))
            $this->locals[] = $op;
        else
            $this->locals[] = $op->val;
        return $op;
    }

    function &opensent($sent)
    {
        for ($i = count($this->opensentence) - 1; $i >= 0; $i--) {
            if ($this->opensentence[$i]['tag'] == $sent)
                return $this->opensentence[$i];
        }
        return null;
    }

    function function_filter($op1, $op2)
    {
        $this->pushOp($op2);
        $this->pushOp($op1);
        $this->storeparams = 1;
        return false;
    }

    function function_point($op1, $op2)
    {
        //вырезка из массива
        if ($op1->type == self::TYPE_XID && $op1->val == 'self') {
            // игнорируем self как вредный  элемент
            $op2->type = self::TYPE_XID;
            return $op2;
        }
        if ($op1->type == self::TYPE_OBJECT) {
            // вызов.
            return call_user_func($op1->handler, $op1, $op2, 'attr');
        }
        return $this->function_scratch($op1, $op2);
    }

    /**
     * разрешить неизвестный ID.
     * @param operand $op
     * @return mixed|\operand
     */
    function &resolve_id(&$op)
    {
        return $this->pushOp($op);
    }

    /**
     * отрабoтка тега macros
     * @example
     * // функция - описание макрокоманды
     * function _macroname($namedpar, //noname section
     *         $par1=null,$par2=1,,) {
     *   if(!empty($namedpar)) export($namedpar);
     *   ...
     * }
     * @example
     * // вызов макрокоманды
     * if(!empty($this->macros[macroname]))
     * call_user_func($this->macros[macroname],$namedpar,$par1,$par2,...);
     *
     * неопределенные функции автоматически становятся макрами! Регистрировать не надо
     *
     * @example
     * // конструктор класса
     * if(!empty($this->macros[macroname]))
     * $this->macros[macroname]=array($this,'_'.macroname);
     *
     * @example
     * // импорт
     * $this->imported['TEMPLATENAME']=new TEMPLATENAME();
     * array_merge($this->macros,$this->imported['TEMPLATENAME']->macros)
     *
     */
    function tag_macro()
    {
        $tag = array('tag' => 'macros', 'operand' => count($this->operand), 'data' => array());
        $this->getNext(); // name of macros
        // зарегистрировать как функцию
        $tag['name'] = $this->to(self::TYPE_LITERAL, $this->op)->val;
        $this->currentFunction = $tag['name'];
        $this->getNext(); // name of macros
        if ($this->op->val != '(') {
            $this->error('expected macro parameters');
        }
        $par = $this->get_Parameters_list('=');
        $arr = array();
        for ($i = 0, $max = count($par['value']); $i < $max; $i++) {
            if (is_null($par['value'][$i])) {
                $arr[] = array('name' => $this->to(self::TYPE_LITERAL, $par['keys'][$i])->val);
            } else {
                $v = $this->to('S', $par['keys'][$i])->val;
                if (!$v) $v = '0 ';
                $arr[] = array(
                    'name' => $this->to(self::TYPE_LITERAL, $par['value'][$i])->val,
                    'value' => $v
                );
            }
        }
        // $this->op->param=$arr;
        $tag['param'] = $arr;
        $this->getNext();
        if ($this->op->val != ')')
            $this->error('expected )');
        //       $this->getNext();
        $id_count = count($this->locals);
        foreach ($tag['param'] as $v) {
            $this->newId($v['name']);
        }
        $this->block_internal(array('endmacro'), $tag);
        /*$op=*/
        $this->popOp();
        $tag['body'] = $this->template('block', $tag);
        array_splice($this->locals, $id_count);
        $this->getNext();
        if ($this->op->val != 'endmacro')
            $this->error('there is no endmacro tag');
        // добавляем в открытый класс определение нового метода
        $sent =& $this->opensent('class');
        if (!empty($sent)) {
            if (empty($sent['macro'])) {
                $sent['macro'] = array();
            }
            $sent['macro'][] = $tag['name'];
        }
    }

    /**
     * отрабoтка тега block
     */
    function tag_block()
    {
        $tag = array('tag' => 'block', 'operand' => count($this->operand), 'data' => array());
        $this->getExpression(); // получили имя идентификатора
        $tag['name'] = $this->popOp()->val;
        $this->currentFunction = $tag['name'];
        $this->getNext();
        $this->block_internal(array('endblock'), $tag);
        $this->getNext();
        if ($this->op->type != self::TYPE_COMMA)
            $this->getNext();
    }

    /**
     * отрабoтка тега for
     * @internal param $id
     */

    function tag_extends()
    {
        $this->getExpression();
        $op = $this->popOp();
        $sent =& $this->opensent('class');
        if (!empty($sent)) {
            $sent['extends'] = preg_replace('~\..*$~', '', basename($op->val));
        }
        // $this->getNext(); // съели символ, закрывающий тег
    }

    /**
     * отрабoтка тега if
     * @return
     */
    function tag_if()
    {
        // парсинг тега for
        // полная форма:
        // if EXPRESSION
        // elif EXPRESSION
        // elif EXPRESSION
        // else EXPRESSION
        // endif
        $tag = array('tag' => 'if', 'operand' => count($this->operand), 'data' => array());

        do {
            // сюда входим с уже полученым тегом if или elif
            $this->getExpression();
            $op = $this->popOp();
            $data = array(
                'if' => $this->to(array('B', 'value'), $op)
            );
            // $this->getNext(); // выдали таг
            $this->block_internal(array('elseif', 'elif', 'else', 'endif'));
            $op = $this->popOp();
            $data['then'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
            $tag['data'][] = $data;
            $this->getNext(); // выдали таг
            if ($this->op->val == 'endif')
                break;
            if ($this->op->val == 'else') {
                // $this->getNext();
                $this->block_internal(array('endif'));
                $op = $this->popOp();
                $data = array(
                    'if' => false,
                    'then' => $this->to(array(self::TYPE_SENTENSE, 'value'), $op)
                );
                $tag['data'][] = $data;
                $this->getNext(); // выдали таг
                break;
            }
        } while (true);
        // $this->getNext(); // съели символ, закрывающий тег
        $this->pushOp($this->oper($this->template('if', $tag), self::TYPE_SENTENSE));
        return;
    }

    function tag_import()
    {
        //$set =array('tag'=>'import','operand'=>count($this->operand));
        $this->getExpression(); // получили имя файла для импорта
        $op = $this->popOp();
        $t =& $this->opensent('class');
        $t['import'][] = basename($op->val, '.' . template_compiler::options('TEMPLATE_EXTENSION', 'jtpl'));
        //   $this->getNext();
        return false;
    }

    /**
     *
     *  тег SET
     *
     */
    function tag_set()
    {
        $set = array('tag' => 'set', 'operand' => count($this->operand));
        $this->getExpression(); // получили имя идентификатора
        $id = $this->newId($this->popOp());
        $set['id'] = $this->to(array('I', 'value'), $id);
        //$set['id'] = $this->to(array('I', 'value'), $set['id']);
        $this->getNext();
        if ($this->op->val != '=')
            $this->error('unexpected construction9');
        $this->getExpression();
        $set['res'] = $this->popOp();
        if ($set['res']->type == self::TYPE_LIST)
            $set['res'] = $this->to(self::TYPE_XLIST, $set['res'])->val;
        else
            $set['res'] = $this->to('*', $set['res'])->val;
        // $this->getNext();
        $this->pushOp($this->oper($this->template('set', $set), self::TYPE_SENTENSE));
        return;
    }


    /**
     * Встроенные в класс рендерер ...
     * @param string $idx - имя подшаблона "" - корневой подшаблон
     * @param array $par - данные для рендеринга
     * @param string $tpl_class - имя базового шаблона
     * @return mixed|string
     */
    function template($idx = null, $par = null, $tpl_class = 'compiler')
    {
        static $tpl_compiler;
        if (!is_null($tpl_class) || empty($tpl_compiler)) {
            $tpl_compiler = 'tpl_' . pps($tpl_class, 'compiler');
            if (!class_exists($tpl_compiler)) {
                // попытка включить файл
                include_once(template_compiler::options('templates_dir') . $tpl_compiler . '.php');
            }
            $tpl_compiler = new $tpl_compiler();
        }

        if (!is_null($par)) {
            $x =& $par;
            if (method_exists($tpl_compiler, '_' . $idx))
                return call_user_func(array($tpl_compiler, '_' . $idx), $x);
            else
                printf('have no template "%s:%s"', 'tpl_compiler', '_' . $idx);
        }
        return '';
    }

    /**
     * {% macro | for |
     * проверить, есть ли зарезервированные обработчики этого тега
     * Проверяем
     * - список классов-расширений
     * - список собственных методов
     * @param string $tag
     * @return bool
     */
    function exec_tag($tag)
    {
        $name = 'tag_' . $tag;
        if (class_exists($name, false)) {
            $tag = new $name();
            $tag->execute($this);
        } else if (method_exists($this, $name)) {
            call_user_func(array($this, $name));
        } else
            return false;

        return true;
    }

}