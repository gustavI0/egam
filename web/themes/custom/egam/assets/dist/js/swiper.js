/**
 * @file
 * Screenshot swiper
 *
 */
(function (Drupal) {

  Drupal.behaviors.swiper = {
    attach: function (context, settings) {

      const thumbSwiper = new Swiper('.thumb-swiper', {
        spaceBetween: 10,
        slidesPerView: 5,
        freeMode: true,
        watchSlidesVisibility: true,
        watchSlidesProgress: true,
      });

      new Swiper('.main-swiper', {
        effect: 'fade',
        spaceBetween: 10,
        navigation: {
          nextEl: '.swiper-button-next',
          prevEl: '.swiper-button-prev',
        },
        thumbs: {
          swiper: thumbSwiper,
        },
      });
    },
  };
})(Drupal);
