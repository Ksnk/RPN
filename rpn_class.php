<?php

/**
 * полностью кастомизируемый класс для анализа скобочной записи и трансляции ее
 * в обратную прольскую форму с вызовами и определяемыми юзером операциями
 *
 * И никакой статики, Карл...
 * <%=point('hat','jscomment','
 *
 *
 *
 *
 *
 *
 * ');%>
 */
class rpn_class
{

    /**
     * комплект типов на все случаи  жизни
     */
    const
        TYPE_OPERAND = 1
    , TYPE_XBOOLEAN = 2
    , TYPE_NONE = 3
    , TYPE_XDIGIT = 4
    , TYPE_XSTRING = 5
    , TYPE_XID = 6
    , TYPE_XLIST = 7
    , TYPE_OBJECT = 8
    , TYPE_STRING = 9
    , TYPE_DIGIT = 10
    , TYPE_OPERATION = 11
    , TYPE_ID = 12
    , TYPE_COMMA = 13
    , TYPE_EOF = 14
    , TYPE_STRING1 = 15
    , TYPE_STRING2 = 16
    , TYPE_LIST = 17
    , TYPE_SLICE = 18
    , TYPE_STRATCH = 19
    , TYPE_CALL = 20
    ;

    static $char2class = array(
        'B' => self::TYPE_XBOOLEAN,
        '*' => self::TYPE_XSTRING,
        'I' => self::TYPE_XID,
        'L' => self::TYPE_XLIST,
        'S' => self::TYPE_XSTRING,
        'D' => self::TYPE_XSTRING,
        'C' => self::TYPE_CALL,
    );

    /**
     * флаги инициализации класса
     */
    const
        THROW_EXCEPTION_ONERROR = 1 // выкинуть exception в случае ошибки
    //,    STOP_ONERROR = 2 // не пригодился
    ,   SHOW_ERROR = 4 // выводить в лог ошибки
    ,   SHOW_DEBUG = 8 // выводить в лог отладку
    ,   EMPTY_FUNCTION_ALLOWED = 16 // допускается автоматическое дописывание пустой функции между операторами

    ,   ALLOW_STRINGS = 32 // допускаются операнды  - строки
    ,   ALLOW_REAL = 64 // допускаются операнды - вещественные числа.
    ,   ALLOW_ID = 128 // допускаются операнды - неведомые идентификаторы
    ,   ALLOW_COMMA = 256 // допускаются неописанные знаки препинания (,;)
    ,   ALLOW_DOTSTRATCH = 512 // вырезка из массива точкой
    ,   CASE_SENCITIVE = 1024
    ;
    /**
     * место для хранения композиции из флагов
     * @var int
     */
    var $flags = 0; // 1 exception, 2-stop on error, 4-error, 8-debug

    /**
     * массив операций, суффиксов и унарных. По нему будем строить регулярку
     */
    protected $operation = array( // злые люди обязательно забудут про скобочки
        ')' => 0,
        '(' => -1,
        ',' => -1,
    ); // операции, которе могут стоять на месте операций
    protected $func = array(); // могут стоять на месте операндов
    protected $suffix = array();
    protected $unop = array();

    /**
     * массив зарезервированных слов. По нему будем строить регулярку
     * Зарезервированные слова используются в качестве предопределенных функций
     *  'PI'=>0,'NOW'=>0 - слово-функция
     *  'SIN'=>1 - функция с одним параметром
     *  'EXP'=>2 - функция с 2-мя параметрами
     *  'ECHO'=>-1 - функция с неопределенным количеством параметров
     *  'IF'=>-2 - стоп-слово, устанавливается тип комма
     * Все зарезервированные слова обязаны обрабатываться evalTag'ом
     */
    protected $reserved_words = array();

    /**
     * имя класса-исключения, которое будем вызывать
     */
    protected $exception_class_name = 'Exception';

    /**
     * callback - обработчики
     */
    protected $executeOp = false;
    protected $evaluateTag = false;

    /**
     * временные переменные, только на время трансляции или инициализации.
     */

    /** @var operand[] - стек операций */
    protected $op = array();
    /** @var mixed стек операндов. В конце останется только один... */
    protected $ex_stack = array();

    /** @var int начало обрабатываемой конструкции в строке, уже обработанный участок, не вошедший в строку */
    protected $start = 0, $xstart = 0;

    /** @var string|string[] - массив ошибок */
    private $errors = array();

