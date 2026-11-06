<?php

namespace Tests\Unit\Services;

use App\Features\Ai\Services\OpenAiStockGeneratorService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OpenAiStockGeneratorServiceTest extends TestCase
{
    #[Test]
    public function it_throws_exception_when_prompt_is_empty(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt tidak boleh kosong.');

        $service->generateStocks('   ');
    }

    #[Test]
    public function it_generates_stocks_from_openai_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'stocks' => [
                                    [
                                        'name' => 'Laptop Asus ROG',
                                        'sku' => 'LAP-ROG-001',
                                        'description' => 'Laptop gaming RTX 4060',
                                        'quantity' => 15,
                                        'unit' => 'unit',
                                        'price' => 21000000,
                                        'min_stock' => 5,
                                    ],
                                    [
                                        'name' => 'Thermal Paste',
                                        'sku' => '',
                                        'description' => '',
                                        'quantity' => 3,
                                        'unit' => '',
                                        'price' => 'invalid',
                                        'min_stock' => 5,
                                    ],
                                    [
                                        'name' => 'Kabel HDMI Habis',
                                        'quantity' => 0,
                                        'min_stock' => 2,
                                    ],
                                ],
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test', 'gpt-4o-mini');
        $items = $service->generateStocks('Komponen toko komputer', 3);

        $this->assertCount(3, $items);
        $this->assertSame('Laptop Asus ROG', $items[0]->name);
        $this->assertSame('LAP-ROG-001', $items[0]->sku);
        $this->assertSame('available', $items[0]->status);

        $this->assertSame('Thermal Paste', $items[1]->name);
        $this->assertNull($items[1]->sku);
        $this->assertNull($items[1]->description);
        $this->assertSame('pcs', $items[1]->unit);
        $this->assertNull($items[1]->price);
        $this->assertSame('low_stock', $items[1]->status);

        $this->assertSame('out_of_stock', $items[2]->status);
    }

    #[Test]
    public function it_throws_exception_when_openai_api_fails(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'Invalid API key provided.',
                ],
            ], 401),
        ]);

        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-invalid', 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API Error: Invalid API key provided.');

        $service->generateStocks('Alat tulis kantor');
    }

    #[Test]
    public function it_parses_markdown_fenced_json_and_list_arrays(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test');

        $raw = "```json\n[\n  {\"name\": \"Kertas HVS A4\", \"quantity\": \"invalid\", \"min_stock\": \"invalid\"},\n  {\"invalid_item\": true}\n]\n```";
        $items = $service->parseContent($raw);

        $this->assertCount(1, $items);
        $this->assertSame('Kertas HVS A4', $items[0]->name);
        $this->assertSame(10, $items[0]->quantity);
        $this->assertSame(5, $items[0]->minStock);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json_content(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AI mengembalikan format respon yang tidak valid.');

        $service->parseContent('not-a-json');
    }

    #[Test]
    public function it_throws_exception_when_decoded_json_has_no_stocks(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada item stok yang berhasil di-generate dari AI.');

        $service->parseContent(json_encode(['stocks' => []]));
    }

    #[Test]
    public function it_throws_exception_when_no_valid_items_are_present(): void
    {
        $service = new OpenAiStockGeneratorService('https://api.openai.com/v1', 'sk-test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Format item stok yang dikembalikan AI tidak sesuai spesifikasi.');

        $service->parseContent(json_encode(['stocks' => [['sku' => 'NO-NAME']]]));
    }

    #[Test]
    public function it_handles_swapped_endpoint_and_api_key_or_missing_scheme(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['stocks' => [['name' => 'Swapped Test']]])]],
                ],
            ], 200),
            'https://custom.openai.local/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['stocks' => [['name' => 'Scheme Test']]])]],
                ],
            ], 200),
        ]);

        // Swapped key and endpoint
        $swappedService = new OpenAiStockGeneratorService('sk-swapped-key', 'https://api.openai.com/v1');
        $res1 = $swappedService->generateStocks('Test 1');
        $this->assertSame('Swapped Test', $res1[0]->name);

        // Missing https:// scheme
        $schemeService = new OpenAiStockGeneratorService('custom.openai.local/v1', 'sk-normal-key');
        $res2 = $schemeService->generateStocks('Test 2');
        $this->assertSame('Scheme Test', $res2[0]->name);
    }
}

