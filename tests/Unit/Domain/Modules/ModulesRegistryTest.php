<?php

namespace Tests\Unit\Domain\Modules;

use App\Domain\Modules\ModulesRegistry;
use PHPUnit\Framework\TestCase;

class ModulesRegistryTest extends TestCase
{
    public function testSlugsAreUnique(): void
    {
        $slugs = array_map(fn ($module) => $module['slug'], ModulesRegistry::all());
        $this->assertSame($slugs, array_values(array_unique($slugs)));
    }

    public function testDependenciesHaveNoCycles(): void
    {
        $modules = ModulesRegistry::all();
        $graph = [];
        foreach ($modules as $module) {
            $graph[$module['slug']] = $module['deps'];
        }

        $visited = [];

        $visit = function ($node) use (&$visit, &$graph, &$visited) {
            if (isset($visited[$node]) && $visited[$node] === 1) {
                return true; // cycle
            }
            if (($visited[$node] ?? 0) === 2) {
                return false;
            }
            $visited[$node] = 1;
            foreach ($graph[$node] as $dep) {
                if ($visit($dep)) {
                    return true;
                }
            }
            $visited[$node] = 2;

            return false;
        };

        foreach (array_keys($graph) as $node) {
            if ($visit($node)) {
                $this->fail('Cycle detected in module dependencies');
            }
        }

        $this->assertTrue(true); // no cycles
    }
}
