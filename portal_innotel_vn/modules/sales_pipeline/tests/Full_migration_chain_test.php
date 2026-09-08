<?php

defined('BASEPATH') or define('BASEPATH', 'dummy');

function assert_true($cond, $msg) {
    if (!$cond) throw new RuntimeException("Assertion failed: {$msg}");
}

// Mock App_module_migration if not defined
if (!class_exists('App_module_migration')) {
    abstract class App_module_migration {
        abstract public function up();
        abstract public function down();
    }
}

$migrationsDir = __DIR__ . '/../migrations/';
$expectedMigrations = [
    101 => '101_version_101.php',
    102 => '102_version_102.php',
    103 => '103_version_103.php',
    104 => '104_version_104.php',
    105 => '105_version_105.php',
    106 => '106_version_106.php',
    107 => '107_version_107.php',
    108 => '108_version_108.php',
    109 => '109_version_109.php',
    110 => '110_version_110.php',
    111 => '111_version_111.php',
    112 => '112_version_112.php',
    113 => '113_version_113.php',
    114 => '114_version_114.php',
];

foreach ($expectedMigrations as $num => $file) {
    $filePath = $migrationsDir . $file;
    assert_true(file_exists($filePath), "Migration file {$file} must exist on disk");

    $content = file_get_contents($filePath);
    $className = 'Migration_Version_' . $num;

    assert_true(
        strpos($content, "class {$className}") !== false,
        "Migration file {$file} must declare class {$className}"
    );

    assert_true(
        strpos($content, "function up()") !== false,
        "Migration class {$className} must implement up()"
    );
}

// Migration 114 must implement non-destructive down()
$m114Content = file_get_contents($migrationsDir . '114_version_114.php');
assert_true(
    strpos($m114Content, "function down()") !== false,
    "Migration_Version_114 must implement down()"
);

// Check that sales_pipeline.php has Version 1.1.4 (corresponding to migration 114)
$mainFile = __DIR__ . '/../sales_pipeline.php';
$mainContent = file_get_contents($mainFile);
assert_true(
    strpos($mainContent, 'Version: 1.1.4') !== false,
    'sales_pipeline.php must declare Version: 1.1.4 (matches migration 114)'
);

echo "PASS: Full_migration_chain_test (All migrations 101 to 114 verified)\n";