    /** @var string - регулярка, собираемая после установки всех опций */
    private $sintaxreg = '##i';

    /** @var string - способ отстрелить себе ногу. Регулярка для определения операнда */
    private $tagreg = '';

    /** @var operand - вот этот тег. Текуший тег, выковырянный из сводящей строки */
    protected $currenttag = false;

    /** @var bool - а не нужно ли перегенерировать регулярку? */
    private $option_compiled = false;

    /** @var operand[] - синтаксический поток (почему дерево?) */
    protected $syntax_tree = array();
    private $types = array();
    private $type = 0;
    private $pastcode = '';
    protected $code;
    /** @var operand[] - массив заранее распознанных тегов */
    protected $tag_stack = array();

    /**
     * вывод информации в лог отладки
     * @param $mess
     */
    public function log($mess)
    {
        if (0 == ($this->flags & self::SHOW_DEBUG)) return;
        echo "\n" . $mess . '<br />';
    }

    /**
     * вывод информации об ошибке
     * @param $mess
     * @return array
     */
    public function error($mess = null,$op=null)
    {
        if (is_null($mess))
            return $this->errors;

        if (0 != ($this->flags & self::THROW_EXCEPTION_ONERROR)) {
            $ex = $this->exception_class_name;
            throw new $ex($mess);
        };
        $this->errors[] = $mess;
        if (0 != ($this->flags & self::SHOW_ERROR)) {
            echo "\n" . $mess . '<br />';
        }
        return false;
    }

