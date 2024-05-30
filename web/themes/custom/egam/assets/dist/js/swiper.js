Drupal.behaviors.swiper = {
  attach: function(context, settings) {

    const thumbSwiper = new Swiper('.thumb-swiper', {
      spaceBetween: 10,
      slidesPerView: 5,
      freeMode: true,
      watchSlidesVisibility: true,
      watchSlidesProgress: true,
    });

    const mainSwiper = new Swiper('.main-swiper', {
      effect: "fade",
      spaceBetween: 10,
      navigation: {
        hideOnClick: true,
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      thumbs: {
        swiper: thumbSwiper,
      },
    });
  }
};
