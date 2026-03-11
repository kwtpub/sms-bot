<?php

namespace App\Models\src\chatBot\Modules;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TreeListBuilder extends Model
{
	public $list = [];

	public function add($str, $lastAddLast = false) {
		if(is_array($str)) { //Если это массив - объеленим массивы.
			$this->list = array_merge($this->list, $str);
		}
		else {
		    // Если требуется добавить элемент в последний подмассив
		    if ($lastAddLast) {
		        // Создаем новый подмассив, если массив пустой или последний элемент не является массивом
		        if (empty($this->list) || !is_array(end($this->list))) {
		            $this->list[] = [];
		        }

		        // Добавляем строку в последний подмассив
		        $this->list[array_key_last($this->list)][] = $str;
		    } else {
		        // Добавляем строку как отдельный элемент массива
		        $this->list[] = $str;
		    }
		}
	}

	public function make($array = [], $indent = 0, $noStart = 0, $nextX = 0, $next2 = 0) {
	    $array = $array ?: $this->list;
	    $this->list = []; // Очистка после обработки

	    // Формируем отступы
	    $indentText = str_repeat(" ", $indent);

	    if($indent && $next2 && !$nextX) {
	    	$indentText = '│' . mb_substr($indentText, 1);
	    }

	    $str = "";

	    $maxIndex = array_key_last($array);

	    foreach ($array as $i => $item) {
	        // Рекурсивно обрабатываем подмассивы
	        if (is_array($item)) {
	            $str .= $this->make($item, $indent + 3, 1, (isset($array[$i + 1]) && mb_substr($array[$i + 1], 0, 1) === "X"), (isset($array[$i + 2])));
	        } else {
	            // Добавляем нужные символы для оформления
	            $prefix = ($i === $maxIndex || isset($array[$i + 1]) && (is_array($array[$i + 1]) || mb_substr($array[$i + 1], 0, 1) === "X")) ? "└ " : "├ ";

	            if(!(isset($array[$i + 1]) && !is_array($array[$i + 1]) && mb_substr($array[$i + 1], 0, 1) === "X") && !$nextX && $i != $maxIndex && isset($array[$i + 2])) {
	            	$prefix = "├ ";
	            }

	            if(!$noStart && (!isset($array[$i-1]) || $array[$i-1] == 'X' || is_array($array[$i-1]))) {
	            	$prefix = "┌ ";

	            	if($i === $maxIndex || isset($array[$i+1]) && is_array($array[$i+1])) {
	            		$prefix = '└ ';
	            	}

	            	if($next2 && isset($array[$i-1]) && is_array($array[$i-1])) {
	            		$prefix = '├ ';
	            	}
	            }

	            // Убираем первый символ "X", если он есть
	            $item = mb_substr($item, 0, 1) === "X" ? mb_substr($item, 1) : $prefix . $item;
	            $str .= $indentText . $item . "\n";
	        }
	    }

	    return $str;
	}

}