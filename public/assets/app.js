const form = document.getElementById('form');
const statusEl = document.getElementById('status');
const go = document.getElementById('go');
const image = document.getElementById('image');
const dropLabel = document.getElementById('dropLabel');
const slider = document.getElementById('slider');
const compare = document.getElementById('compare');

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
      go.textContent = 'Enhancing…';
    }
  });
}

if (slider && compare) {
  const apply = () => compare.style.setProperty('--pos', slider.value + '%');
  slider.addEventListener('input', apply);
  apply();
}
