if (window.Swiper) {
  new Swiper('.journal-slider', {
    loop: true,
    speed: 700,
    autoplay: { delay: 2500, disableOnInteraction: false },
    slidesPerView: 1,
    effect: 'fade'
  });
}
