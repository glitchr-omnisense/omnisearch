<?php

namespace Omnisearch\Tests;

use Omnisearch\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Omnisearch outside any framework: the harness's bare script
 * (docker/harness/bin/bare), in a PHP process of its own, builds the
 * registry by hand and reports what PHP loaded. On the memory engine,
 * nothing but omnisearch and the tests' objects: no Symfony, no Doctrine.
 * Through omnisearch/typesense, Doctrine (the entities) and
 * glitchr/typesense-bundle's root, still no class of a framework.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Console|Routing)|Symfony\\\\Bundle|Doctrine\\\\Bundle|Twig|Omnisearch\\\\Bridge\\\\Symfony|Typesense\\\\Bundle\\\\(?:TypesenseBundle|Bridge))\\b~';

    public function testTheMemoryEngineLoadsNothingButOmnisearch(): void
    {
        $report = self::bare(['--memory']);

        self::assertSame(3, $report['memory']['sent']);
        self::assertSame(['Fujisan in music', 'Mount Fuji at dawn'], $report['memory']['objects']);
        self::assertSame(['Fujisan'], $report['memory']['matched']);
        self::assertSame(['japan' => 2, 'music' => 1, 'travel' => 1], $report['memory']['facets']);
        self::assertContains(Registry::class, $report['memory']['loaded']['symbols']);
        self::assertSame([], array_values(preg_grep('~^(?:Symfony|Doctrine|Twig)\\\\(?!Polyfill)~', $report['memory']['loaded']['symbols'])), 'nothing of Symfony nor Doctrine');
    }

    public function testTypesenseThroughTheBundlesRootWithNoFramework(): void
    {
        if (!class_exists(\Omnisearch\Typesense\TypesenseDriverFactory::class) || !getenv('TYPESENSE_URL')) {
            self::markTestSkipped('omnisearch/typesense or a Typesense server is missing.');
        }
        $report = self::bare();

        self::assertArrayNotHasKey('errors', $report['typesense']);
        self::assertSame(200, $report['typesense']['status']);
        self::assertSame(2, $report['typesense']['found']);
        self::assertSame(['Fujisan in music', 'Mount Fuji'], $report['typesense']['objects'], 'the newest first, loaded in the hits\' order');
        self::assertSame(['Fuji'], $report['typesense']['matched'], 'Typesense names the prefix matched');
        self::assertEqualsCanonicalizing(['en-musique' => 1, 'voyages' => 1], $report['typesense']['facets']['tags'], 'equal counts, in Typesense\'s order');
        self::assertSame(1, $report['typesense']['infix'], '"isa" in "Fujisan" by infix');
        self::assertSame(1, $report['typesense']['filtered']);
        self::assertTrue($report['typesense']['schema']['fields'][0]['infix']);
        self::assertSame([], array_values(preg_grep(self::FRAMEWORK, $report['typesense']['loaded']['symbols'])), 'no class of a framework');
        self::assertContains('Typesense\Bundle\ORM\TypesenseManager', $report['typesense']['loaded']['symbols']);
    }

    /** @return array<string, mixed> */
    private static function bare(array $arguments = []): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, __DIR__.'/../docker/harness/bin/bare', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNISEARCH_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return $report;
    }
}
