<?php
/**
 * Created by PhpStorm.
 * User: Сергей
 * Date: 08.06.15
 * Time: 15:35
 */
require '../vendor/autoload.php';

use Ksnk\rpn\rpn_class;

// just a simple number calculator
$r = new rpn_class();
/**
 * Процент от предыдущего значения 200-10% == 180
 */
$r->_suf('%', 1, function ($a, $eval) use ($r) {
    $a->val = $eval($a);
    $a->percent = 1;
    return $a;
})->functions([
    'pi' => function () {
        return M_PI;
    },
    'e' => function () {
        return M_E;
    }], 0
)->functions([
    'absolute' => function ($a, $eval) {
        return abs($eval($a[0]));
    },
    'arccos' => function ($a, $eval) { // арккосинус от x
        return acos($eval($a[0]));
    },
    'arccosh' => function ($a, $eval) { // арккосинус гиперболический от x
        return acosh($eval($a[0]));
    },
    'arcsin' => function ($a, $eval) { // арксинус от x
        return asin($eval($a[0]));
    },
    'arcsinh' => function ($a, $eval) { // арксинус гиперболический от x
        return asinh($eval($a[0]));
    },
    'arctan' => function ($a, $eval) { // арктангенс от x
        return atan($eval($a[0]));
    },
    'arctanh' => function ($a, $eval) { // арктангенс гиперболический от x
        return atanh($eval($a[0]));
    },
    'exp' => function ($a, $eval) { // экспонента от x
        return exp($eval($a[0]));
    },
    'floor' => function ($a, $eval) { // округление x в меньшую сторону
        return floor($eval($a[0]));
    },
    'log' => function ($a, $eval) { // Натуральный логарифм от x
        return log($eval($a[0]));
    },
    'ln' => function ($a, $eval) { // Натуральный логарифм от x
        return log($eval($a[0]));
    },
    'sign' => function ($a, $eval) { // Знак x
        $number = $eval($a[0]);
        return $number ? abs($number) / $number : 0;
    },
    'sin' => function ($a, $eval) { // Синус от x
        return sin($eval($a[0]));
    },
    'cos' => function ($a, $eval) { // Косинус от x
        return cos($eval($a[0]));
    },
    'sinh' => function ($a, $eval) { // Синус гиперболический от x
        return sinh($eval($a[0]));
    },
    'cosh' => function ($a, $eval) { // Косинус гиперболический от x
        return cosh($eval($a[0]));
    },
    'sqrt' => function ($a, $eval) { // квадратный корень из x
        return sqrt($eval($a[0]));
    },
    'sqr' => function ($a, $eval) { // Квадрат x
        $number = $eval($a[0]);
        return $number * $number;
    },
    'tan' => function ($a, $eval) { // Тангенс от x
        return tan($eval($a[0]));
    },
    'tanh' => function ($a, $eval) { // Тангенс гиперболический от x
        return tanh($eval($a[0]));
    },
    'cbrt' => function ($a, $eval) { // Функция - кубический корень из x
        return pow($eval($a[0]), 1 / 3);
    },
], 1)->functions([
    'pow' => function ($a, $eval) {
        if (count($a) > 1)
            return pow($eval($a[0]), $eval($a[1]));
        return NAN;
    },
], 2)->functions([
    'summ' => function ($a, $eval) {
        $result = 0;
        foreach ($a as $x) {
            $result += $eval($x);
        }
        return $result;
    },
], -1);

$r->option([
    'flags' => 0/**/ //+rpn_class::SHOW_DEBUG+rpn_class::SHOW_ERROR
        + rpn_class::ALLOW_REAL
        + rpn_class::USE_ARIPHMETIC,
    'executeOp' => function ($op, $_1, $_2, $evaluate) use ($r) {
        if (!$op->unop && is_object($_2) && $_2->percent) {
            $_2->val = (($_1 = call_user_func($evaluate, $_1)) / 100) * $_2->val;
            $_2->percent = false; // на всякий случай
        }
        return $r->std_execute($op, $_1, $_2, $evaluate);
    }

]);

foreach ([
             'summ(1,2,4,5,6*4^5,7,8,4.5,6)', //6181.5
             '.05',
             '0x8000',
             '1/0',
             '3.4E+2',
             '00.334564',
             'sin(0)',
             '10-20%',
             '-sqrt(sqr(2)+12)',
             '-1',
             '+1+1-1',
             'pi', 'e',
             'sin(pi/2)',
             '3^2+1',
             'sqrt(-1)',
             'sin(pi/2)',
             '3.4E+2',
             '00.3.4',
             'pow(2,3)',
             'pow(2,3,4)',// 8 error: =["wrong parameters count"]
             'pow(2)',// error: =["wrong parameters count"]
             'summ(1,2,4,5,6,7,,8,4.5,6)', //43.5,
             /**/
         ] as $k => $v) {
    //  echo "\n" . json_encode($r->ev($v, false));
    try {
        $result = $r->ev($v); //var_dump($result);
        if (is_nan($result))
            $result = 'NaN';
        else if (is_infinite($result))
            $result = 'Infinite';
    } catch (Exception $e) {
        $result = $e->getMessage();
    }
    echo "\n" . $v . '=' . json_encode($result);
    $error = $r->error();
    if (!empty($error)) echo "\n" . 'error: =' . json_encode($error, JSON_UNESCAPED_UNICODE);
    echo "\n";
}
