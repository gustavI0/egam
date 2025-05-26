/**
 * @file
 * Lightbox
 *
 */
(function (Drupal, PhotoSwipeLightbox) {
  /**
   * Initialises photoswipe galleries.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.photoswipe = {
    attach: function (context, settings) {
        const lightbox = new PhotoSwipeLightbox({
        gallery: '.cover.lightbox',
        children: 'a',
        initialZoomLevel: .75,
        secondaryZoomLevel: 1,

        pswpModule: PhotoSwipe,
        ...settings?.photoswipe?.options || {}
      });

      // Masquer les flèches si un seul élément
      lightbox.on('afterInit', () => {
        const numItems = lightbox.pswp.getNumItems();
        if (numItems <= 1) {
          const arrowPrev = lightbox.pswp.element.querySelector('.pswp__button--arrow--prev');
          const arrowNext = lightbox.pswp.element.querySelector('.pswp__button--arrow--next');

          if (arrowPrev) arrowPrev.style.display = 'none';
          if (arrowNext) arrowNext.style.display = 'none';
        }
      });

      lightbox.init();
    },
  };
})(Drupal, PhotoSwipeLightbox);