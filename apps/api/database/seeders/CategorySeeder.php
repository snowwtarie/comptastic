<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Colors reproduce CAT_COLORS from apps/web/src/stores/ledger.js.
        // "Revenus" has no color in the frontend (it's never charted by
        // category color) — #16a34a picked here as a neutral placeholder.
        $categories = [
            ['name' => 'Revenus', 'color_hex' => '#16a34a', 'is_income' => true],
            ['name' => 'Logement', 'color_hex' => '#4338ca', 'is_income' => false],
            ['name' => 'Alimentation', 'color_hex' => '#4f46e5', 'is_income' => false],
            ['name' => 'Transport', 'color_hex' => '#6366f1', 'is_income' => false],
            ['name' => 'Loisirs', 'color_hex' => '#818cf8', 'is_income' => false],
            ['name' => 'Santé', 'color_hex' => '#a5b4fc', 'is_income' => false],
            ['name' => 'Autres', 'color_hex' => '#cbd5e1', 'is_income' => false],
        ];

        foreach ($categories as $index => $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [...$category, 'sort_order' => $index],
            );
        }
    }
}
