<?php

/**
 * PHP twig-templates translator.
 * ----------------------------------------------------------------------------
 * $Id: Templater engine v 3.0 (C) by Ksnk (sergekoriakin@gmail.com).
 *      based on Twig sintax,
 * ver: , Last build: 1601301941
 * GIT: origin	https://github.com/Ksnk/RPN.git (push)$
 * ----------------------------------------------------------------------------
 * License MIT - Serge Koriakin - 2015
 * ----------------------------------------------------------------------------
 */
class twig2php_class extends rpn_class
{
    const
        TYPE_SENTENSE = 101,
        TYPE_LITERAL = 102;

    const BUFLEN = 1024;

    var $filename = '',
        $handler = '',
        /** состояние сборщика лексем getnext */
        $state = 0,
        $lines = array(),

        $BLOCK_START = '{%',
        $BLOCK_END = '%}',
        $BLOCK_XEND = '-%}',
        $VARIABLE_START = '{{',
        $VARIABLE_END = '}}',
        $VARIABLE_XEND = '-}}',
        $COMMENT_START = '{#',
        $COMMENT_END = '#}',
        $COMMENT_XEND = '-#}', // todo: временно, пока не справлюсь с минусом в этом месте
        $COMMENT_LINE = '##',
        $trim = true,
        $COMPRESS_START_BLOCK = true;

    /**
     * Масcив для хранения упрощенных способов трансляции конструкции
     * @var operand[] array
     */
    var $pattern = array();

    /**
     * то же самое для унарных операций
     * @var array
     */
    var //$unpattern = array(),
        /** var operand[] */
        $opensentence = array(); // комплект открытых тегов, для портирования

    var $trim_state = 0;


    protected

        $locals = array(), // стек идентификаторов с областью видимости
        $ids_low = 0; // нижняя граница области видимости

    public
        $currentFunction = '', // имя текущей функции block или macro
        //для корректной работы parent()
        /** @var string - скрипт для выполнения */
        $script,
        $tpl_compiler = null;

    function __construct($options = array())
    {
        // parent::__construct();
        $opt = array(
            'flags' => 0 /+self::SHOW_DEBUG+self::SHOW_ERROR
                + self::ALLOW_STRINGS
                + self::ALLOW_REAL
                + self::ALLOW_ID
                + self::ALLOW_COMMA
                + self::ALLOW_DOTSTRATCH
            //      + self::THROW_EXCEPTION_ONERROR
            //           + self::CASE_SENCITIVE
        ,
            'reserved_words' => array('endif' => -2, 'elseif' => -2, 'elif' => -2, 'for' => -2, 'set' => -2, 'if' => -2),
            'evaluateTag' => array($this, '_calcOpr'),
            'executeOp' => array($this, '_calcOp'));

        $opt['reserved_words'][$this->BLOCK_END] = -2;
        $opt['reserved_words'][$this->BLOCK_XEND] = -2;
        $opt['reserved_words'][$this->VARIABLE_END] = -2;
        $opt['reserved_words'][$this->VARIABLE_XEND] = -2;
        $opt['reserved_words'][$this->COMMENT_START] = -2;
        $opt['reserved_words'][$this->COMMENT_LINE] = -2;
        // $opt['reserved_words']['_triml_']=0;

        $this->option($opt);
        $this->option($options);


        self::$char2class['E'] = self::TYPE_SENTENSE;
        // $this->error_msg['unexpected construction'] = 'something strange catched.';
        $this
            ->newOp2('- +', 4)
            ->newOp2('* / %', 5)
            ->newOp2('//', 5, 'ceil(%s/%s)')
            ->newOp2('**', 7, 'pow(%s,%s)')
            ->newOp2('..', 3, array($this, 'reprange'))
            ->newOp2('|', 11, array($this, 'function_filter'))
            ->newOp2('is', 11, array($this, 'function_filter'), 11)
            ->newOp2('== != > >= < <=', 2, null, 'B**')
            ->newOp2('and', 1, '(%s) && (%s)', 'BBB')
            ->newOp2('or', 1, '(%s) || (%s)', 'BBB')
            ->newOp2('~', 2, '(%s).(%s)', 'SSS')
            ->newOp1('not', '!(%s)', 'BB', 1)
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
            ->newFunc('number_format', 'number_format(%s)')
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
            ->newFunc('reg', '$this->func_reg(%s)')
            ->newFunc('russuf', '$this->func_russuf(%s)')
            ->newFunc('in_array', '$this->func_in_array(%s)')
            ->newFunc('is_array', '$this->func_is_array(%s)')
            // ->newFunc('parent', 'parent::_styles(%s)')
            ->newFunc('parent', array($this, 'function_parent'))
            ->newFunc('debug', 'ENGINE::debug(%s)')
            ->newOpr('loop', array($this, 'operand_loop'))// ->newOp1('_echo_', array($this, '_echo_'))
        ;
    }

    protected function execOp($op, $_1, $_2, $unop = false)
    {
        if ($op->unop && method_exists($this, $op->val)) {
            return call_user_func(array($this, $op->val), $_2, $op);
        }
        if (!$op->unop && method_exists($this, $op->val)) {
            return call_user_func(array($this, $op->val), $_1, $_2);
        }
        if (!$op->unop && isset($this->pattern[$op->val])) {
            $op = $this->pattern[(string)$op];
            if (is_array($op->val) && is_callable($op->val)) {
                return call_user_func($op->val, $_1, $_2);
            }

            $type = $op->types;
            return $this->oper(sprintf($op->val, $this->to($type{1}, $_1), $this->to($type{2}, $_2)), self::$char2class[$type{0}]);
        }
        if ($op->unop && isset($this->unop[$op->val])) {
            $op = $this->unop[$op->val];
            if (is_array($op->val) && is_callable($op->val)) {
                return call_user_func($op->val, $_2);
            }

            $type = $op->types;
            return $this->oper(sprintf($op->val, $this->to($type{1}, $_2)), self::$char2class[$type{0}]);
        }
        return null;
    }

