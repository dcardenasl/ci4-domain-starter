<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Guardrail to keep Controllers delegating to the Service layer instead of
 * touching Models or the database directly.
 *
 * Mirrors ServiceModelDependencyConventionsTest.php's whitelist mechanism,
 * applied one layer up: Controllers must go through Controller -> Service ->
 * Model, never Controller -> Model directly. A child project of this
 * template (a CMS built on ci4-domain-starter) hit real Controller/Model
 * coupling once it grew past a handful of resources and had to retrofit
 * this exact guardrail (`ControllerModelDependencyConventionsTest`) after
 * the fact. Starting zero-tolerance here means it never needs retrofitting.
 */
class ControllerModelDependencyConventionsTest extends CIUnitTestCase
{
    public function testControllersDoNotTouchModelsOrDatabaseDirectly(): void
    {
        $root = rtrim((string) ROOTPATH, DIRECTORY_SEPARATOR);
        $controllerDir = $root . DIRECTORY_SEPARATOR . 'app/Controllers';

        $allowed = [];
        sort($allowed);

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDir));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if (!is_string($source) || $source === '') {
                continue;
            }

            $touchesModelsDirectly = preg_match('/^use\s+App\\\\Models\\\\/m', $source) === 1
                || preg_match('/\bmodel\s*\(/', $source) === 1
                || preg_match('/\\\\?Database\s*::\s*connect\s*\(/', $source) === 1;

            if (!$touchesModelsDirectly) {
                continue;
            }

            $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR));
            $found[] = $relative;
        }

        sort($found);
        $this->assertSame(
            $allowed,
            $found,
            "Controllers with direct Model/Database access changed.\n" .
            'Delegate to a Service instead — update this whitelist only for justified exceptions.'
        );
    }
}
