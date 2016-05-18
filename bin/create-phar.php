<?php
/*
 * Create a phar for system-wide installation
 */

use Symfony\Component\Finder\Finder;

require(__DIR__ . '/../vendor/autoload.php');

$tmpDirectory = __DIR__.'/../tmp/';
$buildDirectory = __DIR__.'/../build/';
$binDirectory = $tmpDirectory.'/ReleaseBuilder/bin';
$sourceDirectory = $tmpDirectory.'/ReleaseBuilder/src';
$vendorDirectory = $tmpDirectory.'/ReleaseBuilder/vendor';

$repository = 'https://github.com/RonRademaker/ReleaseBuilder.git';
$target = 'github-build-release.phar';

if (is_file($buildDirectory.$target) ) {
    unlink($buildDirectory.$target);
}
if (is_dir($tmpDirectory)) {
    exec(sprintf('rm -rf %s', $tmpDirectory));
}
mkdir($tmpDirectory);

chdir($tmpDirectory);
echo sprintf('Cloning fresh copy of master%s', "\n");
exec(sprintf('git clone %s', $repository));
chdir('ReleaseBuilder');

echo sprintf('Installing dependencies%s', "\n");
exec('composer install --no-dev -o --prefer-dist');

echo sprintf('Start compiling into PHAR%s', "\n");

$phar = new Phar($buildDirectory.$target, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO, $target);
$phar->setSignatureAlgorithm(Phar::SHA1);
$phar->startBuffering();
$finder = new Finder();
$finder->files()
    ->ignoreVCS(true)
    ->name('*.php')
    ->name('*.pem')
    ->notPath('tests')
    ->in($sourceDirectory)
    ->in($vendorDirectory);

foreach ($finder as $file) {
    echo '.';
    $path = str_replace(
        [realpath($sourceDirectory.'/..').DIRECTORY_SEPARATOR, realpath($vendorDirectory.'/..').DIRECTORY_SEPARATOR],
        '',
        $file->getRealpath()
    );
    $phar->addFromString($path, file_get_contents($file));
}

$phar->addFromString('bin/build-release', file_get_contents(__DIR__.'/build-release'));

$phar->buildFromDirectory($sourceDirectory);
$phar->setStub("#!/usr/bin/env php
<?php
Phar::mapPhar('github-build-release.phar');
require 'phar://github-build-release.phar/bin/build-release';
__HALT_COMPILER();");

$phar->stopBuffering();

echo sprintf('Done%s', "\n");
chmod($buildDirectory.$target, 0555);
echo sprintf('Phar is executable%s', "\n");