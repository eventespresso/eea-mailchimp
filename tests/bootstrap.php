<?php
/**
 * Bootstrap for eea-mailchimp tests
 */

use EETests\bootstrap\AddonLoader;

$core_tests_dir = dirname(dirname(dirname(__FILE__))) . '/event-espresso-core/tests/';
//if still don't have $core_tests_dir, then let's check tmp folder.
if (! is_dir($core_tests_dir)) {
    $core_tests_dir = '/tmp/event-espresso-core/tests/';
}
require $core_tests_dir . 'includes/CoreLoader.php';
require $core_tests_dir . 'includes/AddonLoader.php';

define('EE4_MC_PLUGIN_DIR', dirname(dirname(__FILE__)) . '/');
define('EE4_MC_TESTS_DIR', EE4_MC_PLUGIN_DIR . 'tests/');


$addon_loader = new AddonLoader(
    EE4_MC_TESTS_DIR,
    EE4_MC_PLUGIN_DIR,
    'ee4-mailchimp.php'
);
$addon_loader->init();
