/**
 * @file
 * Screenshot swiper
 *
 */
(function (Drupal, once) {

  Drupal.behaviors.swiper = {
    attach: function (context) {
      once('swiper-init', '.main-swiper', context).forEach((mainSwiperEl) => {
        const thumbSwiperEl = context.querySelector('.thumb-swiper') || document.querySelector('.thumb-swiper');

        let thumbSwiper = null;

        // Vérifier si le thumb swiper doit être initialisé
        if (thumbSwiperEl && !thumbSwiperEl.classList.contains('hidden')) {
          const thumbSlides = thumbSwiperEl.querySelectorAll('.swiper-slide');

          // Initialiser seulement s'il y a plus d'une slide
          if (thumbSlides.length > 1) {
            thumbSwiper = new Swiper(thumbSwiperEl, {
              spaceBetween: 10,
              slidesPerView: Math.min(5, thumbSlides.length),
              freeMode: true,
              watchSlidesVisibility: true,
              watchSlidesProgress: true,
            });
          }
        }

        // Configuration du main swiper
        const mainSwiperConfig = {
          effect: 'fade',
          spaceBetween: 10,
          navigation: {
            nextEl: mainSwiperEl.querySelector('.swiper-button-next'),
            prevEl: mainSwiperEl.querySelector('.swiper-button-prev'),
          },
        };

        // Ajouter la liaison avec thumbs seulement si thumbSwiper existe
        if (thumbSwiper) {
          mainSwiperConfig.thumbs = {
            swiper: thumbSwiper,
          };
        }

        new Swiper(mainSwiperEl, mainSwiperConfig);
      });
    },
  };
})(Drupal, once);