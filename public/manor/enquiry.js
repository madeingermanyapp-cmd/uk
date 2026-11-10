'use strict';
const venueSelect = document.querySelector('[data-venue-select]');
const durationSelect = document.querySelector('[data-duration-select]');
function refreshDuration(reset) {
  if (!venueSelect || !durationSelect) return;
  const allowed = (venueSelect.selectedOptions[0]?.dataset.modes || 'hour,day').split(',');
  Array.from(durationSelect.options).forEach(option => { option.disabled = !allowed.includes(option.value); option.hidden = option.disabled; });
  if (reset || !allowed.includes(durationSelect.value)) durationSelect.value = allowed[0];
  document.querySelectorAll('[data-duration-panel]').forEach(panel => {
    const active = panel.dataset.durationPanel === durationSelect.value;
    panel.hidden = !active;
    panel.querySelectorAll('input').forEach(input => { input.required = active; input.disabled = !active; });
  });
}
venueSelect?.addEventListener('change', () => refreshDuration(true));
durationSelect?.addEventListener('change', () => refreshDuration(false));
refreshDuration(false);
