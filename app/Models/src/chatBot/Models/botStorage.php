<?php

namespace App\Models\src\chatBot\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Logs;
use Illuminate\Support\Facades\DB;

class botStorage extends Model
{
    protected $guarded = [];

    // Кеш для предотвращения повторных запросов в рамках одного запроса (Runtime Cache)
    private static $runtimeCache = [];
    private static $lastCombinationCache = null;

    public static function compressData($cd, $saving, $callback_data) {
        $cd = self::initializeData($cd, $saving, $callback_data);
        $cd = self::cleanEmptyPagination($cd);

        $json = self::compressJson($cd);
        if (strlen($json) <= 64) {
            return $json;
        }

        [$keysSize, $valuesSize] = self::calculateSizes($cd);

        // Рефакторинг: вынесли общую логику сжатия, чтобы не дублировать код
        if ($keysSize >= $valuesSize) {
            $newCd = self::processCompression($cd, 'keys');
            $newCd['cmr'] = 1;
            $json = self::compressJson($newCd);

            if (strlen($json) > 64) {
                $newCd = self::processCompression($newCd, 'values');
                $newCd['cmr'] = 3;
                $json = self::compressJson($newCd);
            }
        } else {
            $newCd = self::processCompression($cd, 'values');
            $newCd['cmr'] = 2;
            $json = self::compressJson($newCd);

            if (strlen($json) > 64) {
                $newCd = self::processCompression($newCd, 'keys');
                $newCd['cmr'] = 3;
                $json = self::compressJson($newCd);
            }
        }

        return (strlen($json) <= 64) ? $json : self::storeData($json);
    }

    // Общий метод для сжатия ключей или значений
    private static function processCompression($cd, $mode) {
        $newCd = [];
        foreach ($cd as $key => $value) {
            if ($key === 'cmr') {
                $newCd[$key] = $value;
                continue;
            }

            if ($mode === 'keys') {
                $hashKey = self::getOrCreateHash($key, 'compressing');
                $newCd[$hashKey] = $value;
            } else {
                $valToHash = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                $hashVal = self::getOrCreateHash($valToHash, 'compressing');
                $newCd[$key] = $hashVal;
            }
        }
        return $newCd;
    }

    private static function compressJson($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        return str_replace(['=', '&'], [':', ','], http_build_query($array));
    }

