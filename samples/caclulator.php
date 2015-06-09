<?php
/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 15:35
 */
require '../test/autoloader.php';

// just a simple natural number calculator
$r=new revpolnot_class();

$r->option(array(
//    'flags'=>12,
    'operation'=>[ '+'=>4,'-'=>4,'*'=>5,'/'=>5, ],
    'suffix'=>['++'=>1],
    'unop'=>['-'=>1],
    'tagreg'=>'\b(\d+)\b',
));

$code='(-3*-----4)*4++/5';

//echo eval($code);
echo "\n".json_encode($r->ev($code));
echo "\n".json_encode($r->ev($code,
        function ($op) use($r) {
            $r->log('eval:'.json_encode($op));
            if(!is_array($op) || !isset($op['data']))
                $result= $op;
            else {
                $result=0+$op['data'] ; // явное преобразование к числу
            }
            return $result;
        },
        function ($op,$_1,$_2,$evaluate,$unop=false) use ($r){
            $r->log('oper:'.json_encode($_1).' '.$op.' '.json_encode($_2));
            if($op=='+'){
                return call_user_func($evaluate,$_1)+call_user_func($evaluate,$_2);
            } else if($op=='++') {
                return call_user_func($evaluate,$_2)+1;
            } else if($op=='-' && $unop) {
                return -call_user_func($evaluate,$_2);
            } else if($op=='-') {
                return call_user_func($evaluate,$_1)-call_user_func($evaluate,$_2);
            } else if($op=='*') {
                return call_user_func($evaluate,$_1)*call_user_func($evaluate,$_2);
            } else if($op=='/') {
                return call_user_func($evaluate,$_1)/call_user_func($evaluate,$_2);
            } else {
                $r->error('unknown operation '.$op);
            }
            return 0;
        }
));
