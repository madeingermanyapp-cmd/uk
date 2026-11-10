'use strict';
document.addEventListener('click', (event) => {
  const add = event.target.closest('[data-add-quote-row]');
  if (!add) return;
  const form = add.closest('form');
  const rows = form.querySelectorAll('[data-quote-row]');
  if (rows.length >= 100) return;
  const copy = rows[rows.length - 1].cloneNode(true);
  copy.querySelectorAll('input,textarea').forEach((input) => {
    input.name = input.name.replace(/\[\d+\]/, '[' + rows.length + ']');
    if (input.type === 'checkbox') input.checked = input.name.startsWith('item_visible');
    else if (input.name.startsWith('item_quantity')) input.value = '1';
    else if (input.name.startsWith('item_amount')) input.value = '0.00';
    else if (input.name.startsWith('item_tax')) input.value = '0';
    else input.value = '';
  });
  copy.querySelector('legend').textContent = add.dataset.lineLabel + ' ' + (rows.length + 1);
  rows[rows.length - 1].after(copy);
});
