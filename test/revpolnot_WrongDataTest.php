<?php
use PHPUnit\Framework\TestCase, Ksnk\rpn\rpn_class;
include_once '../vendor/autoload.php';

/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 13:53
 *
 */
class revpolnot_WrongDataTest extends TestCase
{

    /**
     * Проверка строительства дерева синтаксиса +
     * массовых вычислений. Не остается ли грязи между итерациями?
     */
    function testBuildingSyntaxTree()
    {
        $r = new rpn_class();
        $r->option(array(
            'flags' => rpn_class::EMPTY_FUNCTION_ALLOWED+rpn_class::USE_ARIPHMETIC
  //      | 12
        ,
            'operation' => ['and' => 3, 'or' => 3, 'not' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['not' => 3],
        ));
        foreach ([
                     'Hello world!' =>['["something gose wrong."]','null'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['["[1:5] ","[8:7] ","unknown operation or","unknown operation not","something gose wrong!"]','0'],
            '((172* 501*))))))))) not 128'=>['["uncallable *","uncallable *","something gose wrong!"]','0'],
                     '((((((((172* 501*))) not 128'=>['["uncallable *","uncallable *","unknown operation not","unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0","unclosed  parenthesis_0","something gose wrong!"]','0'],
                 ] as $k => $v) {
            $result=$r->ev($k,false);
            $this->assertEquals($v[0], json_encode($r->error(),JSON_UNESCAPED_UNICODE));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result, JSON_UNESCAPED_UNICODE));
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
                 +rpn_class::THROW_EXCEPTION_ONERROR+rpn_class::USE_ARIPHMETIC,
            'operation' => ['and' => 3, 'or' => 3, 'not' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['not' => 3],
        ));
        foreach ([
                     'Hello world!' =>['"something gose wrong."','""'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['"[1:5] "','""'],
                     '((172* 501*))))))))) not 128'=>['"uncallable *"','""'],
                     '((((((((172* 501*))) not 128'=>['"uncallable *"','""'],
                     '12* 12* * not or or 4'=>['"uncallable *"','""'],
                 ] as $k => $v) {
            $mess='';$result='';
            try{
                $result=$r->ev($k,false);
            } catch(Exception $e){
                $mess=$e->getMessage();
            }
            $this->assertEquals($k . "\n" .$v[0], $k . "\n" .json_encode($mess, JSON_UNESCAPED_UNICODE));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
    }
}
 