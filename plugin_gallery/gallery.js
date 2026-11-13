/**
 * PhotoSwipe v5 initialisation for {gallery} markup.
 *
 * The plugin renders <a> elements inside .gallery-wrapper with
 * data-pswp-width/-height (and optionally data-pswp-srcset/-sizes).
 * PhotoSwipeLightbox picks those attributes up automatically.
 */
document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('.gallery-wrapper')) {
        return;
    }

    var lightbox = new PhotoSwipeLightbox({
        gallery: '.gallery-wrapper',
        children: 'a',
        showHideAnimationType: 'zoom',
        pswpModule: PhotoSwipe
    });
    lightbox.init();
});