    /**
     * сюда мы будем бросать кости. Потом все вот это назовем полностью
     * кастомизируемым объектом.
     * На самом деле мы просто сливаем наверх заботу о параметрах
     * @param $opt
     */
    public function option($opt)
    {
        if (!is_array($opt)) return;
        foreach ($opt as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key == 'operation') {
                    $this->$key = array_merge(
                        array( // злые люди обязательно забудут про скобочки
                            ')' => 0,
                            '(' => -1,
                            ',' => -1,
                        ),
                        $value
                    );
                } else {
                    $this->$key = $value;
                }
            }
        }
        $this->option_compiled = false;
    }

    private function compile_options()
    {

        // строим регулярку по всем определенным параметрам

        $tags = array_unique(array_merge(
                array_keys($this->reserved_words),
                array_keys($this->operation),
                array_keys($this->unop),
                array_keys($this->suffix))
        );
        if (0 != (self::EMPTY_FUNCTION_ALLOWED & $this->flags))
            $this->operation['_EMPTY_'] = 3;
        $cake = array(
            'WORD_OP' => array(), // словные операции
            'MWORD_OP' => array(), // многословные операции
            'JUST_OP' => array(), // символьные многобуквеные операции
            'SYMBOL' => '', // однобуквенные операции
        );
        if (!empty($tags)) {
            foreach ($tags as $v) {
                if (preg_match('/^\w+$/', $v))
                    $cake['WORD_OP'][] = $v;
                else if (preg_match('/^[\s\w]+$/', $v))
                    $cake['MC_JUST_OP'][] = $v;
                else if (strlen($v) > 1)
                    $cake['JUST_OP'][] = $v;
                else
                    $cake['SYMBOL'] .= $v;
            }
        }
        $reg = array();
        $this->types = array(0);
        // вставляем в регулярку строки
        if (0 != ($this->flags & self::ALLOW_STRINGS)) {
            $reg[] = '([\'`"])((?:[^\\1\\\\]|\\\\.)*?)\\1';
            $this->types[] = 0;
            $this->types[] = self::TYPE_STRING;
        }
        if (0 != ($this->flags & self::ALLOW_DOTSTRATCH)) {
            $reg[] = '\.\s*(\w+)';
            $this->types[] = self::TYPE_STRATCH;
        }
        $xreg = array();
        if (!empty($cake['MWORD_OP'])) {
            foreach ($cake['MWORD_OP'] as $v) {
                $xreg [] = '\b' . preg_replace('/\s+/', '\s+', preg_quote($v, '#~')) . '\b';
            }
        }
        if (!empty($cake['WORD_OP'])) {
            foreach ($cake['WORD_OP'] as $v) {
                $xreg[] = '\b' . preg_quote($v, '#~') . '\b';
            }
        }
        if (!empty($cake['JUST_OP'])) {
            $cake['JUST_OP'] = array_reverse($cake['JUST_OP']); // это чтобы длинные операции `++` не разбивались на короткие `+`
            foreach ($cake['JUST_OP'] as $v) {
                $xreg[] = preg_quote($v, '#~');
            }
        }
        if (!empty($cake['SYMBOL']) && 0 == ($this->flags & self::ALLOW_COMMA)) {
            $xreg[] = '[' . preg_quote($cake['SYMBOL'], '#~') . ']';
        }

        // вставляем в регулярку операции и зарезервированные слова
        $reg[] = '(' . implode('|', $xreg) . ')';
        $this->types[] = self::TYPE_OPERATION;

        // вставляем в регулярку вещественные
        if (0 != ($this->flags & self::ALLOW_REAL)) {
            $reg[] = '\b(\d\w*(?:\.[\d]+)?(?:E[\+\-][\d]+)?)';
            $this->types[] = self::TYPE_DIGIT;
        }

        // вставляем в регулярку идентификаторы
        if (0 != ($this->flags & self::ALLOW_ID)) {
            $reg [] = '\b([_a-z][\w_]*)';
            $this->types[] = self::TYPE_ID;
        } else if (!empty($this->tagreg)) { // вставляем в регулярку операнды
            $reg[] = '(' . $this->tagreg . ')';
            $this->types[] = self::TYPE_ID;
        }

        if (0 != ($this->flags & self::ALLOW_COMMA)) {
            $reg[] = '(.)'; #8 - однобуквенные знаки препинания - TYPE_COMMA
            $this->types[] = self::TYPE_COMMA;
        }

        $this->sintaxreg = '#[\n\r\s]*(?:' . implode('|', $reg) . ')#si';
        $this->log('reg:' . $this->sintaxreg);
        $this->option_compiled = true;
    }

    /**
     * Вот так мы боремся со скобочками.
     * @param $op
     * @param int $type
     * @param bool $unop
     * @internal param $opstack
     */
    protected function pushop($op, $type = self::TYPE_NONE, $unop = false)
    {
        if (is_string($op))
            $op = new operand($op, $type);
        if ($unop) {
            $op->unop = 1;
        }
        if (!$op->prio){
            $op->prio = $unop ? 10 : $this->operation[$op->val];
        }
        if ($op->val == '(') {
            $op->depth = count($this->syntax_tree);
        }
        while (!empty($this->op) && $op->val != '(') {
            $past = array_pop($this->op);
            if ($past->val == '(' && $op->val == ')') {
                //array_pop($this->op);
                $x = array();
                while (count($this->syntax_tree) > $past->depth) $x[] = array_pop($this->syntax_tree);
                while ($x) $this->ex_stack[] = array_pop($x);
                return;
            }
            if ($op->prio <= $past->prio && $past->val != '(') {
                $this->syntax_tree[] = $past;
                $this->execute();
            } else {
                $this->op[] = $past;
                break;
            }
        }
        if ($op->val != ')') {
            $this->op[] = $op;
        }
    }

    protected function getcode()
    {
    }

    protected function getnext()
    {
        if (count($this->tag_stack) > 0) {
            return $this->currenttag = array_shift($this->tag_stack);
        }
        $tag = false;
        $this->getcode();
        if (preg_match($this->sintaxreg, $this->code, $m, PREG_OFFSET_CAPTURE, $this->start)) {
            // $this->log('found:'.json_encode($m).$this->start);
            if ($this->start != $m[0][1]) {
                $this->log('error:' . json_encode($m[0]) . $this->start);
                $this->error(sprintf('[%d:%d] ', $this->xstart + $this->start, $this->xstart + $m[0][1] - $this->start));
            }
            $k = count($m) - 1;
            $type = $this->types[$k];
            $tag = $m[$k][0];
            if ($type == 0)
                for ($k=0;$k < count($m);$k++) {
                    $v=$this->types[$k];
                    if (0 != $v) {
                        if ("" !== $m[$k][0]) {
                            $type = $v;
                            $tag = $m[$k][0];
                            break;
                        }
                    }
                }
            if ($type == self::TYPE_STRING) {
                $tag = $this->oper(stripslashes($tag), self::TYPE_STRING);
            } else if (0 == ($this->flags & self::CASE_SENCITIVE)) {
                $tag = $this->oper(strtolower($tag), array('type' => $type));
            }
            //$tag = $m[1][0];
            $this->start = $m[0][1] + strlen($m[0][0]);
            $tag->start = $this->xstart + $this->start;
            //$tag = new operand($tag, $type, $this->xstart + $this->start);
        }
        return $this->currenttag = $tag;
    }

    /**
     * транслируем в обратную польскую форму
     * @param array $stopwords
     * @return operand
     */
    function getExpression($stopwords = array())
    {
        $lastop = count($this->op);
        $this->pushop('(');
        $place_operand = true;
        while (false !== ($tag = $this->getnext())) {
            if ($tag->type == rpn_class::TYPE_STRATCH) {
                $tag->type = rpn_class::TYPE_STRING;
                $this->syntax_tree[] = $tag;
                $this->syntax_tree[] = $this->oper('_scratch_', rpn_class::TYPE_OPERATION);
                $this->execute();
            } else {
                if (!empty($stopwords) && in_array($tag->val, $stopwords)) {
                    break;
                }
                $continue=true;
                if($tag->type==self::TYPE_COMMA){
                    $continue=false;
                    switch ($tag->val) {
                        case '(':
                            if (!$place_operand && 0 != (self::EMPTY_FUNCTION_ALLOWED & $this->flags)) {
                                $this->pushop('_EMPTY_');
                            }
                            if (!$place_operand) { // it's a call
                                $xop = $this->oper('[]', array('list' => array(), 'type' => rpn_class::TYPE_LIST));
                                $xop->val = $this->get_Parameters_list('=');
                                if ($this->currenttag->val != ')')
                                    $this->error('missed )');
                                $this->syntax_tree[] = $xop;
                                $this->syntax_tree[] = $this->oper('_call_', rpn_class::TYPE_OPERATION);
                                $this->execute();
                            } else if ((string)$this->getExpression(array(')')) != ')')
                                $this->error('unclosed  parenthesis_0');
                            $place_operand = false;
                            break;
                        case '{': // это - изображение ассоциативного массива
                            // выбираем список
                            $xop = $this->oper('[]', array('list' => array(), 'type' => rpn_class::TYPE_LIST));
                            $xop->val = $this->get_Parameters_list(':',true);
                            //$num=$this->get_Comma_separated_list();
                            if ($this->currenttag->val != '}'){
                                $this->error('missed }');
                            }
                            $this->syntax_tree[] = $xop;
                            if (!$place_operand) { // вырезка из массива
                                $this->error('improper place for { array }');
                            }
                            $this->execute();
                            break;
                        case '[': // это - начало скобок
                            // выбираем список
                            $xop = $this->oper('[]', array('list' => array(), 'type' => rpn_class::TYPE_LIST));
                            $xop->val = $this->get_Parameters_list(':');
                            //$num=$this->get_Comma_separated_list();
                            if ($this->currenttag->val != ']')
                                $this->error('missed ]');

                            $this->syntax_tree[] = $xop;
                            if (!$place_operand) { // вырезка из массива
                                $this->syntax_tree[] = $this->oper('_scratch_', rpn_class::TYPE_OPERATION);
                            }
                            $this->execute();
                            break;
                        case ')':
                            break 2;
                        case ']':
                            break 2;
                        case '}':
                            break 2;
                        case ';':
                            break 2;
                        case ',':
                            break 2;
                        default:
                            $continue=true;
                    }
                }
                while($continue){
                    $continue=false;
                    if ($tag->val === '' && $tag->type == rpn_class::TYPE_NONE) {
                        break;
                    }
                    if ($tag->type==rpn_class::TYPE_STRING){
                            if (!$place_operand && (0 != (self::EMPTY_FUNCTION_ALLOWED & $this->flags))) { // если операции нет - савим пустую операцию
                                $this->pushop('_EMPTY_');
                            }
                            $this->syntax_tree[] = $tag;
                            $place_operand = false;
                    } else if (isset($this->reserved_words[$tag->val])) {
                        if ($this->reserved_words[$tag->val] == -2) {
                            // стоп-слово
                            break 2;
                        }
                        if ($place_operand) {
                            // будет вызов
                            $parcount = 0;
                            $_xR = $this->reserved_words[$tag->val];
                            if ($_xR != 0) {
                                if ('(' == $this->getnext()) {
                                    $parcount = 1;
                                    $last = count($this->ex_stack);
                                    while (',' == ($x = (string)$this->getExpression())) {
                                        /*  //todo: repair
                                        if($this->canexecute) {
                                             $this->execute();
                                             if($parcount + $last!=count($this->ex_stack)){
                                                 $this->error('something wrong with parameters');
                                             } else {
                                                 $parcount++;
                                             }
                                         } else*/
                                        $parcount++;
                                    }
                                    if ($x != ')')
                                        $this->error('unclosed parenthesis_1');
                                    if ($_xR > 0 && $_xR != $parcount)
                                        $this->error('wrong parameters count');
                                }
                            }
                            $tag->call = true;
                            $tag->parcount = $parcount;
                            $this->syntax_tree[] = $tag;
                            $place_operand = false;
                        }
                    } else if (($_xU = isset($this->unop[$tag->val])) && $place_operand) {
                        $this->pushop($tag, self::TYPE_OPERATION, true);
                    } else if (($_xS = isset($this->suffix[$tag->val])) && !$place_operand) {
                        $tag->unop = 2;
                        $this->syntax_tree[] = $tag;
                        $this->execute();
                        //$this->syntax_tree[] = $tag;
                    } else if (($_xO = isset($this->operation[$tag->val])) && !$place_operand) {
                        if ($tag->type == rpn_class::TYPE_COMMA)
                            $tag->type = rpn_class::TYPE_OPERATION;
                        $this->pushop($tag);
                        $place_operand = true;
                    } else if (($_xf = isset($this->func[$tag->val])) && $place_operand) {
                        $xf = $this->func[$tag->val];
                        if (is_callable($xf->val)) {
                            $tag->handler = $xf->val;
                            $tag->type = rpn_class::TYPE_OBJECT;
                        } else if ($tag->type != rpn_class::TYPE_COMMA)
                            $tag->type = rpn_class::TYPE_COMMA;
                        $this->syntax_tree[] = $tag;
                        $this->execute();
                        $place_operand = false;
                    } else {
                        if (($_xO || $_xS || $_xU) && $tag->type == self::TYPE_OPERATION) {
                            $this->error(sprintf('improper place for `%s`', $tag));
                        }

                        if (!$place_operand && (0 != (self::EMPTY_FUNCTION_ALLOWED & $this->flags))) { // если операции нет - савим пустую операцию
                            $this->pushop('_EMPTY_');
                        }
                        $this->syntax_tree[] = $tag;
                        $place_operand = false;
                    }
                }
            }
        }
        $this->pushop(')');

        if ($lastop != count($this->op)) {
            $this->error('oops!');
        }
        return $tag;
    }

    /**
     * evaluate, вроде как
     * @param string $code
     * @return mixed
     */
    function ev($code = '') //, $evaluateTag = null, $executeOp = null)
    {
        if (!$this->option_compiled) {
            $this->compile_options();
        }

        $this->errors = array();
        $this->start = 0;
        $this->xstart = 0;
        if (!is_array($code))
            $this->code = $code;
        $this->syntax_tree = array();
        $this->ex_stack = array(); // стек операндов
        if (is_array($code))
            call_user_func(array($this, $code[0]), $code[1], $code[2]);
        else
            $this->getExpression();
        if (0 < strlen($this->pastcode)) {
            $this->log('xxx:' . ($this->xstart + $this->start));
            $this->error(sprintf('[%d:%d] ', $this->xstart + $this->start, $this->xstart + strlen($code) - $this->start));
        }
        // вычисляем
        $this->execute();
        if (count($this->ex_stack) < 1) {
            $this->error('something gose wrong.');
            return null;
        } else if (count($this->ex_stack) > 1) {
            $this->error('something gose wrong!');
        }
        return $this->execTag(array_pop($this->ex_stack));
    }

    protected function execOp($op, $_1, $_2, $unop = 0)
    {
        if (is_null($this->evaluateTag) || is_null($this->executeOp)) {
            $this->error('Не указаны callback обработчики');
            return false;
        }
        return call_user_func($this->executeOp, $op, $_1, $_2, $this->evaluateTag, $unop);
    }

    protected function execTag($op)
    {
        if (is_null($this->evaluateTag)) {
            $this->error('Не указаны callback обработчики(1)');
            return false;
        }
        return call_user_func($this->evaluateTag, $op);
    }

    protected function execute()
    {
        while (!empty($this->syntax_tree)) {
            $op = array_shift($this->syntax_tree);
            if ($op->call) {
                $param = array();
                for ($i = 0; $i < $op->parcount; $i++) {
                    array_unshift($param, array_pop($this->ex_stack));
                }
                $this->ex_stack[] = $this->execOp($op, false, $param, true);
            } elseif (rpn_class::TYPE_OPERATION != $op->type) { // это не операция
                $this->ex_stack[] = $op;
            } else if ($op->val != '') {
                if ($op->unop) {
                    // унарные операции
                    $this->ex_stack[] = $this->execOp($op, false, array_pop($this->ex_stack), true);
                } else { // бинарные операции
                    $_2 = array_pop($this->ex_stack);
                    $this->ex_stack[] = $this->execOp($op, array_pop($this->ex_stack), $_2);
                }
            }
        }
    }

    /**
     * Создание нового операнда. Для возможности переопределения класса операнда
     * Единственное место, где встречается слово operand - имя класса
     */
    function oper($value, $type = self::TYPE_NONE, $pos = 0)
    {
        if (is_array($type)) {
            $op = new operand($value);
            foreach ($type as $k => $v)
                $op->$k = $v;
            return $op;
        } else
            return new operand($value, $type, $pos);
    }

    /**
     * вырезка из массива
     * @param operand $op1
     * @param operand $op2
     * @return \operand
     */
    function _scratch_($op1, $op2)
    {
        //вырезка из массива
        /*  if(isset($this->func[$op1->val])){
              $op1->attr=$op1->attr.'.'.$op2->val;
              $op1->type=self::TYPE_OBJECT;
              return $op1;
          }*/
        if ($op1->val == '_self' || $op1->val == 'self') return $op2;

        if ('' == $op1->list) $op1->list = array($this->oper($op1->val, $op1->type));
        if ($op2->type == rpn_class::TYPE_LIST) {
            $op1->list = array_merge($op1->list, $op2->val['keys']);
        } else {
            $op1->list[] = $op2;
        }
        $op1->type = self::TYPE_SLICE;
        return $op1;
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
        $this->error('Calling undefined function is not allowed');
        return $op1;
    }


    function popOp()
    {
        // $this->execute();
        if (count($this->ex_stack) > 0)
            return array_pop($this->ex_stack);
        else
            $this->error('No elements to pop');
        return false;
    }

    function back($tag = null)
    {
        $this->tag_stack[] = is_null($tag) ? $this->currenttag : $tag;
    }

    /**
     * список параметров через запятую, с именами, разделенных = или :
     * Возвращает асссоциативный массив
     */
    protected function get_Parameters_list($sign = ':',$keyasstring=false)
    {
        $arr = array();
        $keys = array();
        $keytypes=array(rpn_class::TYPE_ID /*,rpn_class::TYPE_STRING3*/ );
        if($keyasstring){
            $keytypes[]=rpn_class::TYPE_STRING;
            $keytypes[]=rpn_class::TYPE_STRING1;
            $keytypes[]=rpn_class::TYPE_STRING2;
        }

        do {
            $this->getNext();
            if (in_array($this->currenttag->type, $keytypes)) { //todo:! Сделать string3
                $id = $this->currenttag;
                $this->getNext();
                if ($this->currenttag->val == $sign) {
                    //слопали ключ!
                    $this->getExpression();
                    $keys[] = $id;
                    $arr[] = $this->popOp();
                } elseif ($this->currenttag->val == ',') {
                    //    $this->back();
                    $keys[] = $id;
                    $arr[] = null;
                } else {
                    $this->back($id);
                    $this->back();
                    $this->getExpression();
                    $keys[] = $this->popOp();
                    $arr[] = null;
                }
            } else { // it's a name
                $this->back();
                $opdepth = count($this->ex_stack);
                $this->getExpression();
                if ($opdepth < count($this->ex_stack)) {
                    $keys[] = $this->popOp();
                    $arr[] = null;
                }
            }

            if ($this->currenttag->val != ',' || $this->currenttag->type!=self::TYPE_COMMA) {
                break;
            }
        } while (true);
        return array('value' => $arr, 'keys' => $keys);
    }

}

/**
 * внутренний базовый класс для хранения всякой неведомой зверушки
 * @property string call
 * @property string parcount
 */
class operand
{
    var $val // значение операнда
    , $type // тип операнда
    , $pos // позиция курсора
    , $unop
    , $call
    , $parcount
    , $depth
    , $prio;

    function __set($name, $val)
    {
        // echo $name.' ';
        $this->$name = $val;
    }

    function __get($name)
    {
        return '';
    }

    /**
     * @param string $val
     * @param int $type
     * @param int $pos
     */
    function __construct($val, $type = rpn_class::TYPE_NONE, $pos = 0)
    {
        $this->val = $val;
        $this->type = $type;
        $this->pos = $pos;
    }

    function __toString()
    {
        return $this->val;
    }

}