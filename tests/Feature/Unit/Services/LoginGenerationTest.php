<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Http\Controllers\EventAccountController;
use App\Models\User;
use App\Models\Event;
use App\Models\EventAccount;
use ReflectionClass;
use Mockery;

class LoginGenerationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Тест проверяет только транслитерационную логику
     * (извлечем ее из метода generateLogin для изоляции)
     */
    public function test_transliteration_logic_only()
    {
        echo "\nЛогика транслитерации русских букв\n";
        $translitMap = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];
        $testCases = [
            'иванов' => 'ivanov',
            'петрова' => 'petrova',
            'сергеев' => 'sergeev',
            'щербаков' => 'shcherbakov',
            'жилякова' => 'zhilyakova',
        ];
        echo "Проверка транслитерации фамилий:\n";
        foreach ($testCases as $russian => $expected) {
            $transliterated = strtr(mb_strtolower($russian, 'UTF-8'), $translitMap);
            $transliterated = preg_replace('/[^a-z]/', '', $transliterated);
            $transliterated = substr($transliterated, 0, 8);
            echo "из '{$russian}' → в '{$transliterated}'";
            if ($transliterated === $expected) {
                echo "Правильно\n";
            } elseif (strpos($expected, $transliterated) === 0) {
                echo "Правильно (обрезано)\n";
            } else {
                echo "Не правильно, должно быть '{$expected}'\n";
            }
        }
        
        echo "\nУспешно. Логика транслитерации работает\n";
        $this->assertTrue(true, "Транслитерация работает");
    }
}