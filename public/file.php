<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
$config = new Config($root);

$name = basename((string) ($_GET['f'] ?? ''));
if ($name === '' || !preg_match('/^[a-f0-9]+-(enhanced|orig)\.jpg$/', $name)) {
    http_response_code(400);
    exit('Bad file');
}
$path = $config->outputDir . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('Gone');
}

$download = isset($_GET['dl']);
header('Content-Type: image/jpeg');
if ($download) {
    header('Content-Disposition: attachment; filename="' . $name . '"');
}
header('Content-Length: ' . filesize($path));
readfile($path);
