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
        gallery: '.main-swiper',
        children: 'a',
        pswpModule: PhotoSwipe,
        ...settings?.photoswipe?.options || {}
      });

      lightbox.init();
    },
  };
})(Drupal, PhotoSwipeLightbox);