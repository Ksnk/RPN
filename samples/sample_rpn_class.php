<?php
/**
 * исчисление категорий
 */
require '../test/autoloader.php';

/**
 * Исходные данные
 */
$category_tree=[
    128=>0,
    129=>128,
    130=>128,
    501=>0,
    502=>501,
    503=>501,
    504=>503,
    505=>503,
];

// исходное выражение
$code='128 (501* not 503*)';

// набор категорий товара
$item_category =array(128,504);

/**
 *  строим данные по исходным
 */
$parents=array();
foreach($item_category as $leaf){
    while(!empty($category_tree[$leaf])){
        $parents[]=$category_tree[$leaf];
        $leaf=$category_tree[$leaf];
    }
}
$parents=array_unique($parents);

/**
 * пример выяснения в той ли группе находится товар
 */
$r=new \rpn_class();
$r->option([
    'flags'=>rpn_class::EMPTY_FUNCTION_ALLOWED
    //      | 12
    ,
    'operation' =>  ['AND'=>3,'OR'=>3,'NOT'=>3],
    'suffix'    =>  ['*'=>3],
    'tagreg'    =>  '\b(\d+)\b',
    'unop'      =>  ['NOT'=>3],

    'evaluateTag'=>function ($op) use ($r,$item_category) {
            if(is_bool($op)) $result= $op;
            else {
                $result=in_array($op->val,$item_category) ;
                $r->log('get:'.json_encode($op).'='.json_encode($result));
            }
            return $result;
        },
    'executeOp'=>function ($op,$_1,$_2,$evaluate,$unop=false) use ($r,$item_category,$parents){
            $result=false;
            if($op=='*'){
                if(is_array($_2) && isset($_2->val)){
                    $result = in_array($_2->val,$item_category) || in_array($_2['data'],$parents);
                } else {
                    $result = $_2;
                }
            } else if($op=='OR' || $op=='_EMPTY_'){
                if($_1===true || $_2===true) {
                    $result = true;
                } else {
                    if(!call_user_func($evaluate,$_1))
                        $result = call_user_func($evaluate,$_2);
                    else
                        $result = true;
                }
            } else if($op=='AND') {
                if($_1===false || $_2===false){
                    $result = false;
                } else {
                    if(call_user_func($evaluate,$_1))
                        $result = call_user_func($evaluate,$_2);
                    else
                        $result = false;
                }
            } else if($op=='NOT' && $unop) {
                $result = !call_user_func($evaluate,$_2);
            } else if($op=='NOT') { // делаем AND NOT
                if($_1===false || $_2===true)
                    $result = false;
                else
                    $result = call_user_func($evaluate,$_1) && !call_user_func($evaluate,$_2);
            }
            $r->log('oper:'.json_encode($_1).' '.$op.' '.json_encode($_2).'='.json_encode($result));
            return $result;
        }
]);

/**
 * пример выяснения в той ли группе находится товар
 *
 * Для логических операций возможна оптимизация вычислений.
 */
echo "\n".json_encode($r->ev($code));

/**
 * список всех категорий, подходящих под выражение
 *
 * логика - каждый тег преобразуется в список категорий, операции
 * преобразуют его в список категорий
 */
$r->option([
    'evaluateTag'=>function ($op) use ($r,$category_tree) {
        if(!isset($op->val)) $result= $op;
        else {
            if(is_array($op->val))
                $result=$op->val;
            else
                $result=array(0+$op->val) ;
            if(''!=$op->suf){
                $level=$result;
                do{
                    $level0=array();
                    foreach($category_tree as $id=>$cat){
                        if(in_array($cat,$level)){
                            $level0[]=$id;
                        }
                    }
                    if(!empty($level0))
                        $result=array_merge($result,$level0);
                    $level=$level0;
                } while(!empty($level0));
            }
        }
        return $result;
    },
    'executeOp'=>function ($op,$_1,$_2,$evaluate,$unop=false) use ($r,$category_tree){
            $result=[];
            if($op=='*' && $unop){
                $result=new operand(call_user_func($evaluate,$_2));$result->suf='*';
            } else if($op=='OR' || $op=='_EMPTY_'){
                $result=array_merge(call_user_func($evaluate,$_1),call_user_func($evaluate,$_2));
            } else if($op=='AND') {
                $result=array_intersect(call_user_func($evaluate,$_1),call_user_func($evaluate,$_2));
            } else if($op=='NOT' and $unop) {
                $result= array_diff(array_keys($category_tree),call_user_func($evaluate,$_2));
            } else if($op=='NOT') {
                $result= array_diff(call_user_func($evaluate,$_1),call_user_func($evaluate,$_2));
            }
            $r->log('oper:'.json_encode($_1).' '.$op.' '.json_encode($_2).'='.json_encode($result));
            return array_unique($result);
        }]);

echo "\n".json_encode($r->ev($code));