    protected function execTag($op, $type = '*')
    {
        return $op;
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
        $op = strtolower($op);
        if ($prio < 10 || !isset($this->operation[$op])) $this->operation[$op] = $prio;
        if (is_string($phpeq)) {
            $array[$op] = $this->oper(str_replace('~~~', str_replace('%', '%%', $op), $phpeq), array('prio' => $prio, 'types' => $types . '****'));
        } else {
            $array[$op] = $this->oper($phpeq, array('prio' => $prio, 'types' => $types . '****'));
        }
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
        $this->func[strtolower($op)] = $this->oper(str_replace('~~~', $op, $phpeq));
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
            $this->func[$op] = $phpeq;
        else
            $this->func[$op] = $this->oper($phpeq, $type);
        return $this;
    }

    /**
     * определить унарную операциию
     * @param $op - имя операции
     * @param $phpeq - PHP эквивалент - паттерн для sprintf'а с одним параметром
     * @param string $types
     * @param int $prio
     * @return twig2php_class
     */
    function &newOp1($op, $phpeq = null, $types = '*', $prio = 10)
    {
        if (is_null($phpeq)) $phpeq = '~~~(%s)';
        return $this->new_Op($op, $prio, $this->unop, $phpeq, $types);
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
        return $this->new_Op($op, $prio, $this->pattern, !is_null($phpeq) ? $phpeq : '((%s)~~~(%s))', $types);
    }


    function error($mess = null, $lex = null)
    {
        if (is_null($mess)) return parent::error($mess);
        $mess .= sprintf("\n" . 'file: %s', $this->filename);
        if (is_null($lex))
            $lex = $this->currenttag;
        if (!empty($lex)) {
            // count a string
            $lexpos = $lex->start;
            $line = 0;
            foreach ($this->lines as $k => $v) {
                if ($v >= $lex->start) break;
                $lexpos = $v;
                $line = $k + 1;
            }

            $mess .= sprintf('<br>' . "\n" . 'line:%s, pos:%s lex:"%s"'
                , $line + 1, $lex->start - $lexpos, $lex->val);
        }
        $ex = $this->exception_class_name;
        throw new $ex($mess);
    }


    function getnext()
    {
        if (count($this->tag_stack) > 0) {
            return $this->currenttag = array_shift($this->tag_stack);
        }
        static $reg0 = '';

        if ($this->state == 0) {
            // выедаем конструкты вне twig'а
            if (empty($reg0)) {
                $reg0 = "~(.*?)(";
                foreach (array('COMMENT_LINE', 'VARIABLE_START', 'COMMENT_START', 'BLOCK_START') as $r)
                    $reg0 .= preg_quote($this->$r, '#~') . '|';
                $reg0 .= '$)(\-?)~si';
            }

            $this->getcode();
            while ($this->code && preg_match($reg0, $this->code, $m, 0, $this->start)) {
                // найти начало следующего тега шаблонизатора
                $l = strlen($m[1]);
                if ($l > 0) {
                    if (empty($m[2]) && $m[1]{$l - 1} > "\xC0" || $m[1]{$l - 1} == "{") {
                        $l -= 1;
                    }
                    $str = substr($m[1], 0, $l);
                    if ($this->trim_state & 1) {
                        $str = preg_replace('/^\s*/s', '', $str);
                        if (!empty($m[2]) || $str) $this->trim_state = 0;
                    }
                    if ($this->trim_state & 4) {
                        $str = preg_replace('/^[ \t]*(\r?\n)/', '\1', $str);
                        $this->trim_state &= !4;
                    }
                    if (count($this->tag_stack) == 1) {
                        $this->tag_stack[0]->val .= $str;
                    } else {
                        $this->tag_stack[] = $this->oper($str, self::TYPE_STRING, $this->start);
                    }
                }
                $this->start += $l;
                if (!empty($m[3])) {
                    $this->trim_state |= 2;
                    $this->start += 1;
                }
                if ($m[2] && ($this->trim_state & 2)) {
                    if (count($this->tag_stack) == 1) {
                        $this->tag_stack[0]->val = rtrim($this->tag_stack[0]->val);
                    }
                    $this->trim_state &= !(2 + 4);
                }
                if ($m[2] && ($this->trim_state & 4)) {
                    if (count($this->tag_stack) == 1) {
                        $this->tag_stack[0]->val = preg_replace('/\r?\n[ \t]*$/', '', $this->tag_stack[0]->val);
                    }
                }
                if ($m[2] == $this->COMMENT_LINE) {
                    // читаем до следующего конца строки
                    $this->skiptill('/\r?\n/');
                    if (count($this->tag_stack) == 1) {
                        $this->tag_stack[0]->val = preg_replace('/(\r?\n)[ \t]*$/', '', $this->tag_stack[0]->val);
                    }
                } else if ($m[2] == $this->COMMENT_START) {
                    $this->currenttag=$this->oper('{#', array('type' => self::TYPE_NONE,'start'=>$this->start));
                    $cend=$this->skiptill('/(-)?' . preg_quote($this->COMMENT_END) . '/sU');
                    if ($this->COMMENT_XEND == $cend) {
                        $this->trim_state |= 1;
                    } else if($this->COMMENT_END != $cend){
                        $this->error('Closed comment tag not found');
                    };
                } else if ($m[2] == $this->VARIABLE_START) {
                    $this->tag_stack[] = $this->oper('', self::TYPE_OPERATION);
                    //$this->tag_stack[]=$this->oper('_echo_');
                    $this->state = 1;
                    $this->start += strlen($m[2]);
                    break;
                } else if ($m[2] == $this->BLOCK_START) {
                    if (count($this->tag_stack) == 1) {
                        $this->tag_stack[0]->val = preg_replace('/\r?\n[ \t]*$/', '', $this->tag_stack[0]->val);
                    }
                    $this->tag_stack[] = $this->oper('', self::TYPE_COMMA);
                    $this->state = 1;
                    $this->start += strlen($m[2]);
                    break;
                } else if ('' != $m[2] || $m[0] == '') {
                    break; // что-то незаладилось в реге
                }
                $this->getcode();
            }
        }
        parent::getnext();
        if (false == $this->currenttag) {
            return $this->currenttag;
        }
        if (isset($this->pattern[$this->currenttag->val])) {
            $xO = $this->pattern[$this->currenttag->val];
            if (is_callable($xO->val)) {
                $this->currenttag->handler = $xO->val;
//                $this->currenttag->type=self::TYPE_OBJECT;
            }
        }
        if ($this->currenttag->val == $this->VARIABLE_END || $this->currenttag->val == $this->BLOCK_END) {
            $this->state = 0;
            //return $this->getnext();
        } else if ($this->currenttag->val == $this->VARIABLE_XEND
            || $this->currenttag->val == $this->BLOCK_XEND
        ) {
            $this->trim_state = 1;
            $this->state = 0;
            //return $this->getnext();
        }
        if ($this->currenttag->val == $this->BLOCK_XEND
            || $this->currenttag->val == $this->BLOCK_END
        ) {
            $this->trim_state += 4;
        }

        return $this->currenttag;
    }

