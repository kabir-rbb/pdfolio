<?php

namespace Tests\Feature;

use Tests\TestCase;

class ToolApiTest extends TestCase
{
    public function test_it_returns_all_tools_from_registry(): void
    {
        $response = $this->getJson('/api/tools');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'slug',
                'title',
                'description',
                'icon',
                'color',
                'multiple',
                'accept',
                'acceptLabel',
                'minFiles',
                'action',
                'options',
            ],
        ]);
    }
}
