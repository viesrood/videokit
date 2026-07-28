/**
 * VideoKit facade loader.
 *
 * Swaps a click-to-load poster for the real player iframe. One delegated
 * listener on the document, so posters that are inserted later (Ajax, a live
 * preview, a lightbox) work without re-initialising anything.
 *
 * No dependencies, no build step.
 */
(function () {
    'use strict';

    var LOADED = 'videokitLoaded';

    function load(root) {
        var embedUrl = root.getAttribute('data-embed-url');

        if (!embedUrl || root.dataset[LOADED]) {
            return;
        }

        var iframe = document.createElement('iframe');

        iframe.src = embedUrl;
        iframe.title = root.getAttribute('data-title') || '';
        iframe.className = 'vk-facade__iframe';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.allowFullscreen = true;
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('frameborder', '0');

        root.dataset[LOADED] = '1';
        root.replaceChildren(iframe);

        // The click that started this happened on the button we just removed,
        // so move focus into the player for keyboard users.
        iframe.focus({ preventScroll: true });
    }

    function closest(target) {
        if (!target || typeof target.closest !== 'function') {
            return null;
        }

        return target.closest('[data-videokit-facade]');
    }

    document.addEventListener('click', function (event) {
        var root = closest(event.target);

        if (root) {
            event.preventDefault();
            load(root);
        }
    });

    // Space and Enter already fire a click on a <button>, so only the pointer
    // path needs handling here.
})();
