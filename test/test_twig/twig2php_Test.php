<?php

/**
 * тестовый наследник - для проверки наследования шаблонов
 * Class tpl_test
 */
if(!class_exists('tpl_test')){
class tpl_test extends tpl_base
{

    function __construct()
    {

    }

    function _($par = 0)
    {
        return $this->_test($par) . ' Calling parent_ method';
    }

    function _test(&$par)
    {
        return 'Calling parent test! Ok! ';
    }
}

    class engine
    {
        function export($class, $method, $par1 = null, $par2 = null, $par3 = null)
        {
            return sprintf('calling %s::%s(%s)', $class, $method, array_diff(array($par1, $par2, $par3), array(null)));
        }

        function test($a, $b, $c)
        {
            return $a . '+' . $b . '+' . $c;
        }
    }

    $GLOBALS['engine'] = new engine();
}

class sample_test_object {
    var $xxx='yyy';

    function the_method($a,$b,$c=3){
        return $a.' '.$b.' '.$c;
    }
}

/**
 * Проверка прямого функционала шаблонизатора. Компилируем - выполняем
 *
 * //method assertEquals - злой phpstorm не видит моего PHPUnit и обижаеццо
 */
class twig2php_Test extends PHPUnit_Framework_TestCase
{

    var $errors='';

    function compress($s)
    {
        return preg_replace('/\s+/s', ' ', trim($s));
    }

    function compilerX($src,$par=array(),$method='_'){
        static $cnt=0,$r=null;
        if(is_null($r))$r= new twig2php_class();
        $this->errors=false;
        $cnt++;
        //$r= new twig2php_class();
        $fp = fopen("php://memory", "w+b");
        fwrite($fp, $src);//1024));
        rewind($fp);
        $r->handler=$fp;
        $res=''.$r->ev(array('tplcalc','compiler','test'.$cnt));
        $this->errors=$r->error();
        if($this->errors) print_r($this->errors);
        eval('?>'. $res);
        $classname='tpl_test'.$cnt;
        //echo $data;
        fclose($fp);
        if(class_exists($classname)) {
            $base= new $classname();
            $x=$base->$method($par);
            return $x;
        } else
            return '';
    }

