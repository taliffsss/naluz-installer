<?php

declare(strict_types=1);

namespace Naluz\Installer\Tests;

use Naluz\Installer\Application;
use Naluz\Installer\Output;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $dir;
    private FakeRunner $runner;
    /** @var resource */
    private $out;
    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/naluz-app-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/bootstrap', 0775, true);
        $this->runner = new FakeRunner();
        $this->out = fopen('php://memory', 'w+');
        $this->err = fopen('php://memory', 'w+');
    }

    protected function tearDown(): void
    {
        foreach (['naluz', 'bootstrap/app.php'] as $f) {
            @unlink($this->dir . '/' . $f);
        }
        @rmdir($this->dir . '/bootstrap');
        @rmdir($this->dir);
    }

    private function app(): Application
    {
        return new Application(new Output($this->out, $this->err, false), $this->runner, $this->dir, false);
    }

    private function stdout(): string
    {
        rewind($this->out);
        return (string) stream_get_contents($this->out);
    }

    private function makeProject(): void
    {
        touch($this->dir . '/naluz');
        touch($this->dir . '/bootstrap/app.php');
    }

    public function testVersionAndList(): void
    {
        $this->assertSame(0, $this->app()->run(['naluz', '--version']));
        $this->assertStringContainsString('NaluzPHP Installer ' . Application::VERSION, $this->stdout());
        $this->assertSame(0, $this->app()->run(['naluz']));
        $this->assertSame(0, $this->app()->run(['naluz', 'list']));
        $this->assertStringContainsString('new <name>', $this->stdout());
        $this->assertSame([], $this->runner->calls);
    }

    public function testUnknownCommandOutsideAProject(): void
    {
        $this->assertSame(1, $this->app()->run(['naluz', 'migrate']));
        rewind($this->err);
        $this->assertStringContainsString('not defined', (string) stream_get_contents($this->err));
        $this->assertSame([], $this->runner->calls);
    }

    public function testInsideAProjectCommandsAreForwarded(): void
    {
        $this->makeProject();
        $this->runner->exitCodes['migrate'] = 3;
        $this->assertSame(3, $this->app()->run(['naluz', 'migrate', '--step=2']), "the project's exit code is returned");
        $this->assertSame([PHP_BINARY, 'naluz', 'migrate', '--step=2'], $this->runner->calls[0][0]);
        $this->assertSame($this->dir, $this->runner->calls[0][1]);
    }

    public function testBareCommandInsideAProjectListsProjectCommands(): void
    {
        $this->makeProject();
        $this->app()->run(['naluz']);
        $this->assertSame([PHP_BINARY, 'naluz', 'list'], $this->runner->calls[0][0]);
    }

    public function testVersionIsAlwaysTheInstallers(): void
    {
        $this->makeProject();
        $this->assertSame(0, $this->app()->run(['naluz', '--version']));
        $this->assertSame([], $this->runner->calls);
    }

    public function testNewIsNeverForwardedEvenInsideAProject(): void
    {
        $this->makeProject();
        putenv('COMPOSER_BINARY=' . $this->dir . '/naluz'); // any existing file
        try {
            $this->app()->run(['naluz', 'new', 'child', '--no-interaction']);
        } finally {
            putenv('COMPOSER_BINARY');
        }
        $this->assertContains('create-project', $this->runner->calls[0][0], 'ran composer, not the project script');
    }

    public function testAFileNamedNaluzAloneIsNotAProject(): void
    {
        touch($this->dir . '/naluz');
        $this->assertSame(1, $this->app()->run(['naluz', 'migrate']));
        $this->assertSame([], $this->runner->calls, 'an unrelated ./naluz file is never executed');
    }
}
