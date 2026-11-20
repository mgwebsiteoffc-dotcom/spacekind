<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Public editorial references only. Never creates accounts, client records or project claims. */
class InspirationSeeder extends Seeder
{
    public function run(): void
    {
        $edits = [
            ['living', 'Living · Warm minimalism', 'Less noise. More living.', 'A warm neutral living room with a low sofa and an oak coffee table'],
            ['kitchen', 'Kitchen · Modern naturals', 'The heart of your home.', 'A light-filled kitchen with a pale stone island and garden views'],
            ['bedroom', 'Bedroom · Quiet comfort', 'A slower kind of space.', 'A softly lit bedroom in muted tones'],
        ];
        foreach ($edits as $position => [$key, $category, $headline, $alt]) {
            DB::table('inspiration_edits')->insertOrIgnore([
                'image_key' => $key, 'category' => $category, 'headline' => $headline,
                'alt' => $alt, 'position' => $position,
            ]);
        }
    }
}
