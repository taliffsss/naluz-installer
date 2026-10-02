<?php

declare(strict_types=1);

namespace Naluz\Installer\Tests;

use Naluz\Installer\Input;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testParsesCommandArgumentsFlagsAndValues(): void
    {
        $i = new Input(['new', 'blog', '--dev', '--name=acme/blog', '-V', '--empty=']);
        $this->assertSame('new', $i->command());
        $this->assertSame('blog', $i->argument(0));
        $this->assertNull($i->argument(1));
        $this->assertTrue($i->has('dev'));
        $this->assertNull($i->option('dev'), 'a flag has no string value');
        $this->assertSame('acme/blog', $i->option('name'));
        $this->assertTrue($i->has('V'));
        $this->assertSame('', $i->option('empty'));
        $this->assertFalse($i->has('git'));
    }

    public function testValuesMayContainEqualsSigns(): void
    {
        $this->assertSame('a=b', (new Input(['new', 'x', '--k=a=b']))->option('k'));
    }

    public function testDoubleDashEndsOptions(): void
    {
        $i = new Input(['new', '--', '--weird-name']);
        $this->assertSame('--weird-name', $i->argument(0));
        $this->assertFalse($i->has('weird-name'));
    }

    public function testNoArguments(): void
    {
        $this->assertNull((new Input([]))->command());
        $this->assertNull((new Input(['--version']))->command());
    }
}
