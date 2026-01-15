<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
     /** @test */
    public function it_calculates_sum_correctly()
    {
        // Arrange (подготовка данных)
        $a = 2;
        $b = 3;
        
        // Act (выполнение действия)
        $result = $a + $b;
        
        // Assert (проверка результата)
        $this->assertEquals(5, $result);
    }
    
    /** @test */
    public function it_checks_boolean_values()
    {
        $isActive = true;
        
        $this->assertTrue($isActive);
        $this->assertFalse(!$isActive);
    }
}
