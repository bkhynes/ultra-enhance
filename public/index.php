<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/Config.php';
require $root . '/src/ReplicateClient.php';
require $root . '/src/LocalEnhancer.php';
require $root . '/src/Enhancer.php';
require $root . '/src/VideoGenerator.php';
require $root . '/src/LocalBridge.php';

$config = new Config($root);
$local = new LocalBridge($config);
$result = null;
$enhancer = new Enhancer($config);
$job = (string) ($_POST['job'] ?? 'enhance');
$videoEngine = ReplicateClient::videoEngine((string) ($_POST['video_engine'] ?? 'wan22'));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($config->appToken !== '') {
        $got = (string) ($_POST['token'] ?? '');
        if (!hash_equals($config->appToken, $got)) {
            $result = ['ok' => false, 'kind' => $job, 'engine' => '', 'file' => null, 'orig' => null, 'url' => null, 'orig_url' => null, 'error' => 'Wrong access token'];
        }
    }
    if ($result === null && $job === 'local') {
        $result = $local->queueUpload($_FILES['image'] ?? [], (string) ($_POST['prompt'] ?? ''));
    } elseif ($result === null && $job === 'video') {
        $result = (new VideoGenerator($config))->fromUpload(
            $_FILES['image'] ?? [],
            (string) ($_POST['prompt'] ?? ''),
            (int) ($_POST['duration'] ?? 15),
            $videoEngine
        );
    } elseif ($result === null) {
        $model = $enhancer->normaliseModel((string) ($_POST['model'] ?? $config->model));
        $scale = (int) ($_POST['scale'] ?? $config->scale);
        $result = $enhancer->processUploaded($_FILES['image'] ?? [], $model, $scale);
        $result['kind'] = 'enhance';
    }
}

$localOut = $local->recentOut();
$engineReady = $config->hasReplicate() ? 'Replicate AI ready' : (extension_loaded('imagick') ? 'Imagick local (cloud video needs Replicate)' : 'GD local');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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

      <div class="tabs tabs3">
        <label class="tab"><input type="radio" name="job" value="enhance" <?= !in_array($job, ['video','local'], true) ? 'checked' : '' ?>> Enhance</label>
        <label class="tab"><input type="radio" name="job" value="video" <?= $job === 'video' ? 'checked' : '' ?>> Cloud video</label>
        <label class="tab"><input type="radio" name="job" value="local" <?= $job === 'local' ? 'checked' : '' ?>> Local</label>
      </div>

      <div id="enhanceFields">
        <div class="row">
          <label>Model
            <select name="model">
              <option value="clarity" <?= $config->model === 'clarity' ? 'selected' : '' ?>>Clarity</option>
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
        <label>Engine
          <select name="video_engine">
            <?php foreach (ReplicateClient::VIDEO_ENGINES as $id => $meta): ?>
              <option value="<?= htmlspecialchars($id) ?>" <?= $videoEngine === $id ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Motion prompt
          <textarea name="prompt" rows="3"><?= htmlspecialchars((string) ($_POST['prompt'] ?? '')) ?></textarea>
        </label>
        <label>Length
          <select name="duration">
            <option value="5">5s</option>
            <option value="10">10s</option>
            <option value="15" selected>15s</option>
          </select>
        </label>
      </div>

      <div id="localFields" hidden>
        <p class="hint">Nothing leaves this Mac. Still + prompt land in <code>local/inbox/</code>. Run Wan 2.2 in ComfyUI or Draw Things, save the MP4 as the same job id in <code>local/out/</code>, refresh. Graph: <code>workflows/WAN22_I2V.md</code>.</p>
        <label>Motion prompt
          <textarea name="prompt" rows="3" placeholder="Breath, weight shift, hair, handheld..."><?= htmlspecialchars((string) ($_POST['prompt'] ?? '')) ?></textarea>
        </label>
      </div>

      <label class="drop" id="drop">
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp" required>
        <span id="dropLabel">Tap a reference photo<br><small>stays on this machine for Local</small></span>
      </label>
      <button type="submit" id="go">Go</button>
    </form>

    <p class="status" id="status" hidden>Working…</p>

    <?php if (is_array($result)): ?>
      <section class="card result">
        <?php if ($result['ok']): ?>
          <p class="ok">Done via <?= htmlspecialchars((string) $result['engine']) ?></p>
          <?php if (($result['kind'] ?? '') === 'local'): ?>
            <p class="hint"><?= htmlspecialchars((string) ($result['hint'] ?? 'Queued.')) ?></p>
            <?php if (!empty($result['job_id'])): ?><p class="hint">Job id: <code><?= htmlspecialchars((string) $result['job_id']) ?></code></p><?php endif; ?>
            <?php if (!empty($result['orig_url'])): ?><img class="still" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Queued still"><?php endif; ?>
          <?php elseif (($result['kind'] ?? '') === 'video'): ?>
            <?php if (!empty($result['orig_url'])): ?><img class="still" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Reference"><?php endif; ?>
            <video controls playsinline src="<?= htmlspecialchars((string) $result['url']) ?>"></video>
            <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>&dl=1">Download MP4</a>
          <?php elseif (!empty($result['orig_url']) && !empty($result['url'])): ?>
            <div class="compare" id="compare" style="--pos:50%; --after:url('<?= htmlspecialchars((string) $result['url']) ?>')">
              <img class="before" src="<?= htmlspecialchars((string) $result['orig_url']) ?>" alt="Before">
              <input type="range" min="0" max="100" value="50" id="slider">
            </div>
            <a class="btn" href="<?= htmlspecialchars((string) $result['url']) ?>&dl=1">Download enhanced</a>
          <?php endif; ?>
        <?php else: ?>
          <p class="err"><?= htmlspecialchars((string) $result['error']) ?></p>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($localOut): ?>
      <section class="card">
        <p class="ok">local/out</p>
        <?php foreach ($localOut as $row): ?>
          <p class="hint"><?= htmlspecialchars($row['id']) ?></p>
          <?php if (!empty($row['url'])): ?>
            <video controls playsinline src="<?= htmlspecialchars($row['url']) ?>"></video>
            <a class="btn" href="<?= htmlspecialchars($row['url']) ?>&dl=1">Download</a>
          <?php endif; ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <footer>
      <p>Local = your stills never hit Replicate. Cloud Wan still can. RunPod + the official 14B JSON is the quality path.</p>
    </footer>
  </main>
  <script src="/assets/app.js"></script>
</body>
</html>
