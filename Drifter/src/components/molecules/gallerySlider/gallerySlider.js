if (window.Swiper) {
  new Swiper('.drifter-gallery-slider', {
    loop: true,
    speed: 600,
    slidesPerView: 1.08,
    spaceBetween: 18,
    centeredSlides: false,
    navigation: { nextEl: '.gallery-next', prevEl: '.gallery-prev' },
    pagination: { el: '.gallery-pagination', clickable: true },
    breakpoints: {
      720: { slidesPerView: 2, spaceBetween: 22 },
      1100: { slidesPerView: 2.35, spaceBetween: 24 }
    }
  });
}

const lightbox = new PhotoSwipeLightbox({
  gallery: '.pswp-gallery',
  children: 'a',
  pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js')
});
lightbox.init();
