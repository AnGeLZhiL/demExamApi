<?php

namespace Tests\Feature;

use Tests\TestCase;

class RealParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }
    
    public function test_university_parser_via_real_route()
    {
        echo "=" . str_repeat("=", 60) . "\n";
        echo "Тестирование работы парсинга групп с сайта Новгу\n";
        echo "Через роут: GET /api/university/groups/search\n";
        echo "=" . str_repeat("=", 60) . "\n\n";
        
        // Тест 1 - Проверка что роут существует и отвечает
        echo "1. Проверка существования и доступности роута:\n";
        $response = $this->getJson('/api/university/groups/search?search=3094');
        echo "   GET /api/university/groups/search?search=ИВТ";
        echo "        HTTP статус = " . $response->getStatusCode() . "\n";
        if ($response->getStatusCode() === 404) {
            echo "Роут не найден 404 ошибка\n";
            $this->fail("Роут /api/university/groups/search не найден");
            return;
        }
        echo "Роут существует и отвечает\n\n";
        
        // Тест 2 - разные сценарии поиска
        $testCases = [
            [
                'query' => '3094',
                'description' => 'Поиск групп 3094',
                'expected_min_groups' => 0 // Может быть 0 или больше
            ],
            [
                'query' => '0891',
                'description' => 'Поиск групп 0891',
                'expected_min_groups' => 0
            ],
            [
                'query' => '',
                'description' => 'Пустой поисковый запрос',
                'expected_min_groups' => 0
            ],
            [
                'query' => '0000',
                'description' => 'Поиск несуществующей группы',
                'expected_min_groups' => 0
            ]
        ];
        
        foreach ($testCases as $index => $testCase) {
            $testNumber = $index + 2;
            echo "{$testNumber}. Тест: {$testCase['description']}\n";
            echo "   Поисковый запрос: '{$testCase['query']}'\n";
            $startTime = microtime(true);
            $response = $this->getJson('/api/university/groups/search?search=' . urlencode($testCase['query']));
            echo "   HTTP статус: " . $response->getStatusCode() . "\n";
            if ($response->getStatusCode() >= 500) {
                echo "Серверная ошибка ({$response->getStatusCode()})\n";
                $errorData = $response->json();
                echo "   Ошибка: " . ($errorData['message']) . "\n";
                continue;
            }
            $data = $response->json();
    
            if (isset($data['success'])) {
                echo "   Успех : " . ($data['success'] ? 'да' : 'нет') . "\n";
                echo "   Найдено групп: " . count($data['groups'] ?? []) . "\n";
                if (!empty($data['groups'])) {
                    echo "Группа первая в списке:\n";
                    $firstGroup = $data['groups'][0];
                    echo "     Номер группы: " . ($firstGroup['number'] ?? 'не указан') . "\n";
                    echo "     Курс: " . ($firstGroup['course'] ?? 'не указан') . "\n";
                    echo "     Направление: " . ($firstGroup['direction'] ?? 'не указан') . "\n";
                    echo "     Студентов: " . ($firstGroup['students_count'] ?? 0) . "\n";
                    $this->assertArrayHasKey('number', $firstGroup, "Группа должна иметь номер");
                    $this->assertArrayHasKey('students', $firstGroup, "Группа должна иметь список студентов");
                    $this->assertIsArray($firstGroup['students'], "Студенты должны быть массивом");
                    
                    echo "Структура данных корректна\n";
                }
                
            } else {
                echo "Ответ не содержит поля 'success'\n";
                echo "Ответ: " . json_encode($data) . "\n";
            }
            echo "\n";
        }
    }
}