    function skiptill($s)
    {
        do {
            if (preg_match($s, $this->code, $mm, PREG_OFFSET_CAPTURE, $this->start)) {
                $this->start = $mm[0][1] + strlen($mm[0][0]);
                break;
            } else {
                $this->start = strlen($this->code);
                $this->getcode();
            }
        } while ($this->start<strlen($this->code));
        return isset($mm[0][0])?$mm[0][0]:'';
    }

    /**
     * Вызов функции op1 с параметрами op2
     * @param  operand $op1
     * @param  operand $op2
     * @return operand
     */
    function _call_($op1, $op2)
    {
        //вырезка из массива
        /*if ($op1->handler) {
            return call_user_func($op1->handler, $op1, $op2, 'call');
            //return $op1;
        } else*/
        if (($_x0 = isset($this->func[$op1->val])) || isset($this->operation[$op1->val])) {
            //$op2 схлоп
            /*$x = $_x0?$this->func[$op1->val]->val:$this->operation[$op1->val];
            if (is_callable($x)) {
                $op = call_user_func($x, $op1, $op2);
                if ($op)
                    $this->pushOp($op);
            } elseif (is_string($x)) {*/
            if ($op2->type == self::TYPE_LIST) {
                if ($op1->type == self::TYPE_SLICE && $op1->handler) {
                    $op1 = call_user_func($op1->handler, $op1, $op2, 'call');
                } else {
                    $op1->list = $op2->val;
                    $op1->type = self::TYPE_CALL;
                }
            } else {
                $op1 = $this->to(self::TYPE_CALL, $op1);
                $op1->type = self::TYPE_CALL;
            }
            /*  } else
                  $this->error('wtf4!!!');*/
        } else {
            //вызов макрокоманды
            if ($op2->type == self::TYPE_LIST) {
                // call macro
                $arr = array();
                $arrkeys = array();
                for ($i = 0, $max = count($op2->val['value']); $i < $max; $i++) {
                    if (is_null($op2->val['value'][$i])) {
                        $arr[] = $this->to('S', $op2->val['keys'][$i])->val;
                    } else {
                        $arrkeys[] = array(
                            'key' => $op2->val['keys'][$i]->val,
                            'value' => $this->to('S', $op2->val['value'][$i])->val
                        );
                    }
                }
                if (isset($this->func[$op1->val])) {
                    //$opX=$this->func[$op1->val];
                    $opX = $this->oper(sprintf($this->func[$op1->val]->val, $this->to(array('L', 'value'), $op2)), self::TYPE_OPERAND);
                } else if ($op1->type == self::TYPE_SLICE) {
                    // вызов объектного макроса
                    $this->to('I', $op1->list[0]);
                    //$op1->val =$op1->list[0]->val;
                    $opX = $this->oper($this->template('callmacroex', array('par1' => $op1->list[0]->val, 'mm' => $op1->list[1]->val, 'param' => $arr)), self::TYPE_OPERAND);
                } else {
                    $opX = $this->oper($this->template('callmacro', array('name' => $op1, 'param' => $arr, 'parkeys' => $arrkeys)), self::TYPE_SENTENSE);
                }
                return $opX;
            } else
                $this->error('have no function ' . $op1);
        }
        return $op1;
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

        foreach ($types as $type) {
            // if(isset(self::$char2class[$type])) $type=self::$char2class[$type];
            switch ($type) {
                /*
                * служебные операции
                */
                case 'trimln': // удалить первый NewLine из строки
                    $res->val = preg_replace('/^[ \t]*\r?\n/', '', $res->val);
                    break;
                case 'triml':
                    $res->val = preg_replace('/^\s*/s', '', $res->val);
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
                        $res->val = $this->template('sentence', array('data' => $res->val));
                    }
                    $res->type = self::TYPE_SENTENSE;
                    break;
                /*
                * просто литерал
                */
                case self::TYPE_LITERAL:
                    if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2 || $res->type == self::TYPE_OPERATION) {
                    } else {
                        $this->error('plain literal expected');
                    }
                    break;
                /*
                * преобразования
                */
                case self::TYPE_CALL:
                    if ($res->type == self::TYPE_COMMA || $res->type == self::TYPE_OPERATION) {
                        $res->list = array('value' => array(), 'keys' => array());
                        $res->type = self::TYPE_CALL;
                    } elseif ($res->type != self::TYPE_CALL) {
                        $this->error('Невозможная комбинация типов (>>call)');
                    }
                    break;
                case 'I':
                case self::TYPE_XID:
                    if ($res->type == self::TYPE_CALL) {
                        $this->to('S', $res);
                    } else if ($res->type == self::TYPE_ID || $res->type == self::TYPE_STRING2) {
                        $this->checkId($res);
                        $res->val = '$' . $res->val;
                        $res->type = self::TYPE_OPERAND;
                    } elseif ($res->type == self::TYPE_SLICE) {
                        if (!empty($res->list)) {
                            $this->to('I', $res->list[0]);
                            $res->val = $res->list[0]->val;
                            if ($res->list[0]->type == self::TYPE_STRING) {
                                $res->val = $this->to(array('S', 'value'), $res->list[0]);
                            } else if ($res->handler) {
                                $res->val = call_user_func($res->handler, $res, '', 'value');
                            } else {
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
                            }
                            unset($res->list);
                        }
                        $res->type = self::TYPE_XSTRING;
                    } elseif ($res->type == self::TYPE_LIST) {
                        $this->to(self::TYPE_XLIST, $res);
                    } elseif ($res->type != self::TYPE_XID && $res->type != self::TYPE_OBJECT) {
                        $this->error('waiting for ID');
                    };
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
                    if ($res->type == self::TYPE_SLICE) {
                        $this->to('I', $res);
                        break;
                    } else if ($res->type == self::TYPE_XID) {
                        $res->val = '(isset(' . $res->val . ') && !empty(' . $res->val . '))';
                        break;
                    } elseif ($res->type == self::TYPE_XSTRING) {
                        $res->val = '!!(' . $res->val . ')';
                        break;
                    } else if ($res->type == self::TYPE_SENTENSE) {
                        $this->error('casting to boolean impossible (can\'t call macro this way)');
                        //                       echo $res->type.' ';
                    }
                // продолжаем то, что ниже!!!!!!!!!
                case 'L':
                    if ($res->type == self::TYPE_LIST) {
                        $op = array();
                        for ($i = 0; $i < count($res->val['keys']); $i++) {
                            $x = '';
                            if (!empty($res->val['value'][$i])) {
                                if($res->val['keys'][$i]->type==self::TYPE_ID)
                                    $res->val['keys'][$i]->type=9;
                                $x .= $this->to('*', $res->val['keys'][$i])->val.'=>'.$this->to('*', $res->val['value'][$i])->val ;
                            } else {
                                $x .= $this->to('*', $res->val['keys'][$i])->val;
                            }
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
                case self::TYPE_XSTRING:
                case self::TYPE_STRING:
                    if ($res->type == self::TYPE_CALL) {
                        //$this->to('I', $res);
                        $arr = array();
                        if (isset($res->list['keys'])) {
                            for ($i = 0; $i < count($res->list['keys']); $i++) {
                                $arr[] = $this->to('S', $res->list['keys'][$i])->val;
                            }
                        }
                        if ($res->handler) {
                            $res = call_user_func($res->handler, $res, $arr);
                        } else if (isset($this->func[$res->val])) {

                            $res->val = sprintf($this->func[$res->val],
                                implode(',', $arr));
                        } else {
                            $this->error('WTF455!');
                        }
                        $res->type = self::TYPE_XSTRING;
                    }
                    if ($res->type == self::TYPE_ID) $this->to('I', $res);
                    if ($res->type == self::TYPE_OBJECT) {
                        $res->val = call_user_func($res->handler, $res, '', 'value');
                        $res->type = self::TYPE_OPERAND;
                    }
                    if ($res->type == self::TYPE_SLICE) $this->to('I', $res);
                    if ($res->type == self::TYPE_LIST) {
                        $arr = array();
                        if (isset($res->val['keys'])) {
                            for ($i = 0; $i < count($res->val['keys']); $i++) {
                                $arr[] = $this->to('S', $res->val['keys'][$i])->val;
                            }
                        } else {
                            for ($i = 0; $i < count($res->val); $i++) {
                                $arr[] = $this->to('S', $res->val[$i])->val;
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
        }
        return $res;
    }

    /**
     * xstart - начало, откуда начинается $this->code;
     */
    protected function getcode()
    {
        if ($this->start > self::BUFLEN) {
            $this->code = substr($this->code, $this->start);
            if ($this->code === false) $this->code = '';
            $this->xstart += $this->start;
            $this->start = 0;
        }
        if (strlen($this->code) < self::BUFLEN && !feof($this->handler)) {
            $x = strlen($this->code);
            $this->code .= fread($this->handler, self::BUFLEN);
            if (strlen($this->code) < 2 * self::BUFLEN && !feof($this->handler)) {
                $this->code .= fread($this->handler, self::BUFLEN);
            }
            while (false !== ($x = strpos($this->code, "\n", $x))) {
                $this->lines[] = $this->xstart + $x++;
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
     * @param string $classname
     * @return mixed|string
     */
    function tplcalc($class = 'compiler', $classname = 'compiler')
    {
        $tpl_compiler = 'tpl_' . $class;
        if (!is_object($this->tpl_compiler) || get_class($this->tpl_compiler) != $tpl_compiler) {
            if (!class_exists($tpl_compiler)) {
                // попытка включить файл
                include_once(template_compiler::options('templates_dir') . $tpl_compiler . '.php');
            }
            $this->tpl_compiler = new $tpl_compiler();
        }

        $this->locals = array();
        $this->tag_stack = array();
        $this->code = '';
        $this->opensentence[] = array('tag' => 'class', 'import' => array(), 'macro' => array(), 'name' => $class, 'data' => array());

        $tagx = array('tag' => 'block', 'name' => ' ', 'operand' => count($this->ex_stack), 'data' => array());
        $this->opensentence[] = & $tagx;

        $this->block_internal(array(), $tagx);

        $tagx = array_pop($this->opensentence);
        $tag = array_pop($this->opensentence);
        // TODO: разобраться с правильным наследованием _
        // сейчас просто удаляем метод _ из отнаследованного шаблона
        if (!empty($tag['extends'])) {
            for ($x = 0; $x < count($tag['data']); $x++) {
                if (preg_match('/function\s+_\s*\(/i', $tag['data'][$x]))
                    break;
            }
            unset($tag['data'][$x]);
        }
        if (!empty($classname)) {
            $tag['name'] = $classname;
        }
        $this->popOp();
        $this->ex_stack[] = $this->oper($this->template('class', $tag), self::TYPE_SENTENSE);

    }


    /**
     * фильтр - replace
     * @param operand $op1 - TYPE_ID - имя функции
     * @param operand $op2 - TYPE_LIST - параметры функции
     * @return \operand
     */
    function function_replace($op1, $op2)
    {
        $op1->val = 'str_replace(' . $this->to('S', $op1->list['keys'][1])->val
            . ',' . $this->to('S', $op1->list['keys'][2])->val
            . ',' . $this->to('S', $op1->list['keys'][0])->val
            . ')';
        $op1->type = self::TYPE_OPERAND;
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
        foreach ($op2 as &$v) {
            $value[] = $this->to('S', $v)->val;
        }
        array_unshift($value, '$par');

        $op1->val = 'parent::_' . $this->currentFunction . '(' . implode(',', $value) . ')';
        $op1->type = self::TYPE_OPERAND;
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
            $i = $op1->val;
            $y = $op2->val;
            $step = $i > $y ? -1 : 1;
            $arr = array();
            $keys = array();
            for (; $i != $y; $i += $step) {
                $arr[] = null;
                $keys[] = $this->oper($i, self::TYPE_DIGIT);
            }
            $arr[] = null;
            $keys[] = $this->oper($y, self::TYPE_DIGIT);
            $op1->val = array('keys' => $keys, 'value' => $arr);
            $op1->type = self::TYPE_LIST;
            return $op1;
        } elseif (($op1->type == self::TYPE_STRING && $op2->type == self::TYPE_STRING)) {
            $i = $this->utford($op1->val);
            $y = $this->utford($op2->val);
            $step = $i > $y ? -1 : 1;
            for (; $i != $y; $i += $step) {
                $arr[] = null;
                $keys[] = $this->oper($this->utfchr($i), self::TYPE_STRING);
            }
            $arr[] = null;
            $keys[] = $this->oper($this->utfchr($y), self::TYPE_STRING);
            $op1->val = array('keys' => $keys, 'value' => $arr);
            $op1->type = self::TYPE_LIST;
            return $op1;
        } else { //todo: исправить
            return $this->oper('$this->func_reprange(%s,%s)', self::TYPE_LIST);
        }
    }

    /**
     * отработка тега block, блок верхнего уровня является "корневым" элементом
     * @param operand[]|null $tag_waitingfor
     * @param operand|null $tag
     */
    function block_internal($tag_waitingfor = array(), &$tag = null)
    {
        if (empty($tag))
            $tag = array('tag' => 'block', 'data' => array());
        $tag['operand'] = count($this->ex_stack);
        //$this->pushop('(');
        do {
            if ($this->getnext() === false) {
                $this->back();
                break;
            }

            if ($this->currenttag->type == self::TYPE_STRING) {
                // просто строка междутежная
                $this->syntax_tree[] = $this->currenttag;
                $this->execute();
                continue;
            }
            if (!empty($tag_waitingfor) && $this->currenttag->type != self::TYPE_STRING && in_array($this->currenttag->val, $tag_waitingfor)) {
                // дождались стоп-тега
                $this->back();
                break;
            }
            if ($this->currenttag->type == self::TYPE_COMMA && ($this->currenttag->val = ';' || empty($this->currenttag->val))) {
                $this->execute();
                // служебный знак препинания, препинаемся
            } elseif ($this->currenttag->type == self::TYPE_OPERATION && empty($this->currenttag->val)) { // {{
                $this->getExpression($tag_waitingfor);
                if ($this->currenttag->val != $this->VARIABLE_END && $this->currenttag->val != $this->VARIABLE_XEND) {
                    $this->error('wrong closed tag used.');
                }
                // служебный знак препинания, препинаемся
            } elseif ($this->exec_tag($this->currenttag->val)) {
                if (isset($this->reserved_words[$this->currenttag->val])
                    && ($this->reserved_words[$this->currenttag->val] == -2)
                ) {
                    // стоп-слово
                    continue;
                } else {
                    $this->back();
                }
                // ведомый науке тег
                //              break;
            } else {
                if (isset($this->reserved_words[$this->currenttag->val])
                    && ($this->reserved_words[$this->currenttag->val] == -2)
                ) {
                    // стоп-слово
                    continue;
                } else if ($this->currenttag->type == self::TYPE_ID) {
                    // вызов макрокоманды
                    $this->back();
                    $this->getExpression($tag_waitingfor);
                    $this->back();
                    continue;
                }
                // неведомый науке тег, плачем...
                break;
            }
        } while ($this->currenttag);
        $this->getnext();
        // $this->pushop(')'); // свернуть все операции
        $this->execute();
        $thetype = self::TYPE_OPERAND;
        if ($tag['operand'] < count($this->ex_stack)) {
            $x = array();
            while ($tag['operand'] < count($this->ex_stack)) {
                $x[] = array_pop($this->ex_stack);
            }
            while (count($x) > 0) {
                $y = array_pop($x);
                if ($y->type == self::TYPE_STRING && $y->val == '') continue;
                if ($y->type == self::TYPE_XSTRING && ($y->val == "''" || $y->val == '""')) continue;
                $y = $this->to('S', $y);
                if ($y->type == self::TYPE_SENTENSE) {
                    $thetype = self::TYPE_SENTENSE;
                }
                $tag['data'][] = $y;
            }
        }

        if (empty($tag['name'])) {
            //$t['data'][] = $this->template('block',$tag,$t['name']);
            $x = $this->template('block', $tag);
            if ($thetype == self::TYPE_OPERAND) {
                if (false !== strpos($x, '$result.=')) {
                    $thetype = self::TYPE_SENTENSE;
                }
            }
            $this->syntax_tree[] = $this->oper($x, $thetype);
        } else {
            $t =& $this->opensent('class');
            $t['data'][] = $this->template('block', $tag, $t['name']);
//            $this->syntax_tree[]=$this->oper($this->template('block',  $tag), self::TYPE_SENTENSE);
            $this->syntax_tree[] = $this->oper($this->template('callblock', $tag), self::TYPE_OPERAND);
        }
        $this->execute();
    }

    /**
     * функция проверяем комплект локальных переменных
     * @param operand $id
     * @return bool
     */
    function checkId($id)
    {
        for ($i = $this->ids_low; $i < count($this->locals); $i++) {
            if ($this->locals[$i] == $id->val)
                return true;
        }
        $this->locals[] = $id->val;
        $tag =& $this->opensent('block', 'macros');
        if (!isset($tag['param'])) $tag['param'] = array();
        $tag['param'][] = array(
            'name' => $id->val, 'value' => '""'
        );
        return false;
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

    function &opensent($sent, $sent2 = '@@')
    {
        for ($i = count($this->opensentence) - 1; $i >= 0; $i--) {
            if ($this->opensentence[$i]['tag'] == $sent || $this->opensentence[$i]['tag'] == $sent2)
                return $this->opensentence[$i];
        }
        return null;
    }

    function function_filter($op1, $op2)
    {
        $op2 = $this->to(self::TYPE_CALL, $op2);
        array_unshift($op2->list['value'], null);
        array_unshift($op2->list['keys'], $op1);
        return $op2;
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
        $ids_low = $this->ids_low;
        $this->ids_low = count($this->locals);
        $tag = array('tag' => 'macros', 'operand' => count($this->ex_stack), 'data' => array());
        $this->opensentence[] = & $tag;
        $this->getnext(); // name of macros
        // зарегистрировать как функцию
        $tag['name'] = $this->to(self::TYPE_LITERAL, $this->currenttag)->val;
        $this->currentFunction = $tag['name'];
        $this->getnext(); // name of macros
        if ($this->currenttag->val != '(') {
            $this->error('expected macro parameters');
        }
        $par = $this->get_Parameters_list('=');
        $arr = array();
        for ($i = 0, $max = count($par['value']); $i < $max; $i++) {
            if (is_null($par['value'][$i])) {
                $arr[] = array('name' => $this->to(self::TYPE_LITERAL, $par['keys'][$i])->val);
            } else {
                $v = $this->to('S', $par['value'][$i])->val;
                if (!$v) $v = '0 ';
                $arr[] = array(
                    'name' => $this->to(self::TYPE_LITERAL, $par['keys'][$i])->val,
                    'value' => $v
                );
            }
        }

        $tag['param'] = $arr;

        if ($this->currenttag->val != ')') {
            $this->error('expected )');
        }

        $id_count = count($this->locals);
        foreach ($tag['param'] as $v) {
            $this->newId($v['name']);
        }
        $this->block_internal(array('endmacro'), $tag);
        /*$op=*/
        $this->popOp();
        $tag['body'] = $this->template('block', $tag);
        array_splice($this->locals, $id_count);
        // $this->getnext();
        if ($this->currenttag->val != 'endmacro')
            $this->error('there is no endmacro tag');
        else
            $this->getnext();
        array_pop($this->opensentence);
        // добавляем в открытый класс определение нового метода
        $sent =& $this->opensent('class');
        if (!empty($sent)) {
            if (empty($sent['macro'])) {
                $sent['macro'] = array();
            }
            $sent['macro'][] = $tag['name'];
        }
        $this->locals = array_slice($this->locals, 0, $ids_low);
        $this->ids_low = $ids_low;
    }

    /**
     * отрабoтка тега block
     */
    function tag_block()
    {
        $ids_low = $this->ids_low;
        $this->ids_low = count($this->locals);
        $tag = array('tag' => 'block', 'operand' => count($this->ex_stack), 'data' => array());
        $this->getnext(); // name of macros
        // зарегистрировать как функцию
        $tag['name'] = $this->to(self::TYPE_LITERAL, $this->currenttag)->val;
        $this->currentFunction = $tag['name'];
        $this->opensentence[] =& $tag;
        //$this->getnext();
        $this->block_internal(array('endblock'), $tag);
        $tag = array_pop($this->opensentence);
        //$this->getnext();
        $tag['body'] = $this->template('block', $tag);
        // $this->getnext();
        if ($this->currenttag->val != 'endblock')
            $this->error('there is no endblock tag');
        else
            $this->getnext();
        // добавляем в открытый класс определение нового метода

        $this->locals = array_slice($this->locals, 0, $ids_low);
        $this->ids_low = $ids_low;
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
        // $this->getnext(); // съели символ, закрывающий тег
    }

    /**
     * Отработка тега фильтр
     */
    function tag_filter()
    {
        $name = $this->getnext(); // name of macros
        // зарегистрировать как функцию
        $xx = $this->getnext(); //todo: if $xx ==%} - все в порядке
        $x = array();
        do {
            $xx = $this->getnext();
            $x[] = $xx->val;
        } while ($xx->type == 9);
        array_pop($x);
        $xx = $this->getnext();
        if ($xx->val != 'endfilter') $this->error('filter cannot be applied, sorry', $xx);
        $this->getnext();

        $result = implode("\n", $x);
        switch (strtolower($name)) {
            case 'scss':
                include_once '/projects/tools/scssphp/scss.inc.php';
                $scss = new scssc();
                $result = $scss->compile($result, 'embeded');
                break;
        }
        $this->syntax_tree[] = $this->oper($result, self::TYPE_STRING);
        $this->execute();
    }

    /**
     * отрабoтка тега if
     * @return void
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
        $tag = array('tag' => 'if', 'operand' => count($this->ex_stack), 'data' => array(), 'starttag' => $this->currenttag);
        do {
            // сюда входим с уже полученым тегом if или elif
            $otag = $this->getExpression();
            $op = $this->popOp();
            $data = array(
                'if' => $this->to(array('B', 'value'), $op)
            );
            if ($otag->val == 'set' || $otag->val == 'if' || $otag->val == 'for') {
                $this->back();
            }
            // $this->getnext(); // выдали таг
            $this->block_internal(array('elseif', 'elif', 'else', 'endif'));
            $op = $this->popOp();
            $data['then'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
            $tag['data'][] = $data;
            // $this->getnext(); // выдали таг
            if (!$this->currenttag) {
                $this->error('Unclosed IF tag', $tag['starttag']);
                break;
            };
            if ($this->currenttag->val == 'endif')
                break;
            if ($this->currenttag->val == 'else') {
                // $this->getnext();
                $this->block_internal(array('endif'));
                $op = $this->popOp();
                $data = array(
                    'if' => false,
                    'then' => $this->to(array(self::TYPE_SENTENSE, 'value'), $op)
                );
                $tag['data'][] = $data;
                // $this->getnext(); // выдали таг
                break;
            }
        } while ($this->currenttag);
        $xtag = $this->getnext(); // съели символ, закрывающий тег
        if ($xtag->val != $this->BLOCK_XEND && $xtag->val != $this->BLOCK_END && $xtag->val != $this->VARIABLE_END && $xtag->val != $this->VARIABLE_XEND) {
            $this->back($xtag);
        }
        $this->syntax_tree[] = $this->oper($this->template('if', $tag), self::TYPE_SENTENSE);
        $this->execute();
        return;
    }

    function tag_import()
    {
        //$set =array('tag'=>'import','operand'=>count($this->operand));
        $this->getExpression(); // получили имя файла для импорта
        $op = $this->popOp();
        $t =& $this->opensent('class');
        $t['import'][] = basename($op->val, '.' . template_compiler::options('TEMPLATE_EXTENSION', 'jtpl'));
        //   $this->getnext();
        return false;
    }

    /**
     *
     *  тег SET
     *
     */
    function tag_set()
    {
        $set = array('tag' => 'set', 'operand' => count($this->ex_stack));
        $this->getExpression(array('='));
        $id = $this->popOp(); // получили имя идентификатора
        $id = $this->newId($id);
        //$this->locals[]=$id->val;
        $set['id'] = $this->to(array('I', 'value'), $id);
        //$set['id'] = $this->to(array('I', 'value'), $set['id']);
        //$this->getnext();
        if ($this->currenttag->val != '=')
            $this->error('unexpected construction9');
        $this->getExpression();
        $this->back();
        $set['res'] = $this->popOp();
        if ($set['res']->type == self::TYPE_LIST)
            $set['res'] = $this->to(self::TYPE_XLIST, $set['res'])->val;
        else
            $set['res'] = $this->to('*', $set['res'])->val;
        // $this->getnext();
        $this->syntax_tree[] = $this->oper($this->template('set', $set), self::TYPE_SENTENSE);
        $this->execute();
        return;
    }

    /**
     * Встроенные в класс рендерер ...
     * @param string $idx - имя подшаблона "" - корневой подшаблон
     * @param array $par - данные для рендеринга
     * @return mixed|string
     */
    function template($idx = null, $par = null)
    {
        if (!is_null($par)) {
            $x =& $par;
            if (method_exists($this->tpl_compiler, '_' . $idx))
                return call_user_func(array($this->tpl_compiler, '_' . $idx), $x);
            else
                printf('have no template "%s:%s"', get_class($this->tpl_compiler), '_' . $idx);
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
        /*if (class_exists($name, false)) {
            $tag = new $name();
            $tag->execute($this);
        } else */
        if (method_exists($this, $name)) {
            call_user_func(array($this, $name));
        } else
            return false;

        return true;
    }


    /**
     * Описание хелпера loop для тега for
     * @param operand $op1
     * @param operand|array $attr
     * @param string $reson - attr - добавить новый атрибут , call - вызвать
     * @return null|operand|string
     */
    function operand_loop($op1 = null, $attr = null, $reson = 'attr')
    {
        if ($op1->type == self::TYPE_SLICE) {
            $_attr = '';
            for ($i = 1; $i < count($op1->list); $i++)
                $_attr .= '.' . $op1->list[$i]->val;
        } else
            $_attr = property_exists($op1, 'attr') ? $op1->attr : '';
        $tag = & $this->opensent('for');
        $loopdepth = $tag['loopdepth'];
        while (strpos($_attr, '.parent.loop') !== false) {
            $_attr = substr($_attr, 12);
            while ($loopdepth-- > 0 && $this->opensentence[$loopdepth]['tag'] != 'for') {
            }
            $tag = & $this->opensentence[$loopdepth];
        }
        // найти ближайший открытый for и отметить, что loop там используется.
        if ($reson == 'call') {
            // рекурсивный вызов цикла еще раз
            if ($_attr == '.cycle') {
                $tag['loop_cycle'] = 'array(' . $this->to('S', $attr)->val . ')';
                return $this->oper('$this->loopcycle($loop' . $loopdepth . '_cycle)', self::TYPE_OPERAND);
            } else {
                $this->error('calling not a callable construction ' . $_attr);
            }
        } else if (is_null($attr) || $attr instanceof rpn_class) {
            $op = $this->oper('loop', self::TYPE_OBJECT);
            $op->attr = '';
            $op->handler = array($this, 'operand_loop');
            return $op;
        } else if ($reson == 'attr') {
            if (is_object($attr)) $attr = $attr->val;
            if (in_array($attr,
                array('first', 'cycle', 'last', 'index0', 'loop', 'parent', 'revindex', 'revindex0', 'length', 'index')
            )
            ) {
                $op1->attr .= '.' . $attr;
                return $op1;
            } else {
                $this->error('undefined loop attribute(1)-"' . $attr . '"!');
            }
        } else if ($reson == 'value') {
            switch ($_attr) {
                case '.first':
                    $tag['loop_index'] = true;
                    return '$loop' . $loopdepth . '_index==1';
                case '.last' :
                    $tag['loop_index'] = true;
                    $tag['loop_last'] = true;
                    return '$loop' . $loopdepth . '_index==$loop' . $loopdepth . '_last';
                case '.cycle':
                    $tag['loop_cycle'] = true;
                    return '';
                case '.index0':
                    $tag['loop_index'] = true;
                    return '($loop' . $loopdepth . '_index-1)';
                case '.revindex':
                    $tag['loop_revindex'] = true;
                    $tag['loop_last'] = true;
                    return '$loop' . $loopdepth . '_revindex';
                case '.revindex0':
                    $tag['loop_revindex'] = true;
                    $tag['loop_last'] = true;
                    return '($loop' . $loopdepth . '_revindex-1)';
                case '.length':
                case '.index':
                    $tag['loop_index'] = true;
                    return '$loop' . $loopdepth . '_' . substr($_attr, 1);
                default :
                    $this->error('undefined loop attribute-"' . $_attr . '"!');
            }
        }
        return null;
    }

    /**
     */
    function tag_for()
    {
        // парсинг тега for
        // полная форма:
        // for OPERAND in EXPRESSION [if EXPRESSION]
        // промежуточный else
        // финишный endfor
        $tag = array('tag' => 'for',
            'operand' => count($this->ex_stack),
            'loopdepth' => count($this->opensentence)
        );
        $this->opensentence[] = & $tag;
        // $x=$this->get_Parameters_list('');
        $this->getExpression(array('in', ',')); // получили имя идентификатора
        $id = $this->newId($this->popOp());
        $tag['index'] = $this->to(array('I', 'value'), $id);
        //
        if ($this->currenttag->val == ',') { // key-value pair selected
            //$this->getNext();
            $this->getExpression(array('in'));
            $id = $this->newId($this->popOp());
            $tag['index2'] = $this->to(array('I', 'value'), $id);
        }
        $this->back();
        do {
            $this->getNext();
            switch (strtolower($this->currenttag->val)) {
                case 'in':
                    $this->getExpression();
                    // $tag['in'] = $this->popOp();
                    $this->back();
                    $id = $this->popOp();
                    $tag['in'] = $this->to(array('I', 'value'), $id);
                    break;
                case 'if':
                    $this->getExpression();
                    $id = $this->popOp();
                    $tag['if'] = $this->to(array('*', 'value'), $id);
                    break;
                case 'recursive':
                    $tag['recursive'] = true;
                    break;
                default:
                    if ($this->currenttag->val == $this->BLOCK_END
                        || $this->currenttag->val == $this->BLOCK_XEND
                        || $this->currenttag->type == self::TYPE_COMMA
                    )
                        break 2;
                    else
                        $this->error('unexpected construction1');
            }
        } while ($this->currenttag);
        //$this->opensentence[]=$tag;

        $tag['_else'] = false;
        do {
            $this->block_internal(array('else', 'endfor'));
            //  $this->getNext();
            $op = $this->popOp();
            if ($this->currenttag->val == 'else') {
                $tag['body'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
                $tag['_else'] = true;
                // $this->getNext(); // съели символ, закрывающий тег
            } elseif ($this->currenttag->val == 'endfor') {
                $this->getNext(); // съели символ, закрывающий тег
                if ($tag['_else']) {
                    $tag['_else'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
                } else {
                    $tag['body'] = $this->to(array(self::TYPE_SENTENSE, 'value'), $op);
                }
                // генерируем все это добро
                $this->syntax_tree[] = $this->oper($this->template('for', $tag), self::TYPE_SENTENSE);
                do {
                    $op = array_pop($this->opensentence);
                    if ($op['tag'] == 'for') break;
                } while (!empty($op) && true);

                break;
            } else {
                $this->error('Improper tag ' . $this->currenttag->val);
            }
        } while ($this->currenttag);

    }

    function _scratch_($op1, $op2)
    {
        if ($op2->type == self::TYPE_LIST) {
            foreach ($op2->val['keys'] as &$v) {
                if ($v->type == self::TYPE_ID) {
                    $v = $this->to('S', $v);
                }
            }
        }
        return parent::_scratch_($op1, $op2);
    }
}