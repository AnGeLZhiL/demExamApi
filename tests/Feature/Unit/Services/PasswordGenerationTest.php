<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Http\Controllers\EventAccountController;
use ReflectionClass;

class PasswordGenerationTest extends TestCase
{
    /**
     * 1.Пароль имеет длину 12 символов
     * 2.Содержит хотя бы одну цифру
     * 3.Содержит хотя бы одну заглавную букву
     * 4.Содержит хотя бы одну строчную букву
     * 5.Содержит хотя бы один специальный символ
     */
    public function test_password_generation_meets_requirements()
    {
        echo "Проверка генерации паролей\n";
        $controller = new EventAccountController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('generateRawPassword');
        $method->setAccessible(true);
        $passwords = [];
        for ($i = 0; $i < 5; $i++) {
            $passwords[] = $method->invoke($controller);
        }
        echo "Сгенерированные пароли:\n";
        foreach ($passwords as $index => $password) {
            echo ($index + 1) . ". {$password}\n";
        }
        echo "\n";
        $allPasswordsValid = true;
        foreach ($passwords as $index => $password) {
            echo "Проверка пароля #" . ($index + 1) . ":\n";
            $length = strlen($password);
            //Проверка на длину 12 символов
            if ($length === 12) {
                echo "Успешно. Длина: 12 символов\n";
            } else {
                echo "Ошибка! Длина: {$length}\n";
                $allPasswordsValid = false;
            }
            //Проверка наличия цифры
            if (preg_match('/\d/', $password)) {
                echo "Успешно. Содержит цифру\n";
            } else {
                echo "Ошибка! Не содержит цифру\n";
                $allPasswordsValid = false;
            }
            //Проверка наличия заглавной буквы
            if (preg_match('/[A-Z]/', $password)) {
                echo "Успешно. Содержит заглавную букву\n";
            } else {
                echo "Ошибка! Не содержит заглавную букву\n";
                $allPasswordsValid = false;
            }
            //Проверка наличия строчной буквы
            if (preg_match('/[a-z]/', $password)) {
                echo "Успешно. Содержит строчную букву\n";
            } else {
                echo "Ошибка! Не содержит строчную букву\n";
                $allPasswordsValid = false;
            }
            //Проверка наличия специального символа
            if (preg_match('/[!@#$%^&*]/', $password)) {
                echo "Успешно. Содержит специальный символ\n";
            } else {
                echo "Ошибка! Не содержит специальный символ\n";
                $allPasswordsValid = false;
            }
            //Проверка уникальности паролей
            $duplicates = array_keys($passwords, $password);
            if (count($duplicates) === 1) {
                echo "Успешно. Пароль уникальный\n";
            } else {
                echo "Ошибка! Пароль повторяется\n";
                $allPasswordsValid = false;
            }
            echo "\n";
        }
        if ($allPasswordsValid) {
            echo "Успешно. Все пароли соответствуют требованиям\n";
            $this->assertTrue(true, "Все пароли соответствуют требованиям");
        } else {
            echo "Ошибка! Не все требования соблюдены\n";
            $this->fail("Генерация паролей не соответствует требованиям");
        }
    }
    
    /**
     * Тест проверяет что пароли действительно случайны
     */
    public function test_passwords_are_random()
    {
        echo "\nПроверка случайности паролей\n";
        $controller = new EventAccountController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('generateRawPassword');
        $method->setAccessible(true);
        $passwords = [];
        for ($i = 0; $i < 10; $i++) {
            $passwords[] = $method->invoke($controller);
        }
        //Проверка что все пароли разные
        $uniquePasswords = array_unique($passwords);
        $duplicateCount = count($passwords) - count($uniquePasswords);
        echo "Сгенерировано паролей: " . count($passwords) . "\n";
        echo "Уникальных паролей: " . count($uniquePasswords) . "\n";
        echo "Дубликатов: " . $duplicateCount . "\n\n";
        if ($duplicateCount === 0) {
            echo "Успешно. Все пароли уникальны\n";
            $this->assertCount(count($passwords), $uniquePasswords, 
                "Все пароли должны быть уникальными");
        } else {
            $this->fail("Обнаружены дубликаты при генерации паролей");
        }
    }
}