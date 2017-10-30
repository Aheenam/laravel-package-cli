<?php
namespace Aheenam\LaravelPackageCli\Test;

use Aheenam\LaravelPackageCli\PackageGenerator;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Aheenam\LaravelPackageCli\InvalidPackageNameException;

class PackageGeneratorTest extends TestCase
{

    use MatchesSnapshots;

    /** @test */
    public function it_throws_exception_on_name_validation_fail ()
    {
        $this->expectException(InvalidPackageNameException::class);
        $filesystem = new Filesystem(new MemoryAdapter);

        (new PackageGenerator($filesystem, '/', 'dummy/dummy-package/asdf'))
            ->generate();

    }

    /** @test */
    public function it_generates_base_files ()
    {
        $filesystem = new Filesystem(new MemoryAdapter);

        (new PackageGenerator($filesystem, '/', 'dummy/dummy-package'))
            ->generateBaseFiles();

        $this->assertHasFile($filesystem, 'dummy-package');
        $this->assertHasFile($filesystem, 'dummy-package/.gitignore');
        $this->assertHasFile($filesystem, 'dummy-package/CHANGELOG.md');
        $this->assertHasFile($filesystem, 'dummy-package/README.md');
        $this->assertHasFile($filesystem, 'dummy-package/LICENSE');

        $contents = $filesystem->read('dummy-package/README.md');
        $templateContent = file_get_contents(__DIR__ . '/../template/README.md.stub');
        $templateContent = str_replace('${packageName}', 'dummy-package', $templateContent);
        $this->assertEquals($templateContent, $contents);

        $this->assertHasFile($filesystem, 'dummy-package/database/.gitkeep');
        $this->assertHasFile($filesystem, 'dummy-package/config/.gitkeep');

    }

    /** @test */
    public function it_generates_service_provider ()
    {
        $filesystem = new Filesystem(new MemoryAdapter);

        (new PackageGenerator($filesystem, '/', 'dummy/dummy-package'))
            ->generateServiceProvider();

        $this->assertHasFile($filesystem, 'dummy-package/src/DummyPackageServiceProvider.php');

        $contents = $filesystem->read('/dummy-package/src/DummyPackageServiceProvider.php');
        $this->assertContains('DummyPackageServiceProvider', $contents);
    }

    /** @test */
    public function it_generates_test_files ()
    {
        $filesystem = new Filesystem(new MemoryAdapter);

        (new PackageGenerator($filesystem, '/', 'dummy/dummy-package'))
            ->generateTestFiles();

        $this->assertHasFile($filesystem, 'dummy-package/tests');
        $this->assertHasFile($filesystem, 'dummy-package/tests/TestCase.php');
        $this->assertHasFile($filesystem, 'dummy-package/phpunit.xml');

        $contents = $filesystem->read('dummy-package/phpunit.xml');
        $this->assertContains('Dummy Test Suite', $contents);

        $contents = $filesystem->read('dummy-package/tests/TestCase.php');
        $this->assertContains('use Dummy\DummyPackage\DummyPackageServiceProvider;', $contents);
        $this->assertContains('namespace Dummy\DummyPackage\Test;', $contents);

    }

    /** @test */
    public function it_generates_composer_json ()
    {
        $filesystem = new Filesystem(new MemoryAdapter);

        (new PackageGenerator($filesystem, '/', 'dummy/dummy-package'))
            ->generateComposerJson();

        $this->assertHasFile($filesystem, 'dummy-package/composer.json');

        $contents = $filesystem->read('dummy-package/composer.json');
        $this->assertMatchesJsonSnapshot($contents);

        $this->assertContains('"name": "dummy/dummy-package"', $contents);
        $this->assertContains('"Dummy\\\\DummyPackage\\\\Test\\\\":', $contents);
        $this->assertContains('"Dummy\\\\DummyPackage\\\\":', $contents);
        $this->assertContains('"Dummy\\\\DummyPackage\\\\DummyPackageServiceProvider"', $contents);

    }

    /**
     * @param Filesystem $filesystem
     * @param string $file
     */
    protected function assertHasFile(Filesystem $filesystem, $file)
    {
        $this->assertTrue($filesystem->has($file), "$file does not exists");
    }

}