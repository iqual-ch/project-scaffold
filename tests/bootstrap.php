<?php

/**
 * @file
 * Bootstrap file for the project scaffold test suite.
 *
 * Isolates the Composer home and cache directories used during the tests
 * from the environment of the user running them.
 */

require __DIR__ . '/../vendor/autoload.php';

$composer_home = sys_get_temp_dir() . '/project-scaffold-tests/composer-home';
if (!is_dir($composer_home)) {
  mkdir($composer_home, 0777, TRUE);
}
putenv('COMPOSER_HOME=' . $composer_home);
putenv('COMPOSER_CACHE_DIR=' . $composer_home . '/cache');
