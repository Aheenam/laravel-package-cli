<?php

namespace Aheenam\LaravelPackageCli;

use Aheenam\LaravelPackageCli\Exceptions\DirectoryAlreadyExistsException;
use Aheenam\LaravelPackageCli\Exceptions\InvalidPackageNameException;
use Carbon\Carbon;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\MountManager;

class PackageGenerator
{
    /**
     * the filesystem.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * the target path for generating the package.
     *
     * @var string
     */
    protected $path;

    /**
     * options for the generator.
     *
     * @var array
     */
    protected $options;

    /**
     * the name of the package.
     *
     * @var string
     */
    protected $packageName;

    /**
     * the name of the package vendor.
     *
     * @var string
     */
    protected $vendorName;

    /**
     * additional package information.
     *
     * @var array
     */
    protected $packageInformation;

    /**
     * path to the package.
     *
     * @var string
     */
    protected $packagePath;

    /**
     * the filesystem for the source files.
     *
     * @var Filesystem
     */
    protected $template;

    /**
     * the manager to handle different filesystems.
     *
     * @var MountManager
     */
    protected $manager;

    /**
     * list of all base file that every package
     * will have.
     *
     * @var array
     */
    protected $baseFiles;

    /**
     * PackageGenerator constructor.
     *
     * @param FilesystemInterface $filesystem
     * @param string              $path
     * @param string              $packageName
     * @param array               $options
     */
    public function __construct(FilesystemInterface $filesystem, $path, $packageName, $options = [])
    {
        $this->filesystem = $filesystem;
        $this->path = $path;
        $this->options = $options;

        list($this->vendorName, $this->packageName) = $this->resolvePackageName($packageName);

        if ($this->filesystem->has($this->path.$this->packageName) &&
            (isset($this->options['force']) && !$this->options['force'] || !isset($this->options['force']))) {
            throw new DirectoryAlreadyExistsException();
        }

        $this->packagePath = $this->path.$this->packageName.'/';

        $this->template = new Filesystem(new Local(__DIR__.'/../template/'));

        $this->manager = new MountManager([
            'template' => $this->template,
            'package'  => $filesystem,
        ]);

        $this->baseFiles = [
            '.gitignore',
            'CHANGELOG.md',
            'README.md',
        ];

        // create package info
        $this->packageInformation = $this->buildPackageInformation();
    }

    /**
     * runs all needed generation methods.
     *
     * @return void
     */
    public function generate()
    {
        // create package directory
        $this->filesystem->createDir($this->path.$this->packageName);

        // generate the base files
        $this->generateBaseFiles();

        // generate config file
        $this->generateConfigFile();

        // generate ServiceProvider
        $this->generateServiceProvider();

        // generate the LICENSE
        $this->generateLicense();

        // generate test based files
        $this->generateTestFiles();

        // generate composer.json
        $this->generateComposerJson();
    }

    /**
     * generates the base files by copying, name replacing
     * and renaming them.
     *
     * @return void
     */
    public function generateBaseFiles()
    {

        // copy base files stubs and edit them properly
        foreach ($this->baseFiles as $fileName) {
            // copy the stub
            $this->manager->copy("template://$fileName.stub", "package://$this->packagePath/$fileName.stub");

            // update files content
            $this->updateFileContent($this->packagePath.$fileName.'.stub');

            // rename
            $this->filesystem->rename($this->packagePath.$fileName.'.stub', $this->packagePath.$fileName);
        }

        // generate directories
        $this->filesystem->write($this->packagePath.'database/.gitkeep', '');
    }

    /**
     * generates the config file.
     *
     * @return void
     */
    public function generateConfigFile()
    {

        // check --no-config flag
        if (isset($this->options['no-config']) && $this->options['no-config']) {
            return;
        }

        // copy the stub
        $this->manager->copy(
            'template://config/config.php.stub',
            "package://$this->packagePath/config/config.php.stub"
        );

        // rename file
        $this->filesystem->rename(
            $this->packagePath.'config/config.php.stub',
            $this->packagePath.'config/'.$this->packageName.'.php'
        );
    }

