const form = document.getElementById('form');
const statusEl = document.getElementById('status');
const go = document.getElementById('go');
const image = document.getElementById('image');
const dropLabel = document.getElementById('dropLabel');
const slider = document.getElementById('slider');
const compare = document.getElementById('compare');
const enhanceFields = document.getElementById('enhanceFields');
const videoFields = document.getElementById('videoFields');

function syncJob() {
  const video = document.querySelector('input[name="job"][value="video"]');
  const isVideo = !!(video && video.checked);
  if (enhanceFields) enhanceFields.hidden = isVideo;
  if (videoFields) videoFields.hidden = !isVideo;
  if (go) go.textContent = isVideo ? 'Make 15s video' : 'Enhance';
}
document.querySelectorAll('input[name="job"]').forEach((el) => el.addEventListener('change', syncJob));
syncJob();

if (image) {
  image.addEventListener('change', () => {
    const f = image.files && image.files[0];
    if (f) {
      dropLabel.innerHTML = f.name + '<br><small>' + Math.round(f.size / 1024) + ' KB</small>';
    }
  });
}

if (form) {
  form.addEventListener('submit', () => {
    if (statusEl) statusEl.hidden = false;
    if (go) {
      go.disabled = true;
      go.textContent = 'Working…';
    }
  });
}

if (slider && compare) {
  const apply = () => compare.style.setProperty('--pos', slider.value + '%');
  slider.addEventListener('input', apply);
  apply();
}
