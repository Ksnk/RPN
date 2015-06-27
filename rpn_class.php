<?php

/**
 * полностью кастомизируемый класс для анализа скобочной записи и трансляции ее
 * в обратную прольскую форму
 * с вызовами и определяемыми юзером операциями
 *
 * И никакой статики, Карл...
 */
class rpn_class
{

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
    , TYPE_SLICE = 18;

    const
        THROW_EXCEPTION_ONERROR = 1,
        // STOP_ONERROR = 2, // не пригодился
        SHOW_ERROR = 4,
        SHOW_DEBUG = 8,
        EMPTY_FUNCTION_ALLOWED = 16,

        ALLOW_STRINGS = 32, // допускаются операнды  - строки
        ALLOW_REAL = 64, // допускаются операнды - вещественные числа.
        ALLOW_ID = 128, // допускаются операнды - неописанные идентификаторы
        ALLOW_COMMA = 256; // допускаются неописанные знаки препинания (,;)

    var $flags = 0; // 1 exception, 2-stop on error, 4-error, 8-debug
    /**
     * массив операций, суффиксов и унарных. По нему будем строить регулярку
     */
    protected $operation = array();
    protected $suffix = array();
    protected $unop = array();

    /**
     * массив зарезервированных слов. По нему будем строить регулярку
     * Зарезервированные слова используются в качестве предопределенных функций
     *  'PI'=>0,'NOW'=>0 - слово-функция
     *  'SIN'=>1 - функция с одним параметром
     *  'EXP'=>2 - функция с 2-мя параметрами
     *  'ECHO'=>-1 - функция с неопределенным количеством параметров
     * Все зарезервированные слова обязаны обрабатываться evalTag'ом
     */
    protected $reserved_words = array();

    /**
     * имя класса для вызова исключения
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

    /** @var operand[] */
    protected $op = array();

    private $start = 0;
    private $errors = array();
    private $sintaxreg = '##i';
    private $tagreg = '';
    private $canexecute = false;

    private $option_compiled = false;

