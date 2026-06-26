if (window.Swiper) {
  new Swiper('.hero-slider', {
    effect: 'fade',
    loop: true,
    speed: 900,
    autoplay: { delay: 4200, disableOnInteraction: false },
    pagination: { el: '.hero-pagination', clickable: true }
  });
}
