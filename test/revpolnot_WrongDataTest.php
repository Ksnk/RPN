<?php

/**
 * Created by PhpStorm.
 * User: —ергей
 * Date: 08.06.15
 * Time: 13:53
 */
class revpolnot_WrongDataTest extends PHPUnit_Framework_TestCase
{

    /**
     * проверка строительства дерева синтаксиса +
     * массовых вычислений. не остаетс€ ли гр€зи между итераци€ми?
     */
    function testBuildingSyntaxTree()
    {
        $r = new revpolnot_class();
        $r->option(array(
            // 'flags' => 12,
            'operation' => ['AND' => 3, 'OR' => 3, 'NOT' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['NOT' => 3],
        ));
        foreach ([
                     'Hello world!' =>['["[0:12] "]','[]'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['["[1:5] ","[8:7] ","[26:7] "]','[{"data":"1"},{"data":"2"},{"op":"_EMPTY_"},{"data":"3"},{"op":"_EMPTY_"},{"data":"4"},{"op":"OR"},{"op":"NOT"}]'],
            '((172* 501*))))))))) not 128'=>['["[14:14] "]','[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"}]'],
                     '((((((((172* 501*))) not 128'=>['[]','[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"128"},{"op":"NOT"}]'],
                 ] as $k => $v) {
            $result=$r->ev($k);
            $this->assertEquals($v[0], json_encode($r->error()));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result));
        }
    }

    /**
     * ќбработка ошибок с исключени€ми, в сравнении с обычной работой над ошибками
     */
    function testBuildingSyntaxTreeWithException()
    {
        $r = new revpolnot_class();
        $r->option(array(
             'flags' =>0
                 +revpolnot_class::THROW_EXCEPTION_ONERROR,
            'operation' => ['AND' => 3, 'OR' => 3, 'NOT' => 3],
            'suffix' => ['*' => 3],
            'tagreg' => '\b(\d+)\b',
            'unop' => ['NOT' => 3],
        ));
        foreach ([
                     'Hello world!' =>['"[0:12] "','null'],
                     '1 andx 2 notand 3 or 4 not to be!'=>['"[1:5] "','null'],
                     '((172* 501*))))))))) not 128'=>['"[14:14] "','null'],
                     '((((((((172* 501*))) not 128'=>['""','[{"data":"172"},{"op":"*","unop":true},{"data":"501"},{"op":"*","unop":true},{"op":"_EMPTY_"},{"data":"128"},{"op":"NOT"}]'],
                 ] as $k => $v) {
            $mess='';
            try{
                $result=$r->ev($k);
            } catch(Exception $e){
                $mess=$e->getMessage();
            }
            $this->assertEquals($k . "\n" .$v[0], $k . "\n" .json_encode($mess));
            $this->assertEquals($k . "\n" . $v[1], $k . "\n" . json_encode($result));
        }
    }
}
 