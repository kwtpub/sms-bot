<?php

namespace App\Models\src\chatBot\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\Logs;

class Transalte extends Model
{
    protected $guarded = [];

    public static function translate($text, $language = 'en') {
        $inBD = self::where('language', $language)->where('text', $text)->first();
        if(!is_null($inBD)) {
            return $inBD->result;
        }


        $url = 'https://translate.google.com/translate_a/single'; // URL для Google Translate API

        // Параметры для POST-запроса
        $data = [
            'client' => 'gtx',          // Клиент Google Translate
            'sl' => 'ru',               // Исходный язык (русский)
            'tl' => $language,               // Целевой язык (английский)
            'hl' => 'en',               // Язык интерфейса (английский)
            'dt' => 't',                // Тип данных (перевод текста)
            'q' => $text,               // Текст для перевода
        ];

        // Инициализация cURL
        $ch = curl_init($url);

        // Установка параметров cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Возвращать ответ как строку
        curl_setopt($ch, CURLOPT_POST, true);           // Метод POST
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_PROXY, '82.146.36.178:12550');
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, 'rQN7qXT8DP:uP18tNMVEd');

        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Отправка данных
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded' // Устанавливаем тип данных
        ]);

        // Выполнение запроса
        $response = curl_exec($ch);

        // Проверка на ошибки cURL
        if ($response === false) {
            Logs::log('Translation Error: Invalid response from CURL', curl_error($ch));

            return $text;
        }

        // Закрытие соединения cURL
        curl_close($ch);

        // Декодирование ответа
        $responseData = json_decode($response, true);

        if (!isset($responseData[0][0][0])) {
            Logs::log('Translation Error: Invalid response from Google Translate', [$responseData, $responseData, $text]);

            return $text;
        }

        $translatedText = "";

        foreach($responseData[0] as $slag) {
            $translatedText .= $slag[0];
        }

        self::create([
            'language' => $language,
            'text' => $text,
            'result' => $translatedText
        ]);

        return $translatedText;
    }
}
