# ReleaseBuilder
Utility to create releases in Github

# Installation

``` bash 
composer require ronrademaker/release-builder
```

# Example Usage

Will release 0.2.3 in ```RonRademaker/ReleaseBuilder``` based on current master and will update the ```VERSION``` constant in ```src/Command/ReleaseCommand.php```.

``` bash
vendor/bin/build-release release:build RonRademaker/ReleaseBuilder 0.2.3 src/Command/ReleaseCommand.php::VERSION 0.2-dev master
```
