<?php

/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 13:53
 *
 */
class rpn_WrongDataTest extends PHPUnit_Framework_TestCase
{

    /**
     * проверка строительства дерева синтаксиса +
     * массовых вычислений. не остается ли грязи между итерациями?
     */
    function testBuildingSyntaxTree()
    {
        $r = new rpn_class();
        $r->option(array(
            'flags' => rpn_class::EMPTY_FUNCTION_ALLOWED
  //      | 12
        ,
            'operation' => ['and' => 3, 'or' => 3, 'not' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['not' => 3],
        ));
        foreach ([
                     'Hello world!' =>['["[0:12] "]','[]'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['["[1:5] ","[8:7] ","[26:7] "]','[{"data":"1","type":12},{"data":"2","type":12},{"op":"_EMPTY_"},{"data":"3","type":12},{"op":"_EMPTY_"},{"data":"4","type":12},{"op":"OR"},{"op":"NOT"}]'],
            '((172* 501*))))))))) not 128'=>['["[14:14] "]','[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"}]'],
                     '((((((((172* 501*))) not 128'=>['["unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0"]','[{"data":"172","type":12},{"op":"*","unop":2},{"data":"501","type":12},{"op":"*","unop":2},{"op":"_EMPTY_"},{"data":"128","type":12},{"op":"NOT"}]'],
                 ] as $k => $v) {
            $result=$r->ev($k,false);
            $this->assertEquals($v[0], json_encode($r->error()));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result));
        }
    }

    /**
     * Обработка ошибок с исключениями, в сравнении с обычной работой над ошибками
     */
    function testBuildingSyntaxTreeWithException()
    {
        $r = new rpn_class();
        $r->option(array(
             'flags' =>rpn_class::EMPTY_FUNCTION_ALLOWED
                 +rpn_class::THROW_EXCEPTION_ONERROR,
            'operation' => ['and' => 3, 'or' => 3, 'not' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['not' => 3],
        ));
        foreach ([
                     'Hello world!' =>['"[0:12] "','""'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['"[1:5] "','""'],
                     '((172* 501*))))))))) not 128'=>['"[14:14] "','""'],
                     '((((((((172* 501*))) not 128'=>['"unclosed  parenthesis_0"','""'],
                     '12* 12* * not or or 4'=>['"improper place for `OR`"','""'],
                 ] as $k => $v) {
            $mess='';$result='';
            try{
                $result=$r->ev($k,false);
            } catch(Exception $e){
                $mess=$e->getMessage();
            }
            $this->assertEquals($k . "\n" .$v[0], $k . "\n" .json_encode($mess));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result));
        }
    }
}
 