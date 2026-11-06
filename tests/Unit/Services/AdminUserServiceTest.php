<?php

namespace Tests\Unit\Services;

use App\Features\Admin\Services\AdminUserService;
use App\Features\Stock\Models\Stock;
use App\Features\Stock\Repositories\StockRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminUserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AdminUserService(new StockRepository());
    }

    #[Test]
    public function it_returns_paginated_users_with_stocks_count(): void
    {
        $user1 = User::factory()->create(['name' => 'Alpha User', 'username' => 'alpha', 'email' => 'alpha@example.com']);
        $user2 = User::factory()->create(['name' => 'Beta User', 'username' => 'beta', 'email' => 'beta@example.com']);

        Stock::factory()->count(3)->create(['user_id' => $user1->id]);

        $paginator = $this->service->getUsersList();

        $this->assertSame(2, $paginator->total());
        $foundUser1 = collect($paginator->items())->firstWhere('id', $user1->id);
        $this->assertSame(3, $foundUser1->stocks_count);
    }

    #[Test]
    public function it_filters_users_by_search_query(): void
    {
        User::factory()->create(['name' => 'Gamma Person', 'username' => 'gamma_u', 'email' => 'gamma@example.com']);
        User::factory()->create(['name' => 'Delta Person', 'username' => 'delta_u', 'email' => 'delta@example.com']);

        $byName = $this->service->getUsersList('Gamma');
        $this->assertSame(1, $byName->total());

        $byUsername = $this->service->getUsersList('delta_u');
        $this->assertSame(1, $byUsername->total());

        $byEmail = $this->service->getUsersList('gamma@example.com');
        $this->assertSame(1, $byEmail->total());
    }

    #[Test]
    public function it_returns_target_user_stocks(): void
    {
        $user = User::factory()->create();
        Stock::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'available']);
        Stock::factory()->count(1)->create(['user_id' => $user->id, 'status' => 'low_stock']);

        $all = $this->service->getUserStocks($user->id);
        $this->assertSame(3, $all->total());

        $filtered = $this->service->getUserStocks($user->id, 'low_stock');
        $this->assertSame(1, $filtered->total());
    }
}

