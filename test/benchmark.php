<?php
/**
 * Use your profiler enable bookmarklet to profile this script.
 *
 * repeat:6000 times; peak:63912B, calc:1728B, final:3936B, 4.416233 sec spent (2015-06-13 13:53:26)
 * repeat:6000 times; peak:64608B, calc:2312B, final:4632B, 4.627567 sec spent (2015-06-13 19:05:08)
 *
 */
require '../test/autoloader.php';

//$array=[1,2,'last'=>3,4];end($array); $array[key($array)]="It's a last element";echo json_encode($array);
// just a simple number calculator
$r = new rpn_class();

$mem0=memory_get_usage();
$start_time0=microtime(true);


$r->option(array(
        'flags' => 0//12
      + rpn_class::SHOW_DEBUG
         // + rpn_class::ALLOW_REAL
    ,
        'operation' => ['+' => 4, '-' => 4, '*' => 5, '/' => 5,],
        'suffix' => ['++' => 1],
        'unop' => ['++' => 1,'-' => 1],
        'tagreg'=>'\b\d+\b',

        'evaluateTag' => function ($op) use($r)
            {
               // if (0 != ($r->flags & rpn_class::SHOW_DEBUG)) $r->log('eval:' . json_encode($op));
                if (!is_array($op) || !isset($op['data']))
                    $result = $op;
                else {
                    $result = 0 + $op['data']; // явное преобразование к числу
                }
                return $result;
            },
        'executeOp' => function ($op, $_1, $_2, $evaluate, $unop = 0) use ($r)
            {
               // if (0 != ($r->flags & rpn_class::SHOW_DEBUG)) $r->log('oper:' . json_encode($_1) . ' ' . $op . ' ' . json_encode($_2));
                if ($op == '+') {
                    return call_user_func($evaluate, $_1) + call_user_func($evaluate, $_2);
                } else if ($op == '++') {
                    return call_user_func($evaluate, $_2) + $unop-1;
                } else if ($op == '-' && $unop) {
                    return -call_user_func($evaluate, $_2);
                } else if ($op == '-') {
                    return call_user_func($evaluate, $_1) - call_user_func($evaluate, $_2);
                } else if ($op == '*') {
                    return call_user_func($evaluate, $_1) * call_user_func($evaluate, $_2);
                } else if ($op == '/') {
                    return call_user_func($evaluate, $_1) / call_user_func($evaluate, $_2);
                } elseif ($op == 'pi') {
                    return pi();
                } elseif ($op == 'e') {
                    return M_E;
                } elseif ($op == 'pow') {
                    return pow(call_user_func($evaluate, $_2[0]),call_user_func($evaluate, $_2[1]));
                } elseif ($op == 'floor') {
                    return floor(call_user_func($evaluate, $_2[0]));
                } elseif ($op == 'summ') {
                    $result=0;
                    foreach($_2 as $x){
                        $result+=call_user_func($evaluate, $x);
                    }
                    return $result;
                } else {
                    $r->error('unknown operation ' . $op);
                }
                return 0;
            }
));

$repeat=1;
$code=str_repeat('(1+2-4*1)+',$repeat).'0'; // -1 repeated $repeat times with 10*$repeat+1 bytes long
echo $code;
$before_calc=memory_get_usage();
$result = $r->ev($code);
$peak_mem=memory_get_usage();
if(0!=count($r->error())) echo 'error:'.json_encode($r->error())."\n";
if(-$repeat!=$result)  echo 'error in calculation:'.json_encode($result)."\n";
unset($r,$code,$result);
printf("repeat:%d times; peak:%dB, calc:%dB, final:%dB, %f sec spent (%s)\n",
    $repeat,
    $peak_mem-$mem0,
    $peak_mem-$before_calc,
    memory_get_usage()-$mem0,

    microtime(true)-$start_time0,
    date("Y-m-d H:i:s"));