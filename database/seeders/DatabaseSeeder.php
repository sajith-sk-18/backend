<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default admin
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Store Admin',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ],
        );

        // Categories
        $laptops = Category::updateOrCreate(
            ['slug' => 'laptops'],
            ['name' => 'Laptops', 'description' => 'Notebooks, ultrabooks, gaming laptops']
        );
        $accessories = Category::updateOrCreate(
            ['slug' => 'accessories'],
            ['name' => 'Accessories', 'description' => 'Mice, keyboards, bags, chargers, hubs']
        );
        $monitors = Category::updateOrCreate(
            ['slug' => 'monitors'],
            ['name' => 'Monitors', 'description' => 'External displays']
        );

        // Sample products
        $samples = [
            [
                'category' => $laptops,
                'name'     => 'Dell XPS 13 9340',
                'brand'    => 'Dell',
                'price'    => 1399.00,
                'stock'    => 12,
                'description' => '13.4" InfinityEdge laptop with Core Ultra 7, 16GB LPDDR5x, 1TB NVMe SSD. Perfect for travel and pro workloads.',
                'specs'    => [
                    'Processor' => 'Intel Core Ultra 7 155H',
                    'RAM'       => '16 GB LPDDR5x',
                    'Storage'   => '1 TB NVMe SSD',
                    'Display'   => '13.4" FHD+ 1920x1200',
                    'GPU'       => 'Intel Arc Graphics',
                    'OS'        => 'Windows 11 Pro',
                    'Weight'    => '1.17 kg',
                ],
                'is_featured' => true,
            ],
            [
                'category' => $laptops,
                'name'     => 'Apple MacBook Air 15" M3',
                'brand'    => 'Apple',
                'price'    => 1499.00,
                'stock'    => 8,
                'description' => 'M3 chip, fanless design, all-day battery. 15.3" Liquid Retina display.',
                'specs' => [
                    'Processor' => 'Apple M3 (8-core CPU, 10-core GPU)',
                    'RAM'       => '16 GB unified',
                    'Storage'   => '512 GB SSD',
                    'Display'   => '15.3" Liquid Retina',
                    'OS'        => 'macOS Sonoma',
                    'Weight'    => '1.51 kg',
                ],
                'is_featured' => true,
            ],
            [
                'category' => $laptops,
                'name'     => 'Lenovo ThinkPad X1 Carbon Gen 12',
                'brand'    => 'Lenovo',
                'price'    => 1799.00,
                'stock'    => 5,
                'description' => 'Business-class ultrabook. MIL-SPEC durability, carbon-fiber lid, Intel vPro.',
                'specs' => [
                    'Processor' => 'Intel Core Ultra 7 165U',
                    'RAM'       => '32 GB LPDDR5x',
                    'Storage'   => '1 TB PCIe Gen4',
                    'Display'   => '14" 2.8K OLED',
                    'OS'        => 'Windows 11 Pro',
                ],
                'is_featured' => true,
            ],
            [
                'category' => $laptops,
                'name'     => 'ASUS ROG Zephyrus G14',
                'brand'    => 'ASUS',
                'price'    => 1899.00,
                'stock'    => 6,
                'description' => 'Gaming powerhouse with Ryzen 9 + RTX 4070. 14" QHD+ 165Hz display.',
                'specs' => [
                    'Processor' => 'AMD Ryzen 9 8945HS',
                    'RAM'       => '32 GB DDR5',
                    'Storage'   => '1 TB NVMe',
                    'GPU'       => 'NVIDIA RTX 4070 8GB',
                    'Display'   => '14" 2560x1600 165Hz OLED',
                ],
                'is_featured' => false,
            ],
            [
                'category' => $accessories,
                'name'     => 'Logitech MX Master 3S',
                'brand'    => 'Logitech',
                'price'    => 99.00,
                'stock'    => 50,
                'description' => 'Flagship wireless mouse. 8000 DPI sensor, quiet clicks, MagSpeed scroll.',
                'specs' => [
                    'Connectivity' => 'Bluetooth + Logi Bolt USB',
                    'Battery'      => '70 days',
                    'DPI'          => 'Up to 8000',
                ],
                'is_featured' => true,
            ],
            [
                'category' => $accessories,
                'name'     => 'Keychron K2 Pro Mechanical Keyboard',
                'brand'    => 'Keychron',
                'price'    => 139.00,
                'stock'    => 25,
                'description' => 'Wireless mechanical keyboard, hot-swappable switches, QMK/VIA support.',
                'specs' => [
                    'Layout'   => '75% TKL',
                    'Switches' => 'Hot-swappable (red/brown/blue)',
                    'Backlight'=> 'RGB',
                ],
                'is_featured' => true,
            ],
            [
                'category' => $accessories,
                'name'     => 'Anker 65W GaN Charger',
                'brand'    => 'Anker',
                'price'    => 49.00,
                'stock'    => 80,
                'description' => 'Compact 65W GaN charger, USB-C PD. Powers most laptops and phones.',
                'specs' => [
                    'Output'    => '65W USB-C PD',
                    'Ports'     => '1× USB-C',
                    'Standards' => 'PD 3.0, PPS',
                ],
                'is_featured' => false,
            ],
            [
                'category' => $monitors,
                'name'     => 'LG UltraFine 27" 4K Monitor',
                'brand'    => 'LG',
                'price'    => 599.00,
                'stock'    => 10,
                'description' => '27" IPS, 4K UHD, DCI-P3 95%, USB-C 90W passthrough.',
                'specs' => [
                    'Size'       => '27"',
                    'Resolution' => '3840×2160 (4K UHD)',
                    'Panel'      => 'IPS',
                    'Ports'      => '2× HDMI 2.0, 1× DP, 1× USB-C 90W',
                ],
                'is_featured' => true,
            ],
        ];

        foreach ($samples as $row) {
            Product::updateOrCreate(
                ['name' => $row['name']],
                [
                    'category_id' => $row['category']->id,
                    'brand'       => $row['brand'],
                    'price'       => $row['price'],
                    'stock'       => $row['stock'],
                    'description' => $row['description'],
                    'specs'       => $row['specs'],
                    'is_featured' => $row['is_featured'],
                    'is_active'   => true,
                ]
            );
        }
    }
}
