<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

/**
 * Attaches real product photos (stable, hotlink-friendly stock image URLs) to each product.
 * Idempotent: re-running replaces a product's images. URLs are stored absolute, so no
 * storage symlink is needed (works out-of-the-box on shared hosting like Hostinger).
 *
 * Run on its own with:  php artisan db:seed --class=Database\\Seeders\\ProductImageSeeder
 */
class ProductImageSeeder extends Seeder
{
    private const U = 'https://images.unsplash.com/';
    private const Q = '?w=1000&q=80&auto=format&fit=crop';

    /** Map: product name => [image photo-ids] (first is primary). */
    private const IMAGES = [
        'Dell XPS 13 9340'                    => ['photo-1496181133206-80ce9b88a853', 'photo-1593642702821-c8da6771f0c6'],
        'Apple MacBook Air 15" M3'            => ['photo-1517336714731-489689fd1ca8', 'photo-1611186871348-b1ce696e52c9'],
        'Lenovo ThinkPad X1 Carbon Gen 12'    => ['photo-1588872657578-7efd1f1555ed', 'photo-1496181133206-80ce9b88a853'],
        'ASUS ROG Zephyrus G14'               => ['photo-1593642702821-c8da6771f0c6', 'photo-1587829741301-dc798b83add3'],
        'Logitech MX Master 3S'               => ['photo-1527864550417-7fd91fc51a46'],
        'Keychron K2 Pro Mechanical Keyboard' => ['photo-1541140532154-b024d705b90a', 'photo-1625842268584-8f3296236761'],
        'Anker 65W GaN Charger'               => ['photo-1484704849700-f032a568e944'],
        'LG UltraFine 27" 4K Monitor'         => ['photo-1527443224154-c4a3942d3acf', 'photo-1593344484962-796055d4a3a4'],

        'HP Spectre x360 14'                  => ['photo-1587829741301-dc798b83add3', 'photo-1593642702821-c8da6771f0c6'],
        'Acer Swift Go 14'                    => ['photo-1588872657578-7efd1f1555ed', 'photo-1496181133206-80ce9b88a853'],
        'MSI Katana 15'                       => ['photo-1593642702821-c8da6771f0c6', 'photo-1587829741301-dc798b83add3'],
        'Logitech MX Keys S'                  => ['photo-1625842268584-8f3296236761', 'photo-1541140532154-b024d705b90a'],
        'Razer DeathAdder V3'                 => ['photo-1527864550417-7fd91fc51a46', 'photo-1525547719571-a2d4ac8945e2'],
        'Anker 7-in-1 USB-C Hub'              => ['photo-1484704849700-f032a568e944', 'photo-1546435770-a3e426bf472b'],
        'Samsung Odyssey G7 32"'              => ['photo-1593305841991-05c297ba4575', 'photo-1593344484962-796055d4a3a4'],
        'Dell UltraSharp U2723QE 27"'         => ['photo-1593344484962-796055d4a3a4', 'photo-1527443224154-c4a3942d3acf'],
        'Sony WH-1000XM5'                     => ['photo-1505740420928-5e560c06d30e', 'photo-1572569511254-d8f925fe2cbb'],
        'Apple AirPods Pro (2nd Gen)'         => ['photo-1572569511254-d8f925fe2cbb', 'photo-1505740420928-5e560c06d30e'],
        'Samsung T7 Shield 2TB SSD'           => ['photo-1618384887929-16ec33fab9ef', 'photo-1574920162043-b872873f19c8'],
        'SanDisk Extreme PRO 1TB SSD'         => ['photo-1574920162043-b872873f19c8', 'photo-1551816230-ef5deaed4a26'],
        'TP-Link Archer AX73 Router'          => ['photo-1525547719571-a2d4ac8945e2', 'photo-1618384887929-16ec33fab9ef'],

        'ASUS ProArt PA278CV 27"'             => ['photo-1527443224154-c4a3942d3acf', 'photo-1593305841991-05c297ba4575'],
        'BenQ PD2706U 27"'                    => ['photo-1593344484962-796055d4a3a4', 'photo-1527443224154-c4a3942d3acf'],
        'Bose QuietComfort Ultra'             => ['photo-1505740420928-5e560c06d30e', 'photo-1572569511254-d8f925fe2cbb'],
        'JBL Tune 770NC'                      => ['photo-1572569511254-d8f925fe2cbb', 'photo-1505740420928-5e560c06d30e'],
        'Crucial X9 Pro 2TB SSD'              => ['photo-1574920162043-b872873f19c8', 'photo-1618384887929-16ec33fab9ef'],
        'WD My Passport 4TB'                  => ['photo-1551816230-ef5deaed4a26', 'photo-1574920162043-b872873f19c8'],
        'Logitech C920 HD Pro Webcam'         => ['photo-1546435770-a3e426bf472b', 'photo-1484704849700-f032a568e944'],
        'Logitech G502 X Gaming Mouse'        => ['photo-1527864550417-7fd91fc51a46', 'photo-1525547719571-a2d4ac8945e2'],
    ];

    public function run(): void
    {
        foreach (self::IMAGES as $name => $ids) {
            $product = Product::where('name', $name)->first();
            if (!$product) {
                continue;
            }
            ProductImage::where('product_id', $product->id)->delete();
            foreach (array_values($ids) as $i => $id) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'path'       => self::U . $id . self::Q,
                    'is_primary' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
