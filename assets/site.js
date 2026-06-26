import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';

const ready = (fn) => {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
  else fn();
};

ready(() => {
  // components/molecules/gallerySlider/gallerySlider.js
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
  
  // components/molecules/journalSlider/journalSlider.js
  if (window.Swiper) {
    new Swiper('.journal-slider', {
      loop: true,
      speed: 700,
      autoplay: { delay: 2500, disableOnInteraction: false },
      slidesPerView: 1,
      effect: 'fade'
    });
  }
  
  // components/organisms/hero/hero.js
  if (window.Swiper) {
    new Swiper('.hero-slider', {
      effect: 'fade',
      loop: true,
      speed: 900,
      autoplay: { delay: 4200, disableOnInteraction: false },
      pagination: { el: '.hero-pagination', clickable: true }
    });
  }
  
  // components/organisms/siteHeader/siteHeader.js
  const menuToggles = [...document.querySelectorAll('.menu-toggle')];
  const mobileMenu = document.querySelector('.mobile-menu');
  if (menuToggles.length && mobileMenu) {
    const setMenu = (open) => {
      menuToggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
      document.body.classList.toggle('menu-open', open);
    };
    const closeMenu = () => setMenu(false);
    menuToggles.forEach((toggle) => toggle.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open'))));
    mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
  }
});