    private static function deCompressJson($json) {
        $queryString = str_replace([':', ','], ['=', '&'], $json);
        parse_str($queryString, $data);

        foreach ($data as $key => $value) {
            if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$key] = $decoded;
                }
            }
        }
        return $data;
    }

    private static function initializeData($cd, $saving, $callback_data) {
        foreach ($saving as $param) {
            if (!isset($cd[$param])) {
                $cd[$param] = $callback_data[$param] ?? '';
            }
        }
        return $cd;
    }

    private static function cleanEmptyPagination($cd) {
        return array_filter($cd, function($value, $key) {
            return !((str_starts_with($key, 'b_') || $key == 'p') && empty($value));
        }, ARRAY_FILTER_USE_BOTH);
    }

    private static function calculateSizes($cd) {
        $keysSize = 0;
        $valuesSize = 0;
        foreach ($cd as $key => $value) {
            $keysSize += strlen((string)$key);
            $valuesSize += is_array($value) 
                ? strlen(json_encode($value, JSON_UNESCAPED_UNICODE)) 
                : strlen((string)$value);
        }
        return [$keysSize, $valuesSize];
    }

    private static function getOrCreateHash($value, $type) {
        $strValue = (string)$value;
        $cacheKey = $type . '_' . $strValue;

        if (isset(self::$runtimeCache[$cacheKey])) {
            return self::$runtimeCache[$cacheKey];
        }

        $record = self::where('type', $type)->where('value', $strValue)->first();

        if (!$record) {
            if (self::$lastCombinationCache === null) {
                $last = self::where('type', 'compressing')->orderBy('id', 'desc')->value('hash');
                self::$lastCombinationCache = $last ?? '';
            }

            self::$lastCombinationCache = self::generateUniqueString(self::$lastCombinationCache);
            
            $record = self::create([
                'hash' => self::$lastCombinationCache,
                'type' => $type,
                'value' => $strValue
            ]);
        }

        self::$runtimeCache[$cacheKey] = $record->hash;
        return $record->hash;
    }

    private static function storeData($json) {
        $hash = md5($json);
        // Используем value('hash') чтобы не тянуть весь объект модели
        $exists = self::where('type', 'callback')->where('hash', $hash)->exists();

        if (!$exists) {
            self::create([
                'type' => 'callback',
                'hash' => $hash,
                'value' => 'empty',
                'data' => $json
            ]);
        }

        return json_encode(['storedId' => $hash]);
    }

    public static function handlerCallback($callback) {
        if (empty($callback)) return [];

        $decoded = json_decode($callback, true);
        $callback = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) 
            ? $decoded 
            : self::deCompressJson($callback);

        if (isset($callback['storedId'])) {
            $storedData = self::where('hash', (string)$callback['storedId'])->where('type', 'callback')->first();
            if ($storedData) {
                return self::handlerCallback($storedData->data);
            }
        }

        if (isset($callback['cmr'])) { 
            $callback = self::decompressData($callback);
        }

        if (isset($callback[0])) {
            $callback['btn'] = $callback[0];
            unset($callback[0]);
        }

        return $callback;
    }

    private static function decompressData($callback) {
        $compressType = (int)$callback['cmr']; 
        unset($callback['cmr']); 

        // ОПТИМИЗАЦИЯ: Собираем все хэши из ключей и значений сразу
        $allHashes = array_unique(array_merge(array_keys($callback), array_values($callback)));
        
        // Загружаем одним запросом всё, что может понадобиться
        $dictionary = self::where('type', 'compressing')
            ->whereIn('hash', $allHashes)
            ->pluck('value', 'hash')
            ->toArray();

        switch ($compressType) {
            case 1:
                $callback = self::applyDecompression($callback, $dictionary, true, false);
                break;
            case 2:
                $callback = self::applyDecompression($callback, $dictionary, false, true);
                break;
            case 3:
                $callback = self::applyDecompression($callback, $dictionary, true, true);
                break;
        }

        return $callback;
    }

    // Вспомогательный метод для быстрой подстановки значений из словаря
    private static function applyDecompression($callback, $dictionary, $keys = false, $values = false) {
        $result = [];
        foreach ($callback as $k => $v) {
            $newK = ($keys && isset($dictionary[$k])) ? $dictionary[$k] : $k;
            $newV = ($values && isset($dictionary[$v])) ? $dictionary[$v] : $v;

            if ($values && is_string($newV) && (str_starts_with($newV, '[') || str_starts_with($newV, '{'))) {
                $decoded = json_decode($newV, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $newV = $decoded;
                }
            }
            $result[$newK] = $newV;
        }
        return $result;
    }

    private static function generateUniqueString($lastCombination = '') {
        $chars = 'qwertyuiopasdfghjklzxcvbnm123456789';
        $charsLength = strlen($chars);

        if (empty($lastCombination)) {
            return $chars[0];
        }

        $lastCombinationArr = mb_str_split($lastCombination);
        $length = count($lastCombinationArr);

        for ($i = $length - 1; $i >= 0; $i--) {
            $char = $lastCombinationArr[$i];
            $pos = strpos($chars, $char);

            if ($pos < $charsLength - 1) {
                $lastCombinationArr[$i] = $chars[$pos + 1];
                return implode('', $lastCombinationArr);
            } else {
                $lastCombinationArr[$i] = $chars[0];
            }
        }

        return $chars[0] . implode('', array_fill(0, $length, $chars[0]));
    }
}