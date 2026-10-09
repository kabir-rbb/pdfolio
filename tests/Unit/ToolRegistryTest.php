<?php

namespace Tests\Unit;

use App\Support\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    public function test_it_returns_all_tools(): void
    {
        $tools = ToolRegistry::all();

        $this->assertIsArray($tools);
        $this->assertCount(11, $tools);
    }

    public function test_every_tool_has_required_schema_fields(): void
    {
        $requiredKeys = [
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
        ];

        foreach (ToolRegistry::all() as $tool) {
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $tool, "Tool '{$tool['slug']}' is missing key '{$key}'.");
            }

            $this->assertIsString($tool['slug']);
            $this->assertIsString($tool['title']);
            $this->assertIsString($tool['description']);
            $this->assertIsString($tool['icon']);
            $this->assertIsString($tool['color']);
            $this->assertIsBool($tool['multiple']);
            $this->assertIsString($tool['accept']);
            $this->assertIsString($tool['acceptLabel']);
            $this->assertIsInt($tool['minFiles']);
            $this->assertGreaterThanOrEqual(1, $tool['minFiles']);
            $this->assertIsString($tool['action']);
            $this->assertIsArray($tool['options']);
        }
    }

    public function test_tool_slugs_are_unique(): void
    {
        $slugs = array_column(ToolRegistry::all(), 'slug');

        $this->assertCount(count($slugs), array_unique($slugs));
    }

    public function test_can_retrieve_tool_by_slug(): void
    {
        $tool = ToolRegistry::get('office-to-pdf');

        $this->assertNotNull($tool);
        $this->assertSame('office-to-pdf', $tool['slug']);
        $this->assertSame('Office to PDF', $tool['title']);
        $this->assertFalse($tool['multiple']);
        $this->assertSame(1, $tool['minFiles']);
    }

    public function test_retrieving_unknown_slug_returns_null(): void
    {
        $this->assertNull(ToolRegistry::get('non-existent-tool'));
    }

    public function test_tool_options_have_valid_structure(): void
    {
        foreach (ToolRegistry::all() as $tool) {
            foreach ($tool['options'] as $option) {
                $this->assertArrayHasKey('name', $option);
                $this->assertArrayHasKey('label', $option);
                $this->assertArrayHasKey('type', $option);
                $this->assertArrayHasKey('default', $option);

                if ($option['type'] === 'select') {
                    $this->assertArrayHasKey('choices', $option);
                    $this->assertIsArray($option['choices']);
                    foreach ($option['choices'] as $choice) {
                        $this->assertArrayHasKey('value', $choice);
                        $this->assertArrayHasKey('label', $choice);
                    }
                }
            }
        }
    }
}
