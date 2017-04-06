#!/bin/bash -xeu
PHP_MAJOR_VERSION=$(php -r "echo PHP_MAJOR_VERSION;");

git clone --depth 1 https://github.com/runkit7/runkit7.git
pushd runkit7
phpize && ./configure && make && make install || exit 1
echo 'extension = runkit.so' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
popd
# Configuration settings needed for running tests.
echo 'runkit.internal_override = On' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
# Optional, set error reporting
echo 'error_reporting = E_ALL' >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
