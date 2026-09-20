const form = document.getElementById('form');
const statusEl = document.getElementById('status');
const go = document.getElementById('go');
const image = document.getElementById('image');
const dropLabel = document.getElementById('dropLabel');
const slider = document.getElementById('slider');
const compare = document.getElementById('compare');
const enhanceFields = document.getElementById('enhanceFields');
const videoFields = document.getElementById('videoFields');
const localFields = document.getElementById('localFields');

function currentJob() {
  const el = document.querySelector('input[name="job"]:checked');
  return el ? el.value : 'enhance';
}

function syncJob() {
  const job = currentJob();
  if (enhanceFields) enhanceFields.hidden = job !== 'enhance';
  if (videoFields) videoFields.hidden = job !== 'video';
  if (localFields) localFields.hidden = job !== 'local';
  document.querySelectorAll('#enhanceFields select, #videoFields select, #videoFields textarea, #localFields textarea').forEach((el) => {
    el.disabled = el.closest('#enhanceFields') ? job !== 'enhance'
      : el.closest('#videoFields') ? job !== 'video'
      : job !== 'local';
  });
  if (go) {
    go.textContent = job === 'video' ? 'Make video' : job === 'local' ? 'Queue locally' : 'Enhance';
  }
}
document.querySelectorAll('input[name="job"]').forEach((el) => el.addEventListener('change', syncJob));
syncJob();

if (image) {
  image.addEventListener('change', () => {
    const f = image.files && image.files[0];
    if (f) dropLabel.innerHTML = f.name + '<br><small>' + Math.round(f.size / 1024) + ' KB</small>';
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
