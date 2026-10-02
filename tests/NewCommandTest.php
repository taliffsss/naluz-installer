<?php

declare(strict_types=1);

namespace Naluz\Installer\Tests;

use Naluz\Installer\Input;
use Naluz\Installer\NewCommand;
use Naluz\Installer\Output;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NewCommandTest extends TestCase
{
    private string $root;
    private FakeRunner $runner;
    /** @var resource */
    private $out;
    /** @var resource */
    private $err;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/naluz-new-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0775, true);
        $this->runner = new FakeRunner();
        $this->out = fopen('php://memory', 'w+');
        $this->err = fopen('php://memory', 'w+');
        // composer create-project "creates" a skeleton
        $this->runner->onRun = function (array $cmd, ?string $cwd): void {
            if (in_array('create-project', $cmd, true)) {
                $target = $cmd[array_search('create-project', $cmd, true) + 2];
                mkdir($target . '/storage', 0775, true);
                file_put_contents($target . '/composer.json', json_encode(['name' => 'naluz/naluzphp', 'require' => ['php' => '^8.2']]));
                file_put_contents($target . '/naluz', '<?php');
                file_put_contents($target . '/.env.example', 'APP_KEY=');
            }
        };
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    private function remove(string $dir): void
    {
        foreach (glob($dir . '/{*,.[!.]*}', GLOB_BRACE) ?: [] as $f) {
            is_dir($f) ? $this->remove($f) : unlink($f);
        }
        @rmdir($dir);
    }

    private function command(?array $composer = ['composer'], bool $interactive = false, ?\Closure $confirm = null): NewCommand
    {
        return new NewCommand($this->runner, new Output($this->out, $this->err, false), $composer, $this->root, $interactive, 'php', $confirm);
    }

    private function stderr(): string
    {
        rewind($this->err);
        return (string) stream_get_contents($this->err);
    }

    private function stdout(): string
    {
        rewind($this->out);
        return (string) stream_get_contents($this->out);
    }

    public function testCreatesTheProjectWithComposerAndPreparesIt(): void
    {
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'blog'])));

        $create = $this->runner->calls[0][0];
        $this->assertSame(['composer', 'create-project', 'naluz/naluzphp', $this->root . '/blog'], array_slice($create, 0, 4));
        $this->assertContains('--prefer-dist', $create);
        $this->assertContains('--no-interaction', $create, 'non-interactive when there is no terminal');
        $this->assertNotContains('--no-install', $create);
        $repo = json_decode(substr((string) array_values(array_filter($create, fn ($a) => str_starts_with($a, '--repository=')))[0], 13), true);
        $this->assertSame(['type' => 'vcs', 'url' => 'https://github.com/taliffsss/naluzphp-framework'], $repo);

        $this->assertSame(['php', 'naluz', 'key:generate', '--jwt'], $this->runner->calls[1][0]);
        $this->assertSame($this->root . '/blog', $this->runner->calls[1][1], 'runs inside the new project');
        $this->assertFileExists($this->root . '/blog/storage/database.sqlite');
        $this->assertStringContainsString('cd blog', $this->stdout());
        $this->assertStringContainsString('php naluz migrate', $this->stdout());
        $this->assertCount(2, $this->runner->calls, 'no migrations, no git unless asked');
    }

    public function testOptionsAreTranslated(): void
    {
        $this->command()->handle(new Input(['new', 'blog', '--release=^1.2', '--no-install']));
        $create = $this->runner->calls[0][0];
        $this->assertSame('^1.2', $create[4]);
        $this->assertContains('--no-install', $create);
        $this->assertContains('--no-scripts', $create, "the skeleton's post-create script needs vendor/");
        $this->assertCount(1, $this->runner->calls, 'nothing is run inside a project that has no dependencies');
        $this->assertFileExists($this->root . '/blog/.env', '.env is still created');
        $this->assertStringContainsString('composer install', $this->stdout());

        $this->runner->calls = [];
        $this->command()->handle(new Input(['new', 'other', '--dev']));
        $this->assertSame('dev-master', $this->runner->calls[0][0][4]);
    }

    public function testInteractiveTerminalAllowsComposerPrompts(): void
    {
        $this->command(interactive: true, confirm: fn () => false)->handle(new Input(['new', 'blog']));
        $this->assertNotContains('--no-interaction', $this->runner->calls[0][0]);
    }

    public function testMigrationsAndGit(): void
    {
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'blog', '--migrate', '--git'])));
        $lines = $this->runner->lines();
        $this->assertContains('php naluz migrate', $lines);
        $this->assertContains('git init --quiet', $lines);
        $this->assertContains('git add -A', $lines);
        $this->assertContains('git commit --quiet -m Initial commit', $lines);
        $this->assertStringNotContainsString('  php naluz migrate', $this->stdout(), 'not suggested again once done');
    }

    public function testInteractiveMigrationPrompt(): void
    {
        $asked = [];
        $this->command(interactive: true, confirm: function (string $q) use (&$asked) {
            $asked[] = $q;
            return true;
        })->handle(new Input(['new', 'blog']));
        $this->assertCount(1, $asked);
        $this->assertContains('php naluz migrate', $this->runner->lines());

        $this->runner->calls = [];
        $this->command(interactive: true, confirm: fn () => false)->handle(new Input(['new', 'again']));
        $this->assertNotContains('php naluz migrate', $this->runner->lines());
    }

    public function testNonInteractiveNeverAsks(): void
    {
        $this->command(confirm: fn () => $this->fail('must not prompt'))->handle(new Input(['new', 'blog']));
        $this->addToAssertionCount(1);
    }

    public function testPackageNameIsWritten(): void
    {
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'blog', '--name=acme/blog'])));
        $json = json_decode((string) file_get_contents($this->root . '/blog/composer.json'), true);
        $this->assertSame('acme/blog', $json['name']);
        $this->assertSame(['php' => '^8.2'], $json['require'], 'the rest of composer.json is untouched');
    }

    public function testComposerFailureIsReportedAndStopsEverything(): void
    {
        $this->runner->exitCodes['create-project'] = 2;
        $this->assertSame(2, $this->command()->handle(new Input(['new', 'blog', '--git', '--migrate'])));
        $this->assertCount(1, $this->runner->calls);
        $this->assertStringContainsString('project was not created', $this->stderr());
    }

    public function testStepFailuresWarnButDoNotFail(): void
    {
        $this->runner->exitCodes['migrate'] = 1;
        $this->runner->exitCodes['commit'] = 1;
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'blog', '--migrate', '--git'])));
        $this->assertStringContainsString('Migrations failed', $this->stdout());
        $this->assertStringContainsString('could not commit', $this->stdout());
        $this->assertStringContainsString('php naluz migrate', $this->stdout(), 'tells the user to retry');
    }

    public function testComposerMissing(): void
    {
        $this->assertSame(1, $this->command(null)->handle(new Input(['new', 'blog'])));
        $this->assertStringContainsString('Composer was not found', $this->stderr());
        $this->assertSame([], $this->runner->calls);
    }

    public function testRefusesANonEmptyDirectory(): void
    {
        mkdir($this->root . '/blog');
        file_put_contents($this->root . '/blog/keep.txt', 'mine');
        $this->assertSame(1, $this->command()->handle(new Input(['new', 'blog'])));
        $this->assertStringContainsString('not empty', $this->stderr());
        $this->assertSame([], $this->runner->calls);
        $this->assertFileExists($this->root . '/blog/keep.txt');
    }

    public function testAllowsAnEmptyExistingDirectoryAndTheCurrentDirectory(): void
    {
        mkdir($this->root . '/empty');
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'empty'])));

        $this->runner->calls = [];
        $this->remove($this->root);
        mkdir($this->root, 0775, true);
        $this->assertSame(0, $this->command()->handle(new Input(['new', '.'])));
        $this->assertSame($this->root, $this->runner->calls[0][0][3]);
        $this->assertStringNotContainsString('cd .', $this->stdout());
    }

    public function testRefusesDotInANonEmptyDirectory(): void
    {
        file_put_contents($this->root . '/.hidden', 'x');
        $this->assertSame(1, $this->command()->handle(new Input(['new', '.'])));
    }

    public function testUsageAndMissingParent(): void
    {
        $this->assertSame(1, $this->command()->handle(new Input(['new'])));
        $this->assertStringContainsString('Usage', $this->stderr());
        $this->assertSame(1, $this->command()->handle(new Input(['new', 'x', '--dir=/definitely/not/here'])));
    }

    public function testDirOptionChoosesTheParent(): void
    {
        mkdir($this->root . '/projects');
        $this->assertSame(0, $this->command()->handle(new Input(['new', 'blog', '--dir=' . $this->root . '/projects'])));
        $this->assertSame($this->root . '/projects/blog', $this->runner->calls[0][0][3]);
    }

    /** @return array<string,array{0:string}> */
    public static function badNames(): array
    {
        return [
            'traversal' => ['../evil'], 'parent' => ['..'], 'nested' => ['a/b'], 'absolute' => ['/etc'],
            'option-like' => ['-rf'], 'space' => ['a b'], 'semicolon' => ['a;rm'], 'dollar' => ['$HOME'],
            'backslash' => ['a\\b'], 'newline' => ["a\nb"], 'too long' => [str_repeat('a', 101)], 'hidden' => ['.git'],
        ];
    }

    #[DataProvider('badNames')]
    public function testUnsafeProjectNamesAreRejectedBeforeAnythingRuns(string $name): void
    {
        $this->assertSame(1, $this->command()->handle(new Input(['new', $name])));
        $this->assertStringContainsString('Invalid project name', $this->stderr());
        $this->assertSame([], $this->runner->calls);
    }

    /** @return array<string,array{0:string}> */
    public static function badReleases(): array
    {
        return [['--repository=evil'], ['-x'], ['1.0 && rm -rf /'], ['$(id)'], ["1.0\n2"], ['']];
    }

    #[DataProvider('badReleases')]
    public function testReleaseCannotInjectComposerOptions(string $release): void
    {
        $this->assertSame(1, $this->command()->handle(new Input(['new', 'blog', '--release=' . $release])));
        $this->assertSame([], $this->runner->calls);
    }

    #[DataProvider('goodReleases')]
    public function testReasonableReleaseConstraintsAreAccepted(string $release): void
    {
        $this->assertTrue(NewCommand::validRelease($release));
    }

    /** @return array<string,array{0:string}> */
    public static function goodReleases(): array
    {
        return [['1.2.0'], ['v1.2.0'], ['^1.2'], ['~1.2.0'], ['1.*'], ['>=1.2 <2.0'], ['dev-master'], ['dev-feature/x']];
    }

    #[DataProvider('badPackageNames')]
    public function testInvalidPackageNamesAreRejected(string $name): void
    {
        $this->assertSame(1, $this->command()->handle(new Input(['new', 'blog', '--name=' . $name])));
        $this->assertSame([], $this->runner->calls);
    }

    /** @return array<string,array{0:string}> */
    public static function badPackageNames(): array
    {
        return [['Acme/Blog'], ['blog'], ['acme/'], ['/blog'], ['acme/blog/extra'], ['acme/b l og'], ['acme/blog"x']];
    }
}
