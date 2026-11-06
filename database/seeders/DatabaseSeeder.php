<?php

namespace Database\Seeders;

use App\Features\Stock\Models\Stock;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Member User',
                'username' => 'member',
                'password' => Hash::make('member'),
                'role' => 'member',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Laptop ThinkPad X1 Carbon'],
            [
                'sku' => 'ELC-LTP-001',
                'description' => 'Laptop bisnis ringan dengan prosesor Intel Core Ultra dan RAM 32GB.',
                'quantity' => 15,
                'unit' => 'unit',
                'price' => 24500000,
                'min_stock' => 5,
                'status' => 'available',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Mechanical Keyboard Wireless'],
            [
                'sku' => 'ACC-KBD-002',
                'description' => 'Keyboard mekanikal hot-swappable dengan switch tactile.',
                'quantity' => 3,
                'unit' => 'pcs',
                'price' => 1250000,
                'min_stock' => 5,
                'status' => 'low_stock',
            ]
        );

        Stock::firstOrCreate(
            ['user_id' => $member->id, 'name' => 'Monitor 4K IPS 27 Inch'],
            [
                'sku' => 'ELC-MON-003',
                'description' => 'Monitor resolusi UHD 4K dengan akurasi warna 99% sRGB.',
                'quantity' => 0,
                'unit' => 'unit',
                'price' => 5800000,
                'min_stock' => 3,
                'status' => 'out_of_stock',
            ]
        );
    }
}
