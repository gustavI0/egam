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

      lightbox.init();
    },
  };
})(Drupal, PhotoSwipeLightbox);