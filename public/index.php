<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
require $root . '/src/ReplicateClient.php';
require $root . '/src/LocalEnhancer.php';
require $root . '/src/Enhancer.php';
require $root . '/src/VideoGenerator.php';

$config = new Config($root);
$result = null;
$enhancer = new Enhancer($config);
$job = (string) ($_POST['job'] ?? 'enhance');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($config->appToken !== '') {
        $got = (string) ($_POST['token'] ?? '');
        if (!hash_equals($config->appToken, $got)) {
            $result = ['ok' => false, 'kind' => $job, 'engine' => '', 'file' => null, 'orig' => null, 'url' => null, 'orig_url' => null, 'error' => 'Wrong access token'];
        }
    }
    if ($result === null && $job === 'video') {
        $result = (new VideoGenerator($config))->fromUpload(
            $_FILES['image'] ?? [],
            (string) ($_POST['prompt'] ?? ''),
            (int) ($_POST['duration'] ?? 15)
        );
    } elseif ($result === null) {
        $model = $enhancer->normaliseModel((string) ($_POST['model'] ?? $config->model));
        $scale = (int) ($_POST['scale'] ?? $config->scale);
        $result = $enhancer->processUploaded($_FILES['image'] ?? [], $model, $scale);
        $result['kind'] = 'enhance';
    }
}

$engineReady = $config->hasReplicate() ? 'Replicate AI ready' : (extension_loaded('imagick') ? 'Imagick local (video needs Replicate)' : 'GD local (video needs Replicate)');
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
      <p class="sub"><?= htmlspecialchars($engineReady) ?></p>
    </header>

    <form class="card" method="post" enctype="multipart/form-data" id="form">
      <?php if ($config->appToken !== ''): ?>
        <label>Access token
          <input type="password" name="token" autocomplete="off" required>
        </label>
      <?php endif; ?>

      <div class="tabs">
        <label class="tab"><input type="radio" name="job" value="enhance" <?= $job !== 'video' ? 'checked' : '' ?>> Enhance</label>
        <label class="tab"><input type="radio" name="job" value="video" <?= $job === 'video' ? 'checked' : '' ?>> 15s video</label>
      </div>

      <div id="enhanceFields">
        <div class="row">
          <label>Model
            <select name="model">
              <option value="clarity" <?= $config->model === 'clarity' ? 'selected' : '' ?>>Clarity (skin / photo)</option>
              <option value="realesrgan" <?= $config->model === 'realesrgan' ? 'selected' : '' ?>>Real-ESRGAN</option>
            </select>
          </label>
          <label>Scale
            <select name="scale">
              <option value="2" <?= $config->scale === 2 ? 'selected' : '' ?>>2×</option>
              <option value="4" <?= $config->scale === 4 ? 'selected' : '' ?>>4×</option>
            </select>
          </label>
        </div>
      </div>

      <div id="videoFields" hidden>
        <label>Motion prompt
          <textarea name="prompt" rows="3" placeholder="Slow look-back, wet hair swinging, soft light, handheld camera, ultra realistic..."><?= htmlspecialchars((string) ($_POST['prompt'] ?? '')) ?></textarea>
        </label>
        <label>Length
          <select name="duration">
            <option value="5">5s</option>
            <option value="10">10s</option>
            <option value="15" selected>15s</option>
          </select>
        </label>
      </div>

      <label class="drop" id="drop">
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
        <span id="dropLabel">Tap a reference photo<br><small>JPEG, PNG or WebP · max <?= (int) ($config->maxBytes / 1048576) ?> MB</small></span>
      </label>

      <button type="submit" id="go">Go</button>
    </form>

    <p class="status" id="status" hidden>Working… a 15s clip can take a few minutes.</p>

    <?php if (is_array($result)): ?>
      <section class="card result">
        <?php if ($result['ok']): ?>
          <p class="ok">Done via <?= htmlspecialchars((string) $result['engine']) ?></p>
          <?php if (($result['kind'] ?? '') === 'video'): ?>
            <?php if (!empty($result['orig_url'])): ?>
              <img class="still" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Reference">
            <?php endif; ?>
            <video controls playsinline src="<?= htmlspecialchars((string) $result['url']) ?>"></video>
            <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>&dl=1">Download MP4</a>
          <?php elseif (!empty($result['orig_url']) && !empty($result['url'])): ?>
            <div class="compare" id="compare"
                 style="--pos:50%; --after:url('<?= htmlspecialchars((string) $result['url']) ?>')">
              <img class="before" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Before">
              <input type="range" min="0" max="100" value="50" id="slider" aria-label="Compare">
            </div>
            <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>&dl=1">Download enhanced</a>
          <?php else: ?>
            <img src="<?= htmlspecialchars((string) $result['url']) ?>" alt="Enhanced">
          <?php endif; ?>
        <?php else: ?>
          <p class="err"><?= htmlspecialchars((string) $result['error']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <footer>
      <p>Video uses Kling 3.0 on Replicate, same idea as Imagine: still in, 15s clip out. Needs <code>REPLICATE_API_TOKEN</code>.</p>
    </footer>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
