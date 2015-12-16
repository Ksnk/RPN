<?php
/**
 * this file is created automatically at "16 Dec 2015 15:43". Never change anything, 
 * for your changes can be lost at any time.  
 */

class tpl_compiler extends tpl_base {
function __construct(){
    parent::__construct();
}
        
function _class(&$___par,$macro="",$import="",$data="",$name="",$extends=""){
    if(!empty($___par))extract($___par);$result="";
        $result.='<?php
/**
 * this file is created automatically at "'
            . date('d M Y G:i')
            . '". Never change anything, 
 * for your changes can be lost at any time.  
 */

class tpl_'
            . $name
            . ' extends tpl_'
            . $this->filter_default($extends,'base')
            . ' {
function __construct(){
    parent::__construct();';

            $loop3_array=$macro;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $m){
    
        $result.='
$this->macro[\''
            . $m
            . '\']=array($this,\'_'
            . $m
            . '\');';
        }
    };
            $loop3_array=$import;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $imp){
    
        $result.='$'
            . $imp
            . '=new tpl_'
            . $imp
            . '();
        $this->macro=array_merge($this->macro,$'
            . $imp
            . '->macro);';
        }
    };
        $result.='
}';

            $loop3_array=$data;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $func){
    
        $result.='
        '
            . $func
            . '
';
        }
    };
        $result.='
}
';
    return $result;
}

        
function _sentence(&$___par,$data=""){
    if(!empty($___par))extract($___par);$result="";
            
        if( (((($this->func_bk($data,'val'))!=(''))) && ((($this->func_bk($data,'val'))!=('""')))) && ((($this->func_bk($data,'val'))!=('\'\''))) ) {
            
        $result.='
        $result.='
            . $this->func_bk($data,'val')
            . ';';
        };
    return $result;
}

        
function _callmacro(&$___par,$parkeys="",$param="",$name=""){
    if(!empty($___par))extract($___par);$result="";
        $result.='if(!empty($this->macro[\''
            . $name
            . '\']))
        $result.=call_user_func($this->macro[\''
            . $name
            . '\'],array(';

            $loop3_array=$parkeys;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $p){
    
        $result.='\''
            . $this->func_bk($p,'key')
            . '\'=>'
            . $this->func_bk($p,'value')
            . ',';
        }
    };
        $result.=')';

            
        if( $param ) {
            
        $result.=','
            . $this->filter_join($param,', ');
        };
        $result.=')';
    return $result;
}

        
function _callmacroex(&$___par,$par1="",$mm="",$param=""){
    if(!empty($___par))extract($___par);$result="";
        $result.=$par1
            . '->'
            . $mm
            . '( '
            . $this->filter_join($param,', ')
            . ' )';
    return $result;
}

        
function _set(&$___par,$id="",$res=""){
    if(!empty($___par))extract($___par);$result="";
        $result.=$id
            . '='
            . $res;
    return $result;
}

        
function _for(&$___par,$loop_index="",$loopdepth="",$loop_last="",$loop_revindex="",$loop_cycle="",$index2="",$_else="",$in="",$index="",$body=""){
    if(!empty($___par))extract($___par);$result="";
        $result.='$loop'
            . $loopdepth
            . '_array='
            . $this->filter_default($in,'array()')
            . ';';

            
        if( $loop_index ) {
            
        $result.='$loop'
            . $loopdepth
            . '_index=0;';
        };
            
        if( $loop_last ) {
            
        $result.='$loop'
            . $loopdepth
            . '_last=count($loop'
            . $loopdepth
            . '_array);';
        };
            
        if( $loop_revindex ) {
            
        $result.='$loop'
            . $loopdepth
            . '_revindex=$loop'
            . $loopdepth
            . '_last+1;';
        };
            
        if( $loop_cycle ) {
            
        $result.='$loop'
            . $loopdepth
            . '_cycle='
            . $loop_cycle
            . ';';
        };
        $result.='
    if ((is_array($loop'
            . $loopdepth
            . '_array) && !empty($loop'
            . $loopdepth
            . '_array))
        ||($loop'
            . $loopdepth
            . '_array instanceof Traversable)
    ){
        foreach($loop'
            . $loopdepth
            . '_array as '
            . $index;

            
        if( $index2 ) {
            
        $result.=' =>'
            . $index2
            . ' ';
        };
        $result.='){';

            
        if( $loop_index ) {
            
        $result.='    $loop'
            . $loopdepth
            . '_index++;';
        };
            
        if( $loop_revindex ) {
            
        $result.='    $loop'
            . $loopdepth
            . '_revindex--;';
        };
        $result.='
    '
            . $body
            . '
        }
    }';

            
        if( $_else ) {
            
        $result.='
        else {
        '
            . $_else
            . '
        }';
        };
    return $result;
}

        
function _callblock(&$___par,$name=""){
    if(!empty($___par))extract($___par);$result="";
            $x=$name;
            
        if( $x ) {
            
        $result.='$this->_'
            . $x
            . '($___par)';
        };
    return $result;
}

        
function _block(&$___par,$name="",$param="",$data=""){
    if(!empty($___par))extract($___par);$result="";
            
        if( $name ) {
            
        $result.='
function _'
            . $name
            . '(&$___par';

            $loop3_array=$param;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $p){
    
        $result.=',$'
            . $this->func_bk($p,'name');

            
        if( $this->func_bk($p,'value') ) {
            
        $result.='='
            . $this->func_bk($p,'value');
        } else {
            
        $result.='=0';
        };
        }
    };
        $result.='){
    if(!empty($___par))extract($___par);$result="";';
        };
            $str_is_last=0;
            $loop3_array=$data;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $blk){
    
            
        if( (((!($str_is_last)) && (((($this->func_bk($blk,'type'))==(5))) || ((($this->func_bk($blk,'type'))==(1))))) && ((($this->func_bk($blk,'val'))!=('\'\'')))) && ((($this->func_bk($blk,'val'))!=('""'))) ) {
            
        $result.='
        $result.=';

            $str_is_last=1;
        };
            
        if( ((($this->func_bk($blk,'type'))==(5))) || ((($this->func_bk($blk,'type'))==(1))) ) {
            
            
        if( ((($this->func_bk($blk,'val'))!=('""'))) && ((($this->func_bk($blk,'val'))!=('\'\''))) ) {
            
            
        if( (($str_is_last)>(1)) ) {
            
        $result.='
            . ';
        };
            $str_is_last=(($str_is_last)+(1));
        $result.=$this->func_bk($blk,'val');
        };
        } else {
            
            
        if( $str_is_last ) {
            
        $result.=';
';
        };
            $str_is_last=0;
        $result.='
            '
            . $this->func_bk($blk,'val')
            . ';';
        };
        }
    };
            
        if( $str_is_last ) {
            
        $result.=';';
        };
            
        if( $name ) {
            
        $result.='
    return $result;
}';
        };
    return $result;
}

        
function _if(&$___par,$data=""){
    if(!empty($___par))extract($___par);$result="";
            $if_index=1;
            $if_last=count($data);
            $loop3_array=$data;
    if ((is_array($loop3_array) && !empty($loop3_array))
        ||($loop3_array instanceof Traversable)
    ){
        foreach($loop3_array as $d){
    
            
        if( (($if_index)==(1)) ) {
            
        $result.='
        if( '
            . $this->func_bk($d,'if')
            . ' ) {
            '
            . $this->func_bk($d,'then')
            . '
        }';
        } elseif( ($this->func_bk($d,'if')) || ((($if_index)!=($if_last))) ) {
            
        $result.=' elseif( '
            . $this->func_bk($d,'if')
            . ' ) {
            '
            . $this->func_bk($d,'then')
            . '
        }';
        } else {
            
        $result.=' else {
            '
            . $this->func_bk($d,'then')
            . '
        }';
        };
            $if_index=(($if_index)+(1));
        }
    };
    return $result;
}

        
function _ (&$___par){
    if(!empty($___par))extract($___par);$result="";
        $result.=$this->_class($___par)
            . $this->_sentence($___par)
            . $this->_callmacro($___par)
            . $this->_callmacroex($___par)
            . $this->_set($___par)
            . $this->_for($___par)
            . $this->_callblock($___par)
            . $this->_block($___par)
            . $this->_if($___par);
    return $result;
}

}
