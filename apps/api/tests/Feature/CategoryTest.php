<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_categories_ordered_by_sort_order(): void
    {
        Category::factory()->create(['name' => 'B', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'A', 'sort_order' => 0]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/categories');

        $response->assertOk();
        $this->assertSame('A', $response->json('data.0.name'));
        $this->assertSame('B', $response->json('data.1.name'));
    }
}
