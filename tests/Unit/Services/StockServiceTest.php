<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockService(new StockRepository());
    }

    #[Test]
    public function it_resolves_status_automatically_from_quantity_and_min_stock(): void
    {
        $this->assertSame('out_of_stock', $this->service->resolveStatus(0, 5));
        $this->assertSame('low_stock', $this->service->resolveStatus(5, 5));
        $this->assertSame('low_stock', $this->service->resolveStatus(3, 5));
        $this->assertSame('available', $this->service->resolveStatus(6, 5));
    }

    #[Test]
    public function it_creates_updates_adjusts_and_lists_stocks(): void
    {
        $user = User::factory()->create();

        $stock = $this->service->createStock($user->id, [
            'name' => 'SSD NVMe 1TB',
            'sku' => 'SSD-1TB',
            'description' => 'PCIe 4.0',
            'quantity' => 10,
            'unit' => 'pcs',
            'price' => 1250000,
            'min_stock' => 5,
        ]);

        $this->assertSame('available', $stock->status);

        $list = $this->service->getStocksForUser($user->id);
        $this->assertSame(1, $list->total());

        // Adjust quantity down into low_stock
        $this->service->adjustQuantity($stock, -6);
        $stock->refresh();
        $this->assertSame(4, $stock->quantity);
        $this->assertSame('low_stock', $stock->status);

        // Adjust quantity down to 0 (cannot go below 0) -> out_of_stock
        $this->service->adjustQuantity($stock, -10);
        $stock->refresh();
        $this->assertSame(0, $stock->quantity);
        $this->assertSame('out_of_stock', $stock->status);

        // Update stock back to available
        $this->service->updateStock($stock, [
            'name' => 'SSD NVMe 1TB Pro',
            'quantity' => 12,
            'min_stock' => 4,
        ]);
        $stock->refresh();
        $this->assertSame('SSD NVMe 1TB Pro', $stock->name);
        $this->assertSame(12, $stock->quantity);
        $this->assertSame('available', $stock->status);
    }

    #[Test]
    public function it_manages_cover_photos_and_deletes_stock_with_cover_cleanup(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['user_id' => $user->id]);

        // First cover upload
        $file1 = UploadedFile::fake()->image('cover1.jpg');
        $path1 = $this->service->updateCoverPhoto($stock, $file1);
        $stock->refresh();
        Storage::disk('public')->assertExists($path1);
        $this->assertSame($path1, $stock->cover_image_path);

        // Replace cover upload -> deletes old cover
        $file2 = UploadedFile::fake()->image('cover2.jpg');
        $path2 = $this->service->updateCoverPhoto($stock, $file2);
        $stock->refresh();
        Storage::disk('public')->assertMissing($path1);
        Storage::disk('public')->assertExists($path2);

        // Delete cover photo only
        $this->service->deleteCoverPhoto($stock);
        $stock->refresh();
        Storage::disk('public')->assertMissing($path2);
        $this->assertNull($stock->cover_image_path);

        // Upload again and delete entire stock -> cleans up cover photo
        $file3 = UploadedFile::fake()->image('cover3.jpg');
        $path3 = $this->service->updateCoverPhoto($stock, $file3);
        $stock->refresh();
        Storage::disk('public')->assertExists($path3);

        $this->service->deleteStock($stock);
        Storage::disk('public')->assertMissing($path3);
        $this->assertDatabaseMissing('stocks', ['id' => $stock->id]);
    }
}

