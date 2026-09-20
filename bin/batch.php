<?php

declare(strict_types=1);

/**
 * Overnight / folder batch.
 *   php bin/batch.php /path/to/inbox [/path/to/out]
 * Watches inbox if --watch is passed.
 */
$root = dirname(__DIR__);
require $root . '/src/Config.php';
require $root . '/src/ReplicateClient.php';
require $root . '/src/LocalEnhancer.php';
require $root . '/src/Enhancer.php';

$args = array_values(array_filter($argv, fn ($a) => $a !== '--watch'));
$watch = in_array('--watch', $argv, true);
$inDir = $args[1] ?? ($root . '/inbox');
$outDir = $args[2] ?? ($root . '/output');

if (!is_dir($inDir) && !mkdir($inDir, 0775, true) && !is_dir($inDir)) {
    fwrite(STDERR, "Cannot create inbox: $inDir\n");
    exit(1);
}

$config = new Config($root);
$enhancer = new Enhancer($config);

$run = function () use ($inDir, $outDir, $enhancer): void {
    $files = glob($inDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
    if (!$files) {
        echo '[' . date('H:i:s') . "] inbox empty\n";
        return;
    }
    foreach ($files as $src) {
        $doneFlag = $src . '.done';
        if (is_file($doneFlag)) {
            continue;
        }
        echo '[' . date('H:i:s') . '] enhancing ' . basename($src) . " ... ";
        try {
            $out = $enhancer->processPath($src, $outDir);
            touch($doneFlag);
            echo 'ok -> ' . basename($out) . PHP_EOL;
        } catch (Throwable $e) {
            echo 'FAIL ' . $e->getMessage() . PHP_EOL;
        }
    }
};

echo "Inbox:  $inDir\nOutput: $outDir\nEngine: " . ($config->hasReplicate() ? 'replicate:' . $config->model : 'local') . PHP_EOL;
$run();

if ($watch) {
    echo "Watching every 20s. Ctrl+C to stop.\n";
    while (true) {
        sleep(20);
        $run();
    }
}
