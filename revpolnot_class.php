<?php

/**
 * ��������� ��������������� ����� ��� ������� ��������� ������ � ��������� �� � �������� ��������� �����
 * � �������� � ������������� ������ ����������
 *
 * � ������� �������, ����...
 */
class revpolnot_class
{

    var $flags = 0; // 1 exception,2-stop on error,4-error, 8-debug

    /**
     * ������ ��������, ��������� � �������. �� ���� ����� ������� ���������
     */
    var $operation = array();
    var $suffix = array();
    var $unop = array();

    /**
     * ������ ����������������� ����. �� ���� ����� ������� ���������
     */
    var $reserved_words = array();

    /**
     * ��������� ����������, ������ �� ����� ����������
     */
    var $start = 0;
    var $errors = array();
    var $sintaxreg = '##i';
    var $tagreg = '';

    /**
     * ����� ���������� � ��� �������
     * @param $mess
     * @param int $flag
     */
    public function log($mess, $flag = 8)
    {
        if ($flag & 4)
            $this->errors[] = $mess;
        if (0 == ($this->flags & $flag)) return;
        // if($this->flags & 4) rase
        echo "\n" . $mess . '<br />';
    }

    /**
     * ���� �� ����� ������� �����. ����� ��� ��� ��� ������� ��������� ������������� ��������.
     * �� ����� ���� �� ������ ������� ������ ������ � ����������
     * @param $opt
     */
    public function option($opt)
    {
        if (!is_array($opt)) return;
        foreach ($opt as $key => $value) {
            if (property_exists($this, $key)) {
                if ($key == 'operation') {
                    $this->$key = array_merge(
                        array( // ���� ���� ����������� ������� ��� ��������
                            ')' => -1,
                            '(' => -1,
                            ',' => 0,
                        ),
                        $value
                    );
                } else {
                    $this->$key = $value;
                }
            }
        }
        // ������ ��������� �� ���� ������������ ����������
        $reg = '#\s*(';
        $simbols = array();

        $tags = array_unique(array_merge($this->reserved_words, array_keys($this->operation), array_keys($this->unop), array_keys($this->suffix)));
        $this->operation['_EMPTY_'] = 3;
        if (!empty($tags))
            foreach ($tags as $v) {
                if (preg_match('/^\w+$/', $v))
                    $reg .= '\b' . preg_quote($v) . '\b|';
                else
                    $simbols[] = $v;
            }
        if (!empty($simbols)) {
            $simbols = array_reverse($simbols); // ��� ����� ������� �������� `++` �� ����������� �� �������� `+`
            foreach ($simbols as $v) {
                $reg .= preg_quote($v) . '|';
            }
        }
        if (empty($this->tagreg))
            $reg .= '\b(\d+))#i'; // ������ ������ �����. ���!
        else
            $reg .= $this->tagreg . ')#i';
        $this->log('reg:' . $reg);
        $this->sintaxreg = $reg;
    }

    /**
     * ��� ��� �� ������� �� ����������.
     * @param $op
     * @param $opstack
     * @param $result
     * @param bool $unop
     */
    private function pushop($op, &$opstack, &$result, $unop = false)
    {
        if ($unop)
            $prio = 10;
        else
            $prio = $this->operation[$op];
        while (!empty($opstack) && $op != '(') {
            $past = array_pop($opstack);
            if ($past['op'] == '(' && $op == ')') {
                return;
            }
            if ($prio <= $past['prio']) {
                $data = array('op' => $past['op']);
                if (!empty($past['unop'])) {
                    $data['unop'] = $past['unop'];
                }
                $result[] = $data;
            } else {
                $opstack[] = $past;
                break;
            }
        }
        if ($op != ')') {
            $data = array('op' => $op, 'prio' => $prio);
            if ($unop) {
                $data['unop'] = true;
            }
            $opstack[] = $data;
        }
    }

    /**
     * ����������� � �������� �������� �����
     * @param $code
     * @param int $st
     * @return array
     */
    private function LtoP($code, $st = 0)
    {
        if ($st == 0) {
            $code = strtoupper($code);
            $this->errors = array();
            $this->start = 0;
        }
        $result = array();
        $op = array(array('op' => '('));
        $place_operand = true;
        while (preg_match($this->sintaxreg, $code, $m, PREG_OFFSET_CAPTURE, $this->start)) {
            //$this->log('found:'.json_encode($m[0]).$this->start);
            if ($this->start != $m[0][1]) {
                $this->log('error:' . json_encode($m[0]) . $this->start . ' ' . $st);
                $this->log(sprintf('[%d:%d] ', $this->start, $m[0][1] - $this->start), 4);
            }
            $tag = $m[1][0];
            $this->start = $m[0][1] + strlen($m[0][0]);
            switch ($tag) {
                case '(':
                    if (!$place_operand) {
                        $this->pushop('_EMPTY_', $op, $result);
                    }
                    $this->pushop('(', $op, $result);
                    $result = array_merge($result, $this->LtoP($code, $this->start));
                    $this->pushop(')', $op, $result);
                    $place_operand = false;
                    break;
                case ')':
                    break 2;
                case ',':
                    $this->pushop(',', $op, $result);
                    break 2;
                default:
                    if (isset($this->unop[$tag]) && $place_operand) {
                        $this->pushop($tag, $op, $result, true);
                    } else if (isset($this->suffix[$tag]) && !$place_operand) {
                        $result[] = array('op' => $tag, 'unop' => true);
                    } else if (isset($this->operation[$tag]) && !$place_operand) {
                        $this->pushop($tag, $op, $result);
                        $place_operand = true;
                    } else {
                        if (isset($this->operation[$tag]) || isset($this->suffix[$tag]) || isset($this->unop[$tag])) {
                            $this->log(sprintf('improper place for `%s`', $tag), 4);
                        }

                        if (!$place_operand) { // ���� �������� ��� - ����� ������ ��������
                            $this->pushop('_EMPTY_', $op, $result);
                        }
                        $result[] = array('data' => $m[2][0]);
                        $place_operand = false;
                    }
            }
        }
        if ($st == 0 && $this->start < strlen($code)) {
            $this->log('xxx:' . $this->start . ' ' . $st);
            $this->log(sprintf('[%d:%d] ', $this->start, strlen($code) - $this->start), 4);
        }
        if (!empty($op)) { // ����� -- �������������� �������� ������?
            $this->pushop(')', $op, $result);
        }
        return $result;
    }

    /**
     * @param string $code
     * @param callback $evaluateTag
     * @param callable|string $executeOp
     * @return mixed
     */
    function ev($code, $evaluateTag = null, $executeOp = null)
    {
        $st = $this->LtoP($code);
        if (is_null($evaluateTag))
            return $st;
        if (is_null($executeOp)) $executeOp = array($this, 'executeOpLogical');
        // ���������
        $op = array(); // ���� ���������
        foreach ($st as $r) {
            if (isset($r['data']))
                $op[] = $r;
            else {
                if (!empty($r['unop'])) {
                    // ������� ��������
                    $op[] = call_user_func($executeOp, $r['op'], false, array_pop($op), $evaluateTag, true);
                } else { // �������� ��������
                    $_2 = array_pop($op);
                    $op[] = call_user_func($executeOp, $r['op'], array_pop($op), $_2, $evaluateTag);
                }
            }
        }
        return array_pop($op);
    }
}