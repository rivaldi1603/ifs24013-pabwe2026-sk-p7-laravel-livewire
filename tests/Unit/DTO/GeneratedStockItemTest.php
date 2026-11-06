<?php

namespace Tests\Unit\DTO;

use App\Features\Ai\DTO\GeneratedStockItem;
use PHPUnit\Framework\TestCase;

class GeneratedStockItemTest extends TestCase
{
    public function test_dto_properties_and_to_array(): void
    {
        $dto = new GeneratedStockItem(
            name: 'Monitor 4K',
            sku: 'MON-4K-01',
            description: 'Monitor IPS 27 inch',
            quantity: 15,
            unit: 'unit',
            price: 4500000.0,
            minStock: 3,
            status: 'available'
        );

        $this->assertEquals('Monitor 4K', $dto->name);
        $this->assertEquals('MON-4K-01', $dto->sku);
        $this->assertEquals('Monitor IPS 27 inch', $dto->description);
        $this->assertEquals(15, $dto->quantity);
        $this->assertEquals('unit', $dto->unit);
        $this->assertEquals(4500000.0, $dto->price);
        $this->assertEquals(3, $dto->minStock);
        $this->assertEquals('available', $dto->status);

        $array = $dto->toArray();
        $this->assertEquals([
            'name' => 'Monitor 4K',
            'sku' => 'MON-4K-01',
            'description' => 'Monitor IPS 27 inch',
            'quantity' => 15,
            'unit' => 'unit',
            'price' => 4500000.0,
            'min_stock' => 3,
            'status' => 'available',
        ], $array);
    }
}

