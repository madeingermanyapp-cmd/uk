// @vitest-environment jsdom
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { readFileSync } from 'node:fs';
const script = readFileSync(process.cwd() + '/public/manor/gallery.js', 'utf8');

beforeEach(() => {
    document.documentElement.lang = 'en';
    document.body.innerHTML = '<section class="estate-gallery"><a href="/first.jpg" data-gallery-open data-caption="Lake & bridge">First</a><a href="/second.jpg" data-gallery-open data-caption="Fountain">Second</a></section>';
    HTMLDialogElement.prototype.showModal = vi.fn(function () { this.open = true; });
    HTMLDialogElement.prototype.close = vi.fn(function () { this.open = false; this.dispatchEvent(new Event('close')); });
});
describe('estate photograph gallery', () => {
    it('opens the selected original photo and moves between photographs with buttons and keyboard', () => {
        new Function(script)();
        document.querySelector('a').click();
        const dialog = document.querySelector('dialog');
        expect(dialog.open).toBe(true);
        expect(dialog.querySelector('img').alt).toBe('Lake & bridge');
        dialog.querySelectorAll('button')[2].click();
        expect(dialog.querySelector('img').alt).toBe('Fountain');
        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        expect(dialog.querySelector('img').alt).toBe('Lake & bridge');
        dialog.querySelector('button').click();
        expect(dialog.open).toBe(false);
        expect(document.activeElement).toBe(document.querySelector('a'));
    });
    it('preserves normal modified-click navigation and direct-image links without dialog support', () => {
        new Function(script)();
        const event = new MouseEvent('click', { ctrlKey: true, bubbles: true, cancelable: true });
        document.querySelector('a').dispatchEvent(event);
        expect(event.defaultPrevented).toBe(false);
        expect(document.querySelector('dialog').open).toBe(false);
        document.querySelector('dialog').remove();
        HTMLDialogElement.prototype.showModal = undefined;
        new Function(script)();
        expect(document.querySelector('dialog')).toBe(null);
        expect(document.querySelector('a').getAttribute('href')).toBe('/first.jpg');
    });
});
