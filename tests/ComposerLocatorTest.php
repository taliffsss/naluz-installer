<?php

declare(strict_types=1);

namespace Naluz\Installer\Tests;

use Naluz\Installer\ComposerLocator;
use PHPUnit\Framework\TestCase;

final class ComposerLocatorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-inst-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/bin', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{bin/*,*}', GLOB_BRACE) ?: [] as $f) {
            is_file($f) && unlink($f);
        }
        @rmdir($this->dir . '/bin');
        @rmdir($this->dir);
    }

    public function testFindsComposerOnPath(): void
    {
        touch($this->dir . '/bin/composer');
        $this->assertSame([$this->dir . '/bin/composer'], ComposerLocator::find(null, ['/nonexistent', $this->dir . '/bin']));
    }

    public function testPharIsRunThroughPhp(): void
    {
        touch($this->dir . '/bin/composer.phar');
        $this->assertSame(['/usr/bin/php', $this->dir . '/bin/composer.phar'], ComposerLocator::find(null, [$this->dir . '/bin'], null, '/usr/bin/php'));
    }

    public function testEnvironmentOverrideWinsAndMustExist(): void
    {
        touch($this->dir . '/bin/composer');
        touch($this->dir . '/custom');
        $this->assertSame([$this->dir . '/custom'], ComposerLocator::find($this->dir . '/custom', [$this->dir . '/bin']));
        $this->assertNull(ComposerLocator::find($this->dir . '/missing', [$this->dir . '/bin']), 'a wrong COMPOSER_BINARY is not silently ignored');
    }

    public function testFallsBackToAPharInTheWorkingDirectory(): void
    {
        touch($this->dir . '/composer.phar');
        $this->assertSame(['/usr/bin/php', $this->dir . '/composer.phar'], ComposerLocator::find(null, [], $this->dir, '/usr/bin/php'));
    }

    public function testNothingFound(): void
    {
        $this->assertNull(ComposerLocator::find(null, [$this->dir . '/bin'], $this->dir));
    }
}
