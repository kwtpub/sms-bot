<?php

namespace App\Models\src\chatBot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\src\chatBot\Models\botStorage;

class KeyBoard extends Model
{
    public $buttons = [];
    public $saving = [];
    public $callback_data = [];
    private $lineIDS = [];
    public $is_admin = 0; //Админ-ли?

    public function clear() {
        $this->buttons = [];
        $this->lineIDS = [];
    }

    public function saveParams($params, $callback_data) {
        $this->saving = $params;
        $this->callback_data = $callback_data;
    }

    public function generate($inline = 1) { //Финальная генерация клавиатуры
        if(empty($this->buttons) && !$inline) $this->loadDefault();

        $buttonsArrays = [];

        foreach($this->buttons as $i => $buttons_arr) {
            if(!isset($buttons_arr[0])) $buttons_arr = [$buttons_arr];
            if(empty($buttons_arr)) continue;

            foreach($buttons_arr as $j => $button) {
                $btn = [
                    'text' => $button['text']
                ];

                if(isset($button['cd'])) { //Если есть callback data в кнопке
                    $btn['callback_data'] = botStorage::compressData($button['cd'], $this->saving, $this->callback_data);
                }
                elseif(isset($button['url'])) { //Если есть url
                    $btn['url'] = $button['url'];
                }
                elseif(isset($button['web_app'])) { //Если есть web_app(Ссылка откроеться внутри тг)
                    $btn['web_app'] = $button['web_app'];
                }

                $buttonsArrays[$i][$j] = $btn;
            }
        }

        if(!$inline) {
            if(empty($buttonsArrays)) {
                return json_encode(['remove_keyboard' => true]);
            }
            
            return json_encode(['keyboard' => $buttonsArrays, 'resize_keyboard' => true]);
        }
        else {
            return json_encode(['inline_keyboard' => $buttonsArrays]);
        }
    }

    public function loadDefault() { //Кнопки если отправляем сообщение без кнопок
        // $this->add('Подключить VPN');
        // $this->add('Личный кабинет');
        // $this->add('Помощь');
        if($this->is_admin) $this->add('Админ-панель');
    }

    public function add($text, $callback_data = '', $line = 'none', $url = '', $web_app = 1) { //Добавление кнопки.
        if(mb_stripos($text, 'назад') === 0) $text = '⬅️ ' . $text;
        elseif(mb_stripos($text, 'отмена') === 0) $text = '🚫 ' . $text;
        elseif(mb_stripos($text, 'попробовать') === 0) $text = '🔄 ' . $text;
        elseif(mb_stripos($text, 'подтвержд') === 0) $text = '✅ ' . $text;

        $btn = [
            'text' => $text
        ];

        if(!empty($callback_data)) {
            $btn['cd'] = is_array($callback_data) ? $callback_data : [$callback_data];
        }
        //Если есть ссылка
        elseif(!empty($url)) {
            if($web_app) {
                $btn['web_app'] = ['url' => $url]; //web_app - открытие внутри тг
            } else {
                $btn['url'] = $url; //обычная ссылка
            }
        }

        if($line == 'none') {
            $this->lineIDS[] = [$btn];
        }
        else {
            if(!isset($this->lineIDS[$line])) {
                $this->lineIDS[$line] = [$btn];
            }
            else {
                $this->lineIDS[$line][] = $btn;
            }
        }

        ksort($this->lineIDS, SORT_NATURAL);

        $this->buttons = array_values($this->lineIDS);
    }

}
