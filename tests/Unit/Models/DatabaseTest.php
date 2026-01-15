<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Database;

class DatabaseTest extends TestCase
{
    /** @test */
    public function it_returns_empty_list_when_no_databases()
    {
        // Act: Запрос когда БД нет
        $response = $this->getJson('/api/databases');
        
        // Assert
        $response->assertStatus(200);
        $response->assertJson([]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_database()
    {
        // Act: Запрос несуществующей БД
        $response = $this->getJson('/api/databases/999');
        
        // Assert
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Database not found']);
    }

    /** @test */
    public function it_creates_new_database()
    {
        // Arrange: Данные для создания
        $data = [
            'name' => 'test_database',
            'username' => 'test_user',
            'password' => 'test_password',
            'server_id' => 1,
            'type_id' => 1,
            'event_account_id' => 1,
            'module_id' => 1,
            'status_id' => 1,
            'is_active' => true,
            'is_public' => false,
        ];
        
        // Act: POST запрос
        $response = $this->postJson('/api/databases', $data);
        
        // Assert
        $response->assertStatus(201); // Created
        
        // Проверяем что запись создана
        $this->assertDatabaseHas('databases', [
            'name' => 'test_database',
            'username' => 'test_user',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_validates_required_fields_on_create()
    {
        // Arrange: Неполные данные
        $data = [
            'name' => 'test', // Только имя, остального нет
        ];
        
        // Act
        $response = $this->postJson('/api/databases', $data);
        
        // Assert: Должна быть ошибка валидации
        $response->assertStatus(422); // Unprocessable Entity
        $response->assertJsonValidationErrors(['username', 'password']);
    }
}