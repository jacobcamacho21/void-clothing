<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The VOID catalog: eight designs, four sizes each.
 */
class CatalogSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, image: string, price: float, description: string}>
     */
    private const DESIGNS = [
        [
            'name' => 'No Signal',
            'image' => 'NoSignal.png',
            'price' => 350.00,
            'description' => 'Heavyweight cotton, oversized fit, drop shoulder, "SIGNAL LOST" block print, retro TV halftone graphic, cursive "Please Stand By" detailing with "est. 2024" branding.',
        ],
        [
            'name' => 'Bubbles',
            'image' => 'Bubbles.png',
            'price' => 300.00,
            'description' => 'Heavyweight cotton, drop shoulder construction, center-chest anime character illustration with "VOID" bubble-style typography.',
        ],
        [
            'name' => 'Philemon',
            'image' => 'Philemon.png',
            'price' => 400.00,
            'description' => 'Oversized cotton tee, drop shoulder construction, stippled anime-style character illustration with butterfly motif and "The butterfly, guiding transformation, embodying growth" typography at center chest.',
        ],
        [
            'name' => 'Time',
            'image' => 'Time.png',
            'price' => 350.00,
            'description' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Time" motif at center chest.',
        ],
        [
            'name' => 'Ghost Mode',
            'image' => 'GhostMode.png',
            'price' => 350.00,
            'description' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Ghost Mode" motif at center chest.',
        ],
        [
            'name' => 'Alice',
            'image' => 'Alice.png',
            'price' => 350.00,
            'description' => 'Heavyweight cotton, oversized fit, drop shoulder, center-chest anime illustration in purple, "VOID XIII" vertical print with Japanese script "Will you die for me?".',
        ],
        [
            'name' => 'Evoker',
            'image' => 'Evoker.png',
            'price' => 400.00,
            'description' => 'Oversized cotton tee, drop shoulder construction, "VOID" block lettering, anime-style character halftone graphic with "evoker" script at center chest.',
        ],
        [
            'name' => 'Temperance',
            'image' => 'Temperance.png',
            'price' => 350.00,
            'description' => 'Oversized cotton tee, drop shoulder construction, anime-style character illustration with "Temperance" motif at center chest.',
        ],
    ];

    public function run(): void
    {
        $sizes = config('void.sizes');

        foreach (self::DESIGNS as $design) {
            $product = Product::firstOrCreate(
                ['slug' => Str::slug($design['name'])],
                [
                    'name' => $design['name'],
                    'category' => 'General',
                    'description' => $design['description'],
                    'image' => $design['image'],
                    'is_active' => true,
                ]
            );

            foreach ($sizes as $size => $abbr) {
                $product->variants()->firstOrCreate(
                    ['size' => $size],
                    [
                        'sku' => sprintf(
                            'VD-%s%03d-%s',
                            Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', $design['name']), 0, 2)),
                            $product->id,
                            $abbr
                        ),
                        'price' => $design['price'],
                        'stock' => 12,
                    ]
                );
            }
        }
    }
}
