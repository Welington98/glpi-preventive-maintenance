<?php

use PHPUnit\Framework\TestCase;

// Testes de fumaça: não inicializam o GLPI (setup.php faz die() sem a
// constante GLPI_ROOT), então validam apenas os arquivos de metadados do
// plugin de forma estática.
final class ManifestTest extends TestCase
{
    public function testManifestIsValidJson(): void
    {
        $path = __DIR__ . '/../manifest.json';
        $this->assertFileExists($path);

        $manifest = json_decode(file_get_contents($path), true);

        $this->assertIsArray($manifest);
        $this->assertSame('preventivemaintenance', $manifest['name']);
        $this->assertArrayHasKey('version', $manifest);
    }

    public function testSetupDeclaresRequiredFunctions(): void
    {
        $contents = file_get_contents(__DIR__ . '/../setup.php');

        foreach ([
            'plugin_version_preventivemaintenance',
            'plugin_preventivemaintenance_install',
            'plugin_preventivemaintenance_uninstall',
            'plugin_init_preventivemaintenance',
        ] as $function) {
            $this->assertStringContainsString("function {$function}(", $contents);
        }
    }
}