    private $ex_stack = array();
    /** @var operand[] */
    private $syntax_tree = array();
    private $types = array();
    private $type = 0;
    private $pastcode = '';
    private $code;

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
    public function error($mess = null)
    {
        if (is_null($mess))
            return $this->errors;

        $this->errors[] = $mess;
        if (0 != ($this->flags & self::THROW_EXCEPTION_ONERROR)) {
            $ex = $this->exception_class_name;
            throw new $ex($mess);
        };
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
                else if (strlen($v) >= 1)
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
        $xreg = array();
        if (!empty($cake['MWORD_OP'])) {
            foreach ($cake['MWORD_OP'] as $v) {
                $xreg [] = '\b' . preg_replace('/\s+/', '\s+', preg_quote($v)) . '\b';
            }
        }
        if (!empty($cake['WORD_OP'])) {
            foreach ($cake['WORD_OP'] as $v) {
                $xreg[] = '\b' . preg_quote($v) . '\b';
            }
        }
        if (!empty($cake['JUST_OP'])) {
            $cake['JUST_OP'] = array_reverse($cake['JUST_OP']); // это чтобы длинные операции `++` не разбивались на короткие `+`
            foreach ($cake['JUST_OP'] as $v) {
                $xreg[] = preg_quote($v);
            }
        }
        if (!empty($cake['SYMBOL']) && 0 == ($this->flags & self::ALLOW_COMMA)) {
            $xreg[] = '[' . preg_quote($cake['SYMBOL']) . ']';
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
            $reg [] = '\b([a-z][\w_]*)';
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
        if (is_string($op)) $op = new operand($op, $type);
        if ($unop) {
            $op->unop = 1;
        }
        $op->prio = $unop ? 10 : $this->operation[$op->val];
        while (!empty($this->op) && $op->val != '(') {
            end($this->op);
            $past =& $this->op[key($this->op)];
            //$past = array_pop($this->op);
            if ($past->val == '(' && $op->val == ')') {
                array_pop($this->op);
                return;
            }
            if ($op->prio <= $past->prio) {
                $this->syntax_tree[] = array_pop($this->op); // means st[]=past
                if ($this->canexecute) $this->execute();
            } else {
//                $this->op[] = $past;
                break;
            }
        }
        if ($op->val != ')') {
            $this->op[] = $op;
        }
    }

    private function getnext()
    {
        $tag = false;
        $type = 0;
        if (preg_match($this->sintaxreg, $this->code, $m, PREG_OFFSET_CAPTURE, $this->start)) {
            // $this->log('found:'.json_encode($m).$this->start);
            if ($this->start != $m[0][1]) {
                $this->log('error:' . json_encode($m[0]) . $this->start);
                $this->error(sprintf('[%d:%d] ', $this->start, $m[0][1] - $this->start));
            }
            foreach ($this->types as $k => $v) {
                if (0 != $v) {
                    if ("" !== $m[$k][0]) {
                        $type = $v;
                        $tag = $m[$k][0];
                        break;
                    }
                }
            }
            if ($type == self::TYPE_STRING) {
                $tag = stripslashes($tag);
            } else {
                $tag = strtoupper($tag);
            }
            //$tag = $m[1][0];
            $this->start = $m[0][1] + strlen($m[0][0]);
            $tag = new operand($tag, $type, $this->start);
        }
        return $tag;
    }

    /**
     * транслируем в обратную польскую форму
     * @return array
     */
    private function LtoP()
    {
        $lastop = count($this->op);
        $this->pushop('(');
        $place_operand = true;
        while (false !== ($tag = $this->getnext())) {
            switch ($tag->val) {
                case '(':
                    if (!$place_operand && 0 != (self::EMPTY_FUNCTION_ALLOWED & $this->flags)) {
                        $this->pushop('_EMPTY_');
                    }
                    $this->pushop('(');
                    if ($this->LtoP() != ')')
                        $this->error('unclosed  parenthesis_0');
                    $this->pushop(')');
                    $place_operand = false;
                    break;
                case ')':
                    break 2;
                case ',':
                    break 2;
                default:
                    if (isset($this->reserved_words[$tag->val]) && $place_operand) {
                        // будет вызов
                        $parcount = 0;
                        $_xR = $this->reserved_words[$tag->val];
                        if ($_xR != 0) {
                            if ('(' == $this->getnext()) {
                                $parcount = 1;
                                $last = count($this->syntax_tree);
                                while (',' == ($x = $this->LtoP())) {
                                    /*  //todo: repair
                                    if($this->canexecute) {
                                         $this->execute();
                                         if($parcount + $last!=count($this->syntax_tree)){
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
                    } else if (($_xU = isset($this->unop[$tag->val])) && $place_operand) {
                        $this->pushop($tag, self::TYPE_OPERATION, true);
                    } else if (($_xS = isset($this->suffix[$tag->val])) && !$place_operand) {
                        $tag->unop = 2;
                        $this->syntax_tree[] = $tag;
                    } else if (($_xO = isset($this->operation[$tag->val])) && !$place_operand) {
                        $this->pushop($tag);
                        $place_operand = true;
                    } else {
                        if ($_xO || $_xS || $_xU) {
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
        $this->pushop(')');

        if ($lastop != count($this->op)) {
            $this->error('oops!');
        }
        return $tag;
    }

    /**
     * evaluate, вроде как
     * @param string $code
     * @param bool $execute
     * @return mixed
     */
    function ev($code, $execute = true) //, $evaluateTag = null, $executeOp = null)
    {
        if (!$this->option_compiled) {
            $this->compile_options();
        }
        $this->canexecute = $execute;

        $this->errors = array();
        $this->start = 0;
        $this->code = $code;
        $this->syntax_tree = array();
        $this->ex_stack = array(); // стек операндов
        $this->LtoP();
        if (0 < strlen($this->pastcode)) {
            $this->log('xxx:' . $this->start);
            $this->error(sprintf('[%d:%d] ', $this->start, strlen($code) - $this->start));
        }
        if (!$execute) {
            return $this->syntax_tree;
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

    private function execute()
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
            } else {
                if ($op->unop) {
                    // унарные операции
                    $this->ex_stack[] = $this->execOp($op, false, array_pop($this->ex_stack));
                } else { // бинарные операции
                    $_2 = array_pop($this->ex_stack);
                    $this->ex_stack[] = $this->execOp($op, array_pop($this->ex_stack), $_2);
                }
            }
        }
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
    , $prio
    ;

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
        $this->val = (string)$val;
        $this->type = $type;
        $this->pos = $pos;
    }

    function __toString()
    {
        return $this->val;
    }

}