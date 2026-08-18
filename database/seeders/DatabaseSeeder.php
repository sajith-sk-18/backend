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

        // Categories (with real cover images)
        $img = 'https://images.unsplash.com/';
        $laptops = Category::updateOrCreate(
            ['slug' => 'laptops'],
            ['name' => 'Laptops', 'description' => 'Notebooks, ultrabooks, gaming laptops', 'image' => $img . 'photo-1496181133206-80ce9b88a853?w=800&q=80&auto=format&fit=crop']
        );
        $accessories = Category::updateOrCreate(
            ['slug' => 'accessories'],
            ['name' => 'Accessories', 'description' => 'Mice, keyboards, bags, chargers, hubs', 'image' => $img . 'photo-1541140532154-b024d705b90a?w=800&q=80&auto=format&fit=crop']
        );
        $monitors = Category::updateOrCreate(
            ['slug' => 'monitors'],
            ['name' => 'Monitors', 'description' => 'External displays', 'image' => $img . 'photo-1527443224154-c4a3942d3acf?w=800&q=80&auto=format&fit=crop']
        );
        $audio = Category::updateOrCreate(
            ['slug' => 'audio'],
            ['name' => 'Audio', 'description' => 'Headphones, earbuds and speakers', 'image' => $img . 'photo-1505740420928-5e560c06d30e?w=800&q=80&auto=format&fit=crop']
        );
        $storage = Category::updateOrCreate(
            ['slug' => 'storage'],
            ['name' => 'Storage & Networking', 'description' => 'SSDs, drives, routers and hubs', 'image' => $img . 'photo-1618384887929-16ec33fab9ef?w=800&q=80&auto=format&fit=crop']
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

            // ---- additional catalogue ----
            ['category' => $laptops, 'name' => 'HP Spectre x360 14', 'brand' => 'HP', 'price' => 1599.00, 'stock' => 7, 'is_featured' => true,
                'description' => 'Convertible 2-in-1 with a 2.8K OLED touch display, Intel Core Ultra 7 and all-day battery.',
                'specs' => ['Processor' => 'Intel Core Ultra 7 155H', 'RAM' => '16 GB', 'Storage' => '1 TB SSD', 'Display' => '14" 2.8K OLED Touch', 'OS' => 'Windows 11']],
            ['category' => $laptops, 'name' => 'Acer Swift Go 14', 'brand' => 'Acer', 'price' => 849.00, 'stock' => 15, 'is_featured' => false,
                'description' => 'Lightweight everyday ultrabook with an OLED screen and fast charging.',
                'specs' => ['Processor' => 'Intel Core Ultra 5 125H', 'RAM' => '16 GB', 'Storage' => '512 GB SSD', 'Display' => '14" 2.8K OLED']],
            ['category' => $laptops, 'name' => 'MSI Katana 15', 'brand' => 'MSI', 'price' => 1299.00, 'stock' => 9, 'is_featured' => true,
                'description' => 'Mid-range gaming laptop with RTX 4060 and a 144Hz display.',
                'specs' => ['Processor' => 'Intel Core i7-13620H', 'RAM' => '16 GB DDR5', 'Storage' => '1 TB NVMe', 'GPU' => 'NVIDIA RTX 4060 8GB', 'Display' => '15.6" FHD 144Hz']],
            ['category' => $accessories, 'name' => 'Logitech MX Keys S', 'brand' => 'Logitech', 'price' => 109.00, 'stock' => 40, 'is_featured' => false,
                'description' => 'Premium low-profile wireless keyboard with smart backlighting.',
                'specs' => ['Layout' => 'Full-size', 'Backlight' => 'Smart', 'Battery' => '10 days (backlit)']],
            ['category' => $accessories, 'name' => 'Razer DeathAdder V3', 'brand' => 'Razer', 'price' => 69.00, 'stock' => 35, 'is_featured' => false,
                'description' => 'Ergonomic esports mouse, ultra-light with Focus Pro 30K sensor.',
                'specs' => ['Sensor' => 'Focus Pro 30K', 'Weight' => '59 g', 'Connectivity' => 'Wired/Wireless']],
            ['category' => $accessories, 'name' => 'Anker 7-in-1 USB-C Hub', 'brand' => 'Anker', 'price' => 45.00, 'stock' => 60, 'is_featured' => false,
                'description' => 'Expand your laptop with HDMI 4K, USB-A, SD/microSD and 100W PD.',
                'specs' => ['Ports' => 'HDMI 4K, 2× USB-A, USB-C PD, SD, microSD', 'PD' => '100W']],
            ['category' => $monitors, 'name' => 'Samsung Odyssey G7 32"', 'brand' => 'Samsung', 'price' => 699.00, 'stock' => 8, 'is_featured' => true,
                'description' => 'Curved 1000R gaming monitor, QHD 240Hz, 1ms.',
                'specs' => ['Size' => '32"', 'Resolution' => '2560×1440', 'Refresh' => '240Hz', 'Panel' => 'VA 1000R']],
            ['category' => $monitors, 'name' => 'Dell UltraSharp U2723QE 27"', 'brand' => 'Dell', 'price' => 619.00, 'stock' => 11, 'is_featured' => false,
                'description' => '27" 4K IPS Black panel with USB-C hub and exceptional contrast.',
                'specs' => ['Size' => '27"', 'Resolution' => '3840×2160', 'Panel' => 'IPS Black', 'Ports' => 'USB-C 90W, RJ45, USB hub']],
            ['category' => $audio, 'name' => 'Sony WH-1000XM5', 'brand' => 'Sony', 'price' => 349.00, 'stock' => 20, 'is_featured' => true,
                'description' => 'Industry-leading noise-cancelling over-ear headphones, 30h battery.',
                'specs' => ['Type' => 'Over-ear ANC', 'Battery' => '30 hours', 'Codecs' => 'LDAC, AAC, SBC']],
            ['category' => $audio, 'name' => 'Apple AirPods Pro (2nd Gen)', 'brand' => 'Apple', 'price' => 249.00, 'stock' => 30, 'is_featured' => true,
                'description' => 'Active noise cancellation, Adaptive Audio and USB-C charging case.',
                'specs' => ['Type' => 'In-ear ANC', 'Chip' => 'Apple H2', 'Case' => 'USB-C MagSafe']],
            ['category' => $storage, 'name' => 'Samsung T7 Shield 2TB SSD', 'brand' => 'Samsung', 'price' => 179.00, 'stock' => 45, 'is_featured' => false,
                'description' => 'Rugged portable SSD, up to 1050 MB/s, IP65 water/dust resistant.',
                'specs' => ['Capacity' => '2 TB', 'Speed' => 'Up to 1050 MB/s', 'Interface' => 'USB 3.2 Gen 2']],
            ['category' => $storage, 'name' => 'SanDisk Extreme PRO 1TB SSD', 'brand' => 'SanDisk', 'price' => 149.00, 'stock' => 38, 'is_featured' => false,
                'description' => 'Pocket-sized NVMe portable SSD, up to 2000 MB/s, forged aluminium.',
                'specs' => ['Capacity' => '1 TB', 'Speed' => 'Up to 2000 MB/s', 'Interface' => 'USB 3.2 Gen 2x2']],
            ['category' => $storage, 'name' => 'TP-Link Archer AX73 Router', 'brand' => 'TP-Link', 'price' => 129.00, 'stock' => 22, 'is_featured' => false,
                'description' => 'Wi-Fi 6 AX5400 dual-band router with 6 antennas for whole-home coverage.',
                'specs' => ['Standard' => 'Wi-Fi 6 (AX5400)', 'Bands' => 'Dual-band', 'Ports' => '4× Gigabit LAN']],

            // ---- round two: more depth per category ----
            ['category' => $monitors, 'name' => 'ASUS ProArt PA278CV 27"', 'brand' => 'ASUS', 'price' => 329.00, 'stock' => 14, 'is_featured' => false,
                'description' => 'Colour-accurate creator monitor, QHD IPS, 100% sRGB, USB-C 65W.',
                'specs' => ['Size' => '27"', 'Resolution' => '2560×1440', 'Panel' => 'IPS', 'Colour' => '100% sRGB']],
            ['category' => $monitors, 'name' => 'BenQ PD2706U 27"', 'brand' => 'BenQ', 'price' => 549.00, 'stock' => 9, 'is_featured' => false,
                'description' => '4K designer monitor with AQCOLOR, 95% DCI-P3 and USB-C.',
                'specs' => ['Size' => '27"', 'Resolution' => '3840×2160', 'Colour' => '95% DCI-P3', 'Ports' => 'USB-C 90W']],
            ['category' => $audio, 'name' => 'Bose QuietComfort Ultra', 'brand' => 'Bose', 'price' => 379.00, 'stock' => 16, 'is_featured' => true,
                'description' => 'Premium noise-cancelling headphones with immersive spatial audio.',
                'specs' => ['Type' => 'Over-ear ANC', 'Battery' => '24 hours', 'Spatial' => 'Bose Immersive Audio']],
            ['category' => $audio, 'name' => 'JBL Tune 770NC', 'brand' => 'JBL', 'price' => 99.00, 'stock' => 28, 'is_featured' => false,
                'description' => 'Wireless over-ear with adaptive noise cancelling and 70h battery.',
                'specs' => ['Type' => 'Over-ear ANC', 'Battery' => '70 hours', 'Connectivity' => 'Bluetooth 5.3']],
            ['category' => $storage, 'name' => 'Crucial X9 Pro 2TB SSD', 'brand' => 'Crucial', 'price' => 159.00, 'stock' => 33, 'is_featured' => false,
                'description' => 'Portable SSD up to 1050 MB/s, IP55 rated, pocket-sized.',
                'specs' => ['Capacity' => '2 TB', 'Speed' => 'Up to 1050 MB/s', 'Interface' => 'USB 3.2 Gen 2']],
            ['category' => $storage, 'name' => 'WD My Passport 4TB', 'brand' => 'Western Digital', 'price' => 119.00, 'stock' => 26, 'is_featured' => false,
                'description' => 'Portable hard drive with 256-bit AES hardware encryption.',
                'specs' => ['Capacity' => '4 TB', 'Interface' => 'USB 3.2 Gen 1', 'Security' => '256-bit AES']],
            ['category' => $accessories, 'name' => 'Logitech C920 HD Pro Webcam', 'brand' => 'Logitech', 'price' => 79.00, 'stock' => 40, 'is_featured' => false,
                'description' => 'Full HD 1080p webcam with stereo mics — crisp calls and streams.',
                'specs' => ['Resolution' => '1080p/30fps', 'Mic' => 'Dual stereo', 'Mount' => 'Clip / tripod']],
            ['category' => $accessories, 'name' => 'Logitech G502 X Gaming Mouse', 'brand' => 'Logitech', 'price' => 79.00, 'stock' => 30, 'is_featured' => false,
                'description' => 'Iconic gaming mouse with HERO 25K sensor and hybrid switches.',
                'specs' => ['Sensor' => 'HERO 25K', 'Buttons' => '13 programmable', 'Weight' => '89 g']],
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

        // Attach real product photos.
        $this->call(ProductImageSeeder::class);
    }
}
