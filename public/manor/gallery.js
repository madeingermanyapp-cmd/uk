/* Progressive enhancement: each gallery photograph remains a direct image link. */
document.querySelectorAll('.estate-gallery').forEach(gallery => {
    if (typeof HTMLDialogElement === 'undefined' || typeof HTMLDialogElement.prototype.showModal !== 'function') return;
    const links = [...gallery.querySelectorAll('[data-gallery-open]')];
    const dialog = document.createElement('dialog');
    dialog.className = 'photo-lightbox';
    dialog.setAttribute('aria-label', document.documentElement.lang === 'ar' ? 'معاينة الصورة' : 'Photograph preview');
    const close = document.createElement('button');
    close.type = 'button';
    close.textContent = document.documentElement.lang === 'ar' ? 'إغلاق' : 'Close';
    const image = document.createElement('img');
    const caption = document.createElement('p');
    const navigation = document.createElement('div');
    navigation.className = 'actions';
    const previous = document.createElement('button');
    previous.type = 'button';
    previous.textContent = document.documentElement.lang === 'ar' ? 'السابقة' : 'Previous';
    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = document.documentElement.lang === 'ar' ? 'التالية' : 'Next';
    navigation.append(previous, next);
    dialog.append(close, image, caption, navigation);
    document.body.append(dialog);
    let active = 0;
    const show = index => {
        active = (index + links.length) % links.length;
        image.src = links[active].href;
        image.alt = links[active].dataset.caption || '';
        caption.textContent = image.alt;
    };
    links.forEach((link, index) => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        show(index);
        dialog.showModal();
    }));
    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => show(active - 1));
    next.addEventListener('click', () => show(active + 1));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', event => {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
            event.preventDefault();
            show(active + (event.key === 'ArrowRight' ? 1 : -1));
        }
    });
    dialog.addEventListener('close', () => links[active].focus());
});
