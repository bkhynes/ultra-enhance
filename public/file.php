<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
$config = new Config($root);

$name = basename((string) ($_GET['f'] ?? ''));
if ($name === '' || !preg_match('/^[a-f0-9]+-(enhanced|orig)\.jpg$|^[a-f0-9]+-video\.mp4$/', $name)) {
    http_response_code(400);
    exit('Bad file');
}
$path = $config->outputDir . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('Gone');
}

$download = isset($_GET['dl']);
$isVideo = str_ends_with($name, '.mp4');
header('Content-Type: ' . ($isVideo ? 'video/mp4' : 'image/jpeg'));
if ($download) {
    header('Content-Disposition: attachment; filename="' . $name . '"');
}
header('Content-Length: ' . filesize($path));
header('Accept-Ranges: bytes');
readfile($path);
