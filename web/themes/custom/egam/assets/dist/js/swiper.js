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
      // loop: true,
      spaceBetween: 10,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      pagination: {
        el: '.swiper-pagination',
      },
      thumbs: {
        swiper: thumbSwiper,
      },
    });
  }
};