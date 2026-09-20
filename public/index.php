<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
require $root . '/src/ReplicateClient.php';
require $root . '/src/LocalEnhancer.php';
require $root . '/src/Enhancer.php';

$config = new Config($root);
$result = null;
$enhancer = new Enhancer($config);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($config->appToken !== '') {
        $got = (string) ($_POST['token'] ?? '');
        if (!hash_equals($config->appToken, $got)) {
            $result = ['ok' => false, 'engine' => '', 'file' => null, 'orig' => null, 'url' => null, 'orig_url' => null, 'error' => 'Wrong access token'];
        }
    }
    if ($result === null) {
        $model = $enhancer->normaliseModel((string) ($_POST['model'] ?? $config->model));
        $scale = (int) ($_POST['scale'] ?? $config->scale);
        $result = $enhancer->processUploaded($_FILES['image'] ?? [], $model, $scale);
    }
}

$engineReady = $config->hasReplicate() ? 'Replicate AI ready' : (extension_loaded('imagick') ? 'Imagick local' : 'GD local');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Ultra Enhance</title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
  <main class="wrap">
    <header>
      <p class="kicker">Mac + iPhone</p>
      <h1>Ultra Enhance</h1>
      <p class="sub"><?= htmlspecialchars($engineReady) ?>. Drag the slider after a run to compare.</p>
    </header>

    <form class="card" method="post" enctype="multipart/form-data" id="form">
      <?php if ($config->appToken !== ''): ?>
        <label>Access token
          <input type="password" name="token" autocomplete="off" required>
        </label>
      <?php endif; ?>

      <div class="row">
        <label>Model
          <select name="model">
            <option value="clarity" <?= $config->model === 'clarity' ? 'selected' : '' ?>>Clarity (skin / photo)</option>
            <option value="realesrgan" <?= $config->model === 'realesrgan' ? 'selected' : '' ?>>Real-ESRGAN (crisp upscale)</option>
          </select>
        </label>
        <label>Scale
          <select name="scale">
            <option value="2" <?= $config->scale === 2 ? 'selected' : '' ?>>2×</option>
            <option value="4" <?= $config->scale === 4 ? 'selected' : '' ?>>4×</option>
          </select>
        </label>
      </div>

      <label class="drop" id="drop">
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
        <span id="dropLabel">Tap to pick a photo<br><small>JPEG, PNG or WebP · max <?= (int) ($config->maxBytes / 1048576) ?> MB</small></span>
      </label>

      <button type="submit" id="go">Enhance</button>
    </form>

    <p class="status" id="status" hidden>Working… AI jobs can take a minute or two.</p>

    <?php if (is_array($result)): ?>
      <section class="card result">
        <?php if ($result['ok']): ?>
          <p class="ok">Done via <?= htmlspecialchars((string) $result['engine']) ?></p>
          <?php if (!empty($result['orig_url']) && !empty($result['url'])): ?>
            <div class="compare" id="compare"
                 style="--pos:50%; --after:url('<?= htmlspecialchars((string) $result['url']) ?>')">
              <img class="before" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Before">
              <input type="range" min="0" max="100" value="50" id="slider" aria-label="Compare">
            </div>
            <p class="hint">Slide: left is original, right is enhanced.</p>
          <?php else: ?>
            <img src="<?= htmlspecialchars((string) $result['url']) ?>" alt="Enhanced">
          <?php endif; ?>
          <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>&dl=1">Download enhanced</a>
        <?php else: ?>
          <p class="err"><?= htmlspecialchars((string) $result['error']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer>
      <p>Batch overnight on the Mac: drop files in <code>inbox/</code> then <code>php bin/batch.php --watch</code>.</p>
    </footer>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