    /**
     * Generates the content of the LICENSE if one is selected.
     */
    public function generateLicense()
    {
        // check if license is set
        if (!isset($this->options['license']) || $this->options['license'] === '') {
            $this->filesystem->write($this->packagePath.'LICENSE', '');

            return;
        }

        switch (strtolower($this->options['license'])) {
            case 'mit':
                $this->manager->copy(
                    'template://license/mit.stub',
                    "package://$this->packagePath/LICENSE.stub"
                );
                break;
            case 'apache 2.0':
                $this->manager->copy(
                    'template://license/apache20.stub',
                    "package://$this->packagePath/LICENSE.stub"
                );
                break;
            case 'gnu gpl v3':
                $this->manager->copy(
                    'template://license/gnu_gpl_v3.stub',
                    "package://$this->packagePath/LICENSE.stub"
                );
                break;
            default:
                return;
        }

        // update files content
        $this->updateFileContent($this->packagePath.'LICENSE.stub');

        // rename file
        $this->filesystem->rename(
            $this->packagePath.'LICENSE.stub',
            $this->packagePath.'LICENSE'
        );
    }

    /**
     * Creates the service provider.
     *
     * @return void
     */
    public function generateServiceProvider()
    {
        // copy the stub
        $this->manager->copy(
            'template://src/PackageServiceProvider.php.stub',
            "package://$this->packagePath/src/PackageServiceProvider.php.stub"
        );

        // update files content
        $this->updateFileContent("{$this->packagePath}/src/PackageServiceProvider.php.stub");

        // rename file
        $this->filesystem->rename(
            $this->packagePath.'src/PackageServiceProvider.php.stub',
            $this->packagePath.'src/'.$this->packageInformation['serviceProvider'].'.php'
        );
    }

    /**
     * Creates the files for testing purposes.
     *
     * @return void
     */
    public function generateTestFiles()
    {
        $this->filesystem->createDir($this->packagePath.'tests');

        // copy the stubs
        $this->manager->copy(
            'template://tests/TestCase.php.stub',
            "package://$this->packagePath/tests/TestCase.php.stub"
        );
        $this->manager->copy(
            'template://phpunit.xml.stub',
            "package://$this->packagePath/phpunit.xml.stub"
        );

        // update file contents
        $this->updateFileContent("{$this->packagePath}/tests/TestCase.php.stub");
        $this->updateFileContent("{$this->packagePath}/phpunit.xml.stub");

        // rename files
        $this->filesystem->rename(
            $this->packagePath.'tests/TestCase.php.stub',
            $this->packagePath.'tests/TestCase.php'
        );

        $this->filesystem->rename(
            $this->packagePath.'phpunit.xml.stub',
            $this->packagePath.'phpunit.xml'
        );
    }

    /**
     * Create the composer.json file.
     *
     * @return void
     */
    public function generateComposerJson()
    {
        // copy the stub
        $this->manager->copy(
            'template://composer.json.stub',
            "package://$this->packagePath/composer.json.stub"
        );

        // update file contents
        $this->updateFileContent("{$this->packagePath}/composer.json.stub");

        // rename files
        $this->filesystem->rename(
            $this->packagePath.'composer.json.stub',
            $this->packagePath.'composer.json'
        );
    }

    /**
     * replaces the names in the given file.
     *
     * @param $file
     *
     * @return void
     */
    protected function updateFileContent($file)
    {
        $contents = $this->filesystem->read($file);

        foreach ($this->packageInformation as $field => $info) {
            $contents = str_replace('${'.$field.'}', $info, $contents);
        }

        $this->filesystem->update($file, $contents);
    }

    /**
     * builds information about package that is written in
     * the stubs.
     *
     * @return array
     */
    protected function buildPackageInformation()
    {
        $serviceProviderName = $this->kebabToCapitalize($this->packageName).'ServiceProvider';

        return [
            'namespace'         => ucfirst($this->vendorName).'\\'.$this->kebabToCapitalize($this->packageName),
            'serviceProvider'   => $serviceProviderName,
            'packageName'       => $this->packageName,
            'vendorName'        => ucfirst($this->vendorName),
            'fullPackageName'   => strtolower($this->vendorName).'/'.strtolower($this->packageName),
            'composerNamespace' => ucfirst($this->vendorName).'\\\\'.$this->kebabToCapitalize($this->packageName),
            'currentYear'       => Carbon::now()->year,
        ];
    }

    /**
     * @param $string
     *
     * @return string
     */
    protected function kebabToCapitalize($string)
    {
        return str_replace(
            ' ',
            '',
            ucwords(
                str_replace(
                    '-',
                    ' ',
                    $string
                )
            )
        );
    }

    /**
     * @param string $packageName
     *
     * @return array
     */
    protected function resolvePackageName($packageName)
    {
        $packageParts = explode('/', $packageName);

        if (count($packageParts) !== 2) {
            throw new InvalidPackageNameException();
        }

        return $packageParts;
    }
}
