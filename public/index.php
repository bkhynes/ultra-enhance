<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
require $root . '/src/ReplicateClient.php';
require $root . '/src/LocalEnhancer.php';
require $root . '/src/Enhancer.php';

$config = new Config($root);
$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($config->appToken !== '') {
        $got = (string) ($_POST['token'] ?? '');
        if (!hash_equals($config->appToken, $got)) {
            $result = ['ok' => false, 'engine' => '', 'file' => null, 'url' => null, 'error' => 'Wrong access token'];
        }
    }
    if ($result === null) {
        $result = (new Enhancer($config))->processUploaded($_FILES['image'] ?? []);
    }
}

$engineReady = $config->hasReplicate() ? 'Replicate AI (' . htmlspecialchars($config->model) . ')' : (extension_loaded('imagick') ? 'Imagick local' : 'GD local');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Ultra Enhance</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <header>
      <p class="kicker">Mac + iPhone</p>
      <h1>Ultra Enhance</h1>
      <p class="sub">Upscale and push a photo toward ultra-real. Engine: <strong><?= htmlspecialchars($engineReady) ?></strong> · <?= (int) $config->scale ?>×</p>
    </header>

    <form class="card" method="post" enctype="multipart/form-data" id="form">
      <?php if ($config->appToken !== ''): ?>
        <label>Access token
          <input type="password" name="token" autocomplete="off" required>
        </label>
      <?php endif; ?>

      <label class="drop" id="drop">
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
        <span id="dropLabel">Tap to pick a photo<br><small>JPEG, PNG or WebP · max <?= (int) ($config->maxBytes / 1048576) ?> MB</small></span>
      </label>

      <button type="submit" id="go">Enhance</button>
    </form>

    <p class="status" id="status" hidden>Working… large photos can take a minute or two.</p>

    <?php if (is_array($result)): ?>
      <section class="card result">
        <?php if ($result['ok']): ?>
          <p class="ok">Done via <?= htmlspecialchars((string) $result['engine']) ?></p>
          <img src="<?= htmlspecialchars((string) $result['url']) ?>" alt="Enhanced">
          <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>">Download</a>
        <?php else: ?>
          <p class="err"><?= htmlspecialchars((string) $result['error']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer>
      <p>On iPhone: same Wi-Fi as the Mac, open this page in Safari. Add a Replicate token in <code>.env</code> for the AI look.</p>
    </footer>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
