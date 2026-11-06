<?php

namespace Tests\Unit\Services;

use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Features\Stock\Services\StockExcelService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class StockExcelServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockExcelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StockExcelService(new StockRepository());
    }

    #[Test]
    public function it_builds_spreadsheet_and_exports_streamed_response(): void
    {
        $user = User::factory()->create();
        Stock::factory()->create([
            'user_id' => $user->id,
            'name' => 'Monitor LG 27',
            'sku' => 'MON-LG-27',
            'quantity' => 8,
            'unit' => 'unit',
            'price' => 3200000,
            'min_stock' => 3,
            'status' => 'available',
        ]);

        $stocks = Stock::all();
        $spreadsheet = $this->service->buildSpreadsheet($stocks, 'Inventory Sheet');

        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('Inventory Sheet', $sheet->getTitle());
        $this->assertSame('Name', $sheet->getCell('B1')->getValue());
        $this->assertSame('Monitor LG 27', $sheet->getCell('B2')->getValue());
        $this->assertSame('MON-LG-27', $sheet->getCell('C2')->getValue());

        $response = $this->service->exportToResponse($stocks, 'export-stocks.xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $response);

        ob_start();
        $response->sendContent();
        $output = ob_get_clean();
        $this->assertNotEmpty($output);
    }

    #[Test]
    public function it_imports_stocks_from_excel_with_various_status_resolutions(): void
    {
        $user = User::factory()->create();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1: Header
        $sheet->fromArray(['No', 'Name', 'SKU', 'Description', 'Quantity', 'Unit', 'Price', 'Min Stock', 'Status'], null, 'A1');
        // Row 2: Explicit valid status
        $sheet->fromArray([1, 'Barang Explicit', 'SKU-EXP', 'Desc 1', 15, 'pcs', 50000, 5, 'available'], null, 'A2');
        // Row 3: Invalid status, qty 0 -> out_of_stock
        $sheet->fromArray([2, 'Barang Habis', '-', '-', 0, '-', '-', 5, 'invalid_status'], null, 'A3');
        // Row 4: Empty status, qty <= min_stock -> low_stock
        $sheet->fromArray([3, 'Barang Menipis', 'SKU-LOW', 'Desc 3', 3, 'box', 120000, 5, ''], null, 'A4');
        // Row 5: Empty status, qty > min_stock -> available
        $sheet->fromArray([4, 'Barang Aman', 'SKU-OK', 'Desc 4', 20, 'unit', 75000, 5, ''], null, 'A5');
        // Row 6: Fallback name in Column A when Column B is empty
        $sheet->fromArray(['Barang Kolom A', '', '', '', 10, 'pcs', 10000, 2, ''], null, 'A6');
        // Row 7: Duplicate header row or empty row (should be skipped)
        $sheet->fromArray(['No', 'Name', '', '', '', '', '', '', ''], null, 'A7');

        $tempFile = tempnam(sys_get_temp_dir(), 'stock_test_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        $result = $this->service->import($tempFile, $user->id);

        @unlink($tempFile);

        $this->assertSame(5, $result['imported']);
        $this->assertEmpty($result['errors']);

        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Explicit',
            'sku' => 'SKU-EXP',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Habis',
            'sku' => null,
            'unit' => 'pcs',
            'price' => null,
            'status' => 'out_of_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Menipis',
            'status' => 'low_stock',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Aman',
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('stocks', [
            'user_id' => $user->id,
            'name' => 'Barang Kolom A',
            'status' => 'available',
        ]);
    }
}