    /**
     * условное предложение, вырезка из массива
     */
    function test0()
    {
        $data = array('user' => array('right' => array('*' => 1027)), 'xright' => array(1 => 1027));
        $s = '{%if not user.right["*"] %}1{%endif-%}
{%if not xright["*"] %}2{%endif-%}
{%if not xright[1] %}3{%endif%}';
        $pattern = '2';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    /**
     * обработка комментариев
     */
    function testLineComment()
    {
        $this->assertEquals($this->compilerX('
####################################################################
##
##  файл шаблонов для шаблонизатора
##
####################################################################

####################################################################
## class
##
{#- -#}
Привет {{title-}}! ',array('title'=>'Мир')), 'Привет Мир! ');
    }


    /**
     * текст c простой вставкой параметра
     */
    function testPar()
    {
        $this->assertEquals($this->compilerX('Привет {{title}}! ',array('title'=>'Мир')), 'Привет Мир! ');
    }

    /**
     * длинный текст без twig операторов
     */
    function testCreateAndRun()
    {
        $src=str_repeat('Привет мир!',1024*16);
        $this->assertEquals($this->compilerX($src), $src);
    }

    /**
     * проверка тега macro, оператора склейки, вызова макры с указанием имени параметра
     */
    function test_test23()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% macro fileman(list=1,pages=1,type,filter) -%}
{{list~pages~type~filter}}
        {%- endmacro -%}
{{fileman()}} {{fileman(pages=3)}} {{fileman(1,2,3)}}';
        $pattern = '1100 1300 1230';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    /**
     * вложенные IF
     */
    function test_test26(){
        $s='{% if pages -%}
<div class="paging">
        {%- set maxnumb= (pages.total) // pages.perpage
           set start = pages.page-3
           if start>0 -%}
            <a href="{{pages.url}}page=prev">&lt;&lt;</a>&nbsp;
        {%- endif -%}{%- endif -%}';
        $this->assertEquals(
            $this->compilerX($s, array('pages' => array(
                'total' => 144,
                'perpage' => 15,
                'url' => "http:xxx.com/xxx?",
                'page' => 5,
            ))), '<div class="paging"><a href="http:xxx.com/xxx?page=prev">&lt;&lt;</a>&nbsp;'
        );
    }

    function test_test334(){
        $s='<tr style="border:none;"><td colspan="{{ colspan -2}}" height="10"></td></tr>';
        $data = array('main' => $GLOBALS['engine'], 'colspan' => '10');
        $pattern = '<tr style="border:none;"><td colspan="8" height="10"></td></tr>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    /** test extends-parent  */

    function test_test27()
    {
        $s = '##
##   Генерация страничной адресации
##
{%- macro paging(pages)-%}
{% if pages -%}
<div class="paging">
        {%- set maxnumb= (pages.total) // pages.perpage
           set start = pages.page-3
           if start>0 -%}
            <a href="{{pages.url}}page=prev">&lt;&lt;</a>&nbsp;
        {%- endif -%}

        {%- for xpage in range(7,1) -%} {% set page=start+xpage -%}
        {% if page>0 and page <= maxnumb -%}
        {% if page == pages.page -%}
        <span>{{page}}</span>&nbsp;
        {%- else -%}
        <a href="{{pages.url}}page={{page}}">{{page}}</a>&nbsp;
        {%-  endif endif  endfor -%}
        {%- if page < maxnumb -%}
            <a href="{{pages.url}}?page=next">&gt;&gt;</a>&nbsp;
        {%- endif -%}
        {%- if pages.total%}<span>Всего: {{pages.total}}</span>{% endif -%}
        </div>
{%- endif -%}
{% endmacro -%}';
        $pattern = '<div class="paging"><a href="http:xxx.com/xxx?page=prev">&lt;&lt;</a>&nbsp;<a href="http:xxx.com/xxx?page=2">2</a>&nbsp;<a href="http:xxx.com/xxx?page=3">3</a>&nbsp;<a href="http:xxx.com/xxx?page=4">4</a>&nbsp;<span>5</span>&nbsp;<a href="http:xxx.com/xxx?page=6">6</a>&nbsp;<a href="http:xxx.com/xxx?page=7">7</a>&nbsp;<a href="http:xxx.com/xxx?page=8">8</a>&nbsp;<a href="http:xxx.com/xxx??page=next">&gt;&gt;</a>&nbsp;<span>Всего: 144</span></div>';
        $this->assertEquals(
            $this->compilerX($s, array('pages' => array(
                'total' => 144,
                'perpage' => 15,
                'url' => "http:xxx.com/xxx?",
                'page' => 5,
            )),'_paging'), $pattern
        );
    }

    function test00(){
        $data = array('data' => '<<<>>>');
        $s = '
    {% extends "test.php"%}
    {% block test %} <table>
        {% for x in [1,2] %}
        <tr class="{{loop.cycle(\'odd\',\'even\')}}"><td>{{x}}</td><td>
        one</td><td>two</td></tr>
        {% endfor %}
        </table> {{parent()}}{% endblock %}
        {{test()}} ';
        $pattern = ' <table>
        <tr class="odd"><td>1</td><td>
        one</td><td>two</td></tr>
        <tr class="even"><td>2</td><td>
        one</td><td>two</td></tr>
        </table> Calling parent test! Ok! ';

        $this->assertEquals($this->compilerX($s,$data,'_test'), $pattern);
    }

    function testCallObject()
    {
        $data = array('main' => $GLOBALS['engine'], 'data' => '<<<>>>');
        $s = '
        {{ main.test (1,2,3) }} ';
        $pattern = '
        1+2+3 ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test16()
    {
        $data = array('data' => array());
        $s = '{% macro input(name, value=\'\', type=\'text\', size=20) -%}
    <input type="{{ type }}" name="{{ name }}" value="{{
        value|e }}" size="{{ size }}">
{%- endmacro -%}
<p>{{ input(\'username\') }}</p>
<p>{{ input(\'password\', type=\'password\') }}</p>';
        $pattern = '<p><input type="text" name="username" value="" size="20"></p>
<p><input type="password" name="password" value="" size="20"></p>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test13()
    {
        $data = array();
        $s = '
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {{ loop.index }}{{ item }}{{ loop.revindex }}{{ item }}
{%- endfor %}';
        $pattern = '1on\\e\'s 2on\\e\'s 2one"s 1one"s ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test25()
    {
        $data = array(
            'topics' => array(
                'topic1' => array('Message 1 of topic 1', 'Message 2 of topic 1'),
                'topic2' => array('Message 1 of topic 2', 'Message 2 of topic 2'),
            ));
        $s = '{% for topic, messages in topics %}
       * {{ loop.index }}: {{ topic }}
     {%- for message in messages %}
         - {{ loop.parent.loop.index }}.{{ loop.index }}: {{ message }}
     {%- endfor %}
   {%- endfor %}';
        $pattern = '
       * 1: topic1
         - 1.1: Message 1 of topic 1
         - 1.2: Message 2 of topic 1
       * 2: topic2
         - 2.1: Message 1 of topic 2
         - 2.2: Message 2 of topic 2';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test18()
    {
        $data = array('data' => '<<<>>>');
        $s = ' <table>
	{%- for x in [1,2] %}
	<tr class="{{loop.cycle(\'odd\',\'even\')}}"><td>{{x}}</td><td>
	one</td><td>two</td></tr>
	{%- endfor %}
	</table>';
        $pattern = ' <table>
	<tr class="odd"><td>1</td><td>
	one</td><td>two</td></tr>
	<tr class="even"><td>2</td><td>
	one</td><td>two</td></tr>
	</table>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test1()
    {
        $data = array('iff' => "'hello'", 'then' => 'world');
        $s = 'if( {{iff}} ){ {{then }} };';
        $this->assertEquals(
            $this->compilerX($s, $data),
            'if( \'hello\' ){ world };'
        );
    }

    function test_test10()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test
        #}

        {% for item in [1,2,3,4,5,6,7,8,9] -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    /**
     * тесты на тег FOR
     */
    function test_test12()
    {
        $data = array();
        $s = '
        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {% if loop.first %}{{ item }}{% endif -%}
    {% if loop.last %}{{ item }}{% endif -%}
    {{ item }}
{%- endfor %}';
        $pattern = 'on\\e\'s on\\e\'s one"s one"s ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test101()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test
        #}

        {%- for item in ["a".."f",1..9] -%}
    {{ item }}
{%- endfor %}';
        $pattern = 'abcdef123456789';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function testLipsum()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = '{{ lipsum(1,0,10,10)}}';
        $pattern = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi malesuada ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function test_test2()
    {
        $data = array('user' => array('username' => '111')); //,array('username'=>'one'),array('username'=>'two')));
        $s = 'hello {{ user.username }}!';
        $this->assertEquals(
            $this->compilerX($s, $data),
            'hello 111!'
        );
    }

    function test_test3()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
<ul>
{%- for user in users %}
  <li>{{ user.username|e }}</li>
{%- endfor %}
</ul>';
        $pattern = '<h1>Members</h1>
<ul>
  <li>one</li>
  <li>two</li>
</ul>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test4()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
<ul>
    {%- for user in users %}
  <li>{{ user.username|e }}</li>
{%- endfor %}
</ul>';
        $pattern = '<h1>Members</h1>
<ul>
  <li>one</li>
  <li>two</li>
</ul>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test5()
    {
        $data = array('users' => array(array('username' => 'one'), array('username' => 'two')));
        $s = '<h1>Members</h1>
   <ul>
    {%- for user in users -%}
  <li>{{ user.username|e }}</li>
{%- endfor -%}
</ul>';
        $pattern = '<h1>Members</h1>
   <ul><li>one</li><li>two</li></ul>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test6()
    {
        $data = array(
            'navigation' => array(array('href' => 'one', 'caption' => 'two'), array('href' => 'one', 'caption' => 'two'), array('href' => 'one', 'caption' => 'two')),
            'a_variable' => 'hello!',
        );
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="en">
<head>
    <title>My Webpage</title>
</head>
<body>
    <ul id="navigation">
    {%- for item in navigation %}
        <li><a href="{{ item.href }}">{{ item.caption }}</a></li>
    {%- endfor %}
    </ul>

    <h1>My Webpage</h1>
    {{ a_variable }}
</body>
</html>';
        $pattern = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html lang="en">
<head>
    <title>My Webpage</title>
</head>
<body>
    <ul id="navigation">
        <li><a href="one">two</a></li>
        <li><a href="one">two</a></li>
        <li><a href="one">two</a></li>
    </ul>

    <h1>My Webpage</h1>
    hello!
</body>
</html>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test7()
    {
        $data = array('seq' => array(1, 2, 3, 4, 5, 6, 7, 8, 9));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{% for item in seq -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test8()
    {
        $data = array('seq' => array(1, 2, 3, 4, 5, 6, 7, 8, 9));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test
        #}

        {% for item in seq -%}
    {{ item }}
{%- endfor %}';
        $pattern = '123456789';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test9()
    {
        $data = array('foo' => array('bar' => 'xxx'));
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s1 = ' {{ foo.bar }} ';
        $s2 = ' {{ foo[\'bar\'] }} ';

        $pattern = ' xxx ';
        $this->assertEquals(
            $this->compilerX($s1, $data), $pattern
        );
        $this->assertEquals(
            $this->compilerX($s2, $data), $pattern
        );
    }

    function test_test11()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{#
        it\'s a test
        #}

        {%- for item in ["on\\\\e\'s ","one\"s "] -%}
    {{ item }}
{%- endfor %}';
        $pattern = 'on\\e\'s one"s ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test14()
    {
        $data = array('data' => array());
        $s = '
        {%- for item in data -%}
    {{ loop.index }}{{ item }}{{ loop.revindex }}{{ item }}
    {% else -%}
    nothing
{%- endfor %}';
        $pattern = 'nothing';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function test_test17()
    {
        $data = array('data' => '<<<>>>');
        $s = ' <p>{{data|e|default(\'nothing\')}}</p>';
        $pattern = ' <p>&lt;&lt;&lt;&gt;&gt;&gt;</p>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test19()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% set ZZZ=[\'Табличная форма\',\'Блочная форма\',\'Галерея\'] -%}
		<table class="align_left">{% for x in ZZZ %}<col>{%endfor -%}
		<tr>{% for x in ZZZ -%}
		<td><input type="radio" name="kat_form_{{loop.index}}"><b> {{x}}</b><br>
		<input type="text" class="digit2" name="kat_col_{{loop.index}}"> Кол-во столбцов<br>
		</td>{% endfor -%}
		</tr>
		</table>';
        $pattern = '<table class="align_left"><col><col><col><tr>' .
            '<td><input type="radio" name="kat_form_1"><b> Табличная форма</b><br>
		<input type="text" class="digit2" name="kat_col_1"> Кол-во столбцов<br>
		</td>' .
            '<td><input type="radio" name="kat_form_2"><b> Блочная форма</b><br>
		<input type="text" class="digit2" name="kat_col_2"> Кол-во столбцов<br>
		</td>' .
            '<td><input type="radio" name="kat_form_3"><b> Галерея</b><br>
		<input type="text" class="digit2" name="kat_col_3"> Кол-во столбцов<br>
		</td>' .
            '</tr>
		</table>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test20()
    {
        $data = array('rows' => array(
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
            array(
                array('id' => 1, 'text' => 'one'),
                array('id' => 2, 'text' => 'two'),
                array('id' => 3, 'text' => 'three'),
                array('id' => 4, 'text' => 'four'),
                array('id' => 5, 'text' => 'five'),
                array('id' => 6, 'text' => 'six')
            ),
        ));
        $s = '{% for  rr in rows %} {%- if not loop.first -%}
<tr>
<td style="height:35px;"></td>
{%- set bg=loop.cycle(\'bglgreen\',\'bggreen\') %}
{%- if loop.last %}{%set last=\'border-bottom:none;\' %}
{%- else %}{%set last=\'\' %}{% endif %}
<th class="{{bg}}" style="border-left:none;{{last}}">{{loop.index0}}</th>
{%- for  r in rr %}
<td class="{{bg}}" {%- if last %} style="{{last}}"{%endif%}>
<div id="item_text_{{r.id}}" class="text_edit">{{r.text|default(\'&nbsp;\')}}</div></td>
{%- endfor %}
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>
{%- endif %}
{%- endfor %}';
        $pattern = '<tr>
<td style="height:35px;"></td>
<th class="bglgreen" style="border-left:none;">1</th>
<td class="bglgreen">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bglgreen">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bglgreen">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bglgreen">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bglgreen">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bglgreen">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr><tr>
<td style="height:35px;"></td>
<th class="bggreen" style="border-left:none;">2</th>
<td class="bggreen">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bggreen">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bggreen">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bggreen">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bggreen">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bggreen">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr><tr>
<td style="height:35px;"></td>
<th class="bglgreen" style="border-left:none;border-bottom:none;">3</th>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_1" class="text_edit">one</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_2" class="text_edit">two</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_3" class="text_edit">three</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_4" class="text_edit">four</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_5" class="text_edit">five</div></td>
<td class="bglgreen" style="border-bottom:none;">
<div id="item_text_6" class="text_edit">six</div></td>
<td class="bgdray">
<input type="button" class="arrowup"
><input type="text" class="digit2"
><input type="button" class="arrowdn"
></td>
<td class="bgdray">
<input type="button" class="remrec">
</td>
</tr>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function test_test21()
    {
        $data = array();
        //$data=array('users'=>array(array('username'=>'one'),array('username'=>'two')));
        $s = '{%- for d in [1,2,3,4] %}
  <li><a href="?do=showtour&amp;id={{d.ID}}">{{d.name}}</a></li>
  {%- endfor %}
		</ul>
	</li>
	<li>
		<span>Игроки</span>
	  <ul>
  {%- for d in [1,2,3,4] %}
  <li><a href="?do=player&amp;id={{d.ID}}">{{d.name}}</a></li>
  {%- endfor %}';
        $pattern = '
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
  <li><a href="?do=showtour&amp;id="></a></li>
		</ul>
	</li>
	<li>
		<span>Игроки</span>
	  <ul>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>
  <li><a href="?do=player&amp;id="></a></li>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test22()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% macro Regnew(invite=1,error=3,error2=4,error3=5) -%}
        <table style="table-layout:fixed;">
			<tr><td >
            {%- if error %}<em>{{error}}</em><br>{% endif -%}
            {{error2}} {{error3}} {{invite}}</td></tr></table>
                    {%-endmacro -%}
             {{ Regnew(1,2,error3=6) }}';
        $pattern = '<table style="table-layout:fixed;">
			<tr><td ><em>2</em><br>4 6 1</td></tr></table>';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function test_test233()
    {
        $data = array('data' => '<<<>>>');
        $s = '{% macro fileman(list=1,pages=1,type,filter) -%}
{{list~pages~type~filter}}
        {%- endmacro -%}
{{fileman()}} {{fileman(pages=3)}} {{fileman(1,2,3)}}';
        $pattern = '1100 1300 1230';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function testCall()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = '{% macro fileman(list=1,pages=1,type,filter) -%}
{{list~pages~type~filter}}
        {%- endmacro -%}
        {{ call (func,1,2,3) }} {{fileman()}} {{fileman(pages=3)}} {{fileman(1,2,3)}}';
        $pattern = '1230 1100 1300 1230';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    /**
     * 2 ошибки.
     * поставить = вместо ==
     * закомментировать endif
     */

    function testAbsentEndif()
    {
        $data = array('func' => 'fileman', 'data' => '<<<>>>');
        $s = "<div class='body'>
{%- for elem in data %}
{%- if elem.type=='text' %}
{%- elseif elem.type=='foto' %}
{%- else %}
       unsupported type <br>
    {% endif %}
    {%- endfor %}
</div> ";
        $pattern = '<div class=\'body\'>
</div> ';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function test_test15()
    {
        $data = array('data' => array());
        $s = ' {{ "Hello World"|replace("Hello", "Goodbye") }}';
        $pattern = ' Goodbye World';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }


    function testSelf_Self()
    {
        $data = array('form' => array(

            'elements' => array('type' => 'fieldset', 'attributes' => ' style="width:100px;"', 'label' => 'Hello',
                'elements' => array(
                    array('id' => 'input', 'required' => true, 'label' => 'Hello world', 'html' => '<input type="text">')
                )
            )));
        $s = file_get_contents(dirname(__FILE__) . '/quick.form.twig');

        $pattern = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"> <html xmlns="http://www.w3.org/1999/xhtml"> <head> <title>Using Twig template engine to output the form</title> <style type="text/css"> /* Set up custom font and form width */ body { margin-left: 10px; font-family: Arial, sans-serif; font-size: small; } .quickform { min-width: 500px; max-width: 600px; width: 560px; } </style> </head> <body> <div class="quickform"> <form> <div class="row"> <label for="" class="element"> </label> <div class="element "> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element "> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element "> </div> </div> <div class="row"> <label for="" class="element"> </label> <div class="element "> </div> </div> </form> </div> </body> </html>';
        $this->assertEquals(
            $this->compress($this->compilerX($s, $data)), $pattern
        );
    }

    function test34 (){
        $data = array(
            'src' => 'xxx',
            'list' => array('xxx' => 1027),
            'years' =>array(2014,2015)
        );
        $s=<<<EEE
##
## заголовок станицы reviews
##
{% macro header (src,list,years) %}
{% set months=['январь','февраль','март','апрель','май','июнь','июль','август','сентябрь','октябрь','ноябрь','декабрь']
    %}
<div style="display:block; width:720px; margin: 0 auto; border: 1px solid gray; border-radius: 8px; background: #f4f4f7; /* Old browsers */
    background: -moz-linear-gradient(top, #f4f4f7 0%, #ffffff 100%); /* FF3.6+ */
    background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#f4f4f7), color-stop(100%,#ffffff)); /* Chrome,Safari4+ */
    background: -webkit-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* Chrome10+,Safari5.1+ */
    background: -o-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* Opera 11.10+ */
    background: -ms-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* IE10+ */
    background: linear-gradient(to bottom, #f4f4f7 0%,#ffffff 100%); /* W3C */
    filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#f4f4f7', endColorstr='#ffffff',GradientType=0 );">
    <table><tr><td>
        {% for year in years %}
        <div>
            <div style="width:45px; padding-top:5px; padding-bottom:5px; padding-left:5px; text-align:left; float:left;">{{ year }}</div>
            <div style="width:670px; padding-top:5px; padding-bottom:5px; float:right;">
                {% for m in [1..12] %}
                    {% if not loop.first %}/{% endif -%}
                    <a href="/content/articles/reviews.php?year={{ year}}&month={{ m }}{% if src and src!='all' %}&src={{ src }}{% endif %}">{{ months[m-1] }}</a>
                {% endfor %}
            </div>
        </div>
        {% endfor %}
    </td></tr></table>
    </div>
    <br />

    <h2 style="text-align:left; color:#2B709F; margin-left:20px; font-weight:bold; font-size:18px;">Отзывы покупателей за июль 2012</h2>
<div style="text-align:left; margin-left:20px; margin-top:10px;">
    {%- for key,val in list %}
        {%- if src==key %}<span class="bold">{{ val }}</span>
        {%- else %}<a href="{% if key and key!='all' %}?src={{ key }}{% else %}?{% endif %}">{{ val }}</a>
        {%- endif %}
        {%- if not loop.last %}/{% endif %}
    {%- endfor -%}
</div><br />
{% endmacro %}
EEE;
        $pattern = <<<XXX

<div style="display:block; width:720px; margin: 0 auto; border: 1px solid gray; border-radius: 8px; background: #f4f4f7; /* Old browsers */
    background: -moz-linear-gradient(top, #f4f4f7 0%, #ffffff 100%); /* FF3.6+ */
    background: -webkit-gradient(linear, left top, left bottom, color-stop(0%,#f4f4f7), color-stop(100%,#ffffff)); /* Chrome,Safari4+ */
    background: -webkit-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* Chrome10+,Safari5.1+ */
    background: -o-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* Opera 11.10+ */
    background: -ms-linear-gradient(top, #f4f4f7 0%,#ffffff 100%); /* IE10+ */
    background: linear-gradient(to bottom, #f4f4f7 0%,#ffffff 100%); /* W3C */
    filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#f4f4f7', endColorstr='#ffffff',GradientType=0 );">
    <table><tr><td>
        <div>
            <div style="width:45px; padding-top:5px; padding-bottom:5px; padding-left:5px; text-align:left; float:left;">2014</div>
            <div style="width:670px; padding-top:5px; padding-bottom:5px; float:right;"><a href="/content/articles/reviews.php?year=2014&month=1&src=xxx">январь</a>/<a href="/content/articles/reviews.php?year=2014&month=2&src=xxx">февраль</a>/<a href="/content/articles/reviews.php?year=2014&month=3&src=xxx">март</a>/<a href="/content/articles/reviews.php?year=2014&month=4&src=xxx">апрель</a>/<a href="/content/articles/reviews.php?year=2014&month=5&src=xxx">май</a>/<a href="/content/articles/reviews.php?year=2014&month=6&src=xxx">июнь</a>/<a href="/content/articles/reviews.php?year=2014&month=7&src=xxx">июль</a>/<a href="/content/articles/reviews.php?year=2014&month=8&src=xxx">август</a>/<a href="/content/articles/reviews.php?year=2014&month=9&src=xxx">сентябрь</a>/<a href="/content/articles/reviews.php?year=2014&month=10&src=xxx">октябрь</a>/<a href="/content/articles/reviews.php?year=2014&month=11&src=xxx">ноябрь</a>/<a href="/content/articles/reviews.php?year=2014&month=12&src=xxx">декабрь</a>
            </div>
        </div>
        <div>
            <div style="width:45px; padding-top:5px; padding-bottom:5px; padding-left:5px; text-align:left; float:left;">2015</div>
            <div style="width:670px; padding-top:5px; padding-bottom:5px; float:right;"><a href="/content/articles/reviews.php?year=2015&month=1&src=xxx">январь</a>/<a href="/content/articles/reviews.php?year=2015&month=2&src=xxx">февраль</a>/<a href="/content/articles/reviews.php?year=2015&month=3&src=xxx">март</a>/<a href="/content/articles/reviews.php?year=2015&month=4&src=xxx">апрель</a>/<a href="/content/articles/reviews.php?year=2015&month=5&src=xxx">май</a>/<a href="/content/articles/reviews.php?year=2015&month=6&src=xxx">июнь</a>/<a href="/content/articles/reviews.php?year=2015&month=7&src=xxx">июль</a>/<a href="/content/articles/reviews.php?year=2015&month=8&src=xxx">август</a>/<a href="/content/articles/reviews.php?year=2015&month=9&src=xxx">сентябрь</a>/<a href="/content/articles/reviews.php?year=2015&month=10&src=xxx">октябрь</a>/<a href="/content/articles/reviews.php?year=2015&month=11&src=xxx">ноябрь</a>/<a href="/content/articles/reviews.php?year=2015&month=12&src=xxx">декабрь</a>
            </div>
        </div>
    </td></tr></table>
    </div>
    <br />

    <h2 style="text-align:left; color:#2B709F; margin-left:20px; font-weight:bold; font-size:18px;">Отзывы покупателей за июль 2012</h2>
<div style="text-align:left; margin-left:20px; margin-top:10px;"><span class="bold">1027</span></div><br />
XXX;
        $this->assertEquals(
            $this->compilerX($s, $data, '_header'), $pattern
        );
    }

    function test_test35()
    {
        $data = array('data' => array());
        $s = '{% set foo = ["hello","world"] %}{{foo[0]}}{% set foo = {"foo": \'bar\'} %}{{ foo.foo }}{% set foo = {bar: \'world\'} %}{{ foo.bar }}';
        $pattern = 'hellobarworld';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    /**
     * длинный текст c чисткой пробелов
     */
    function testCreateAndRun0()
    {
        $src=str_repeat('
',1024*106).'{{- hello -}}'.str_repeat('
',1024*106);
        $this->assertEquals($this->compilerX($src,array('hello'=>'Ok')), 'Ok');
    }

    function test_test36()
    {
        $data = array('main'=>(object)array('hello'=>'world'));
        $s = '{{ main.hello }}';
        $pattern = 'world';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }

    function test_test37()
    {
        $data = array('main'=>new sample_test_object());
        $s = '{{ main.the_method(1,2) }}';
        $pattern = '1 2 3';
        $this->assertEquals(
            $this->compilerX($s, $data), $pattern
        );
    }
}

