# ReleaseBuilder
Utility to create releases in Github

# Installation

``` bash 
composer require ronrademaker/release-builder
```

# Example Usage

Will release 0.2.3 in ```RonRademaker/ReleaseBuilder``` based on current master and will update the ```VERSION``` constant in ```src/Command/ReleaseCommand.php```.

``` bash
vendor/bin/build-release release:build RonRademaker/ReleaseBuilder 0.2.3 0.2-dev --version-constant=src/Command/ReleaseCommand.php::VERSION --branch=master
```

# Generated changelog

The Release Builder will automatically create a changelog in release notes containing the title and number of Pull Requests that were added since the previous version. If there are no Pull Requests, each commit will be included in your changelog.
