const form = document.querySelector(".contact__form");
const note = document.querySelector(".contact__note");
const header = document.querySelector(".header");
const menuToggle = document.querySelector(".header__toggle");
const nav = document.querySelector(".header__nav");

if (header && menuToggle && nav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = header.classList.toggle("header--open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    menuToggle.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
  });
}

document.querySelectorAll('a[href^="#"]').forEach((link) => {
  link.addEventListener("click", (event) => {
    const target = document.querySelector(link.getAttribute("href"));

    if (!target) return;

    event.preventDefault();
    target.scrollIntoView({ behavior: "smooth", block: "start" });

    if (link.getAttribute("href") === "#contact" && form) {
      const firstField = form.querySelector("input, select, textarea");
      window.setTimeout(() => firstField?.focus({ preventScroll: true }), 450);
    }

    if (header && menuToggle) {
      header.classList.remove("header--open");
      menuToggle.setAttribute("aria-expanded", "false");
      menuToggle.setAttribute("aria-label", "Open menu");
    }
  });
});

if (form && note) {
  form.addEventListener("submit", () => {
    note.textContent = "Opening your email client with the enquiry details.";
  });
}

const revealItems = document.querySelectorAll("[data-reveal], .services__card, .process__list li");

if ("IntersectionObserver" in window) {
  const revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          revealObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.18, rootMargin: "0px 0px -8%" },
  );

  revealItems.forEach((item, index) => {
    item.style.setProperty("--reveal-delay", Math.min(index % 6, 5) * 70 + "ms");
    revealObserver.observe(item);
  });
} else {
  revealItems.forEach((item) => item.classList.add("is-visible"));
}

const countItems = document.querySelectorAll("[data-count-to]");

function animateCount(item) {
  const target = Number(item.dataset.countTo || 0);
  const duration = 1100;
  const start = performance.now();

  function frame(now) {
    const progress = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    item.textContent = Math.round(target * eased).toLocaleString("en-US");

    if (progress < 1) requestAnimationFrame(frame);
  }

  requestAnimationFrame(frame);
}

if (countItems.length && "IntersectionObserver" in window) {
  const countObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          countObserver.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.45 },
  );

  countItems.forEach((item) => countObserver.observe(item));
} else {
  countItems.forEach((item) => animateCount(item));
}

document.querySelectorAll("[data-slider]").forEach((slider) => {
  const slides = [...slider.querySelectorAll(".testimonial-slider__slide")];
  const prev = slider.querySelector("[data-slider-prev]");
  const next = slider.querySelector("[data-slider-next]");
  const dots = slider.querySelector(".testimonial-slider__dots");
  let index = 0;

  dots.innerHTML = slides.map((_, dotIndex) => '<span class="' + (dotIndex === 0 ? "is-active" : "") + '"></span>').join("");
  const dotItems = [...dots.children];

  function showSlide(nextIndex) {
    slides[index].classList.remove("is-active");
    dotItems[index]?.classList.remove("is-active");
    index = (nextIndex + slides.length) % slides.length;
    slides[index].classList.add("is-active");
    dotItems[index]?.classList.add("is-active");
  }

  prev?.addEventListener("click", () => showSlide(index - 1));
  next?.addEventListener("click", () => showSlide(index + 1));
  window.setInterval(() => showSlide(index + 1), 6500);
});

document.querySelectorAll("[data-accordion]").forEach((accordion) => {
  accordion.querySelectorAll(".faq__question").forEach((button) => {
    button.addEventListener("click", () => {
      const item = button.closest(".faq__item");
      const isOpen = item.classList.contains("is-open");

      accordion.querySelectorAll(".faq__item").forEach((panel) => {
        panel.classList.remove("is-open");
        panel.querySelector(".faq__question")?.setAttribute("aria-expanded", "false");
      });

      if (!isOpen) {
        item.classList.add("is-open");
        button.setAttribute("aria-expanded", "true");
      }
    });
  });
});

const gallery = document.querySelector("#campaign-gallery");

let galleryIsotope;

function initIsotopeGallery() {
  if (!gallery || !window.Isotope) return;

  if (galleryIsotope) {
    galleryIsotope.layout();
    return;
  }

  galleryIsotope = new Isotope(gallery, {
    itemSelector: ".gallery__item",
    layoutMode: "masonry",
    percentPosition: true,
    transitionDuration: "0.45s",
    masonry: {
      columnWidth: ".gallery__sizer",
      gutter: ".gallery__gutter",
    },
  });

  gallery.querySelectorAll("img").forEach((image) => {
    if (image.complete) return;
    image.addEventListener("load", () => galleryIsotope?.layout(), { once: true });
  });
}

initIsotopeGallery();
window.addEventListener("load", initIsotopeGallery);

if (gallery) {
  import("https://unpkg.com/photoswipe@5.4.4/dist/photoswipe-lightbox.esm.js")
    .then(({ default: PhotoSwipeLightbox }) => {
      const lightbox = new PhotoSwipeLightbox({
        gallery: "#campaign-gallery",
        children: "a",
        showHideAnimationType: "zoom",
        pswpModule: () => import("https://unpkg.com/photoswipe@5.4.4/dist/photoswipe.esm.js"),
      });
      lightbox.init();
    })
    .catch(() => {
      gallery.dataset.lightbox = "fallback";
    });
}

document.querySelectorAll("[data-vertical-slider]").forEach((slider) => {
  const cards = [...slider.querySelectorAll(".vertical-video__card")];
  let active = Math.max(0, cards.findIndex((card) => card.classList.contains("is-active")));
  let slideTimer;

  function resetVideo(card) {
    const video = card?.querySelector("video");

    if (!video) return;

    video.pause();
    video.currentTime = 0;
  }

  function queueNext() {
    const card = cards[active];
    const video = card?.querySelector("video");

    window.clearTimeout(slideTimer);

    if (!video) {
      slideTimer = window.setTimeout(() => setActive(active + 1), 4000);
      return;
    }

    video.loop = false;
    video.currentTime = 0;
    video.play().catch(() => {
      slideTimer = window.setTimeout(() => setActive(active + 1), 4000);
    });
  }

  function setActive(nextIndex) {
    const previous = active;

    cards.forEach((card) => card.classList.remove("was-active"));
    cards[previous]?.classList.remove("is-active");
    cards[previous]?.classList.add("was-active");
    resetVideo(cards[previous]);

    active = (nextIndex + cards.length) % cards.length;
    cards[active]?.classList.add("is-active");
    queueNext();
  }

  cards.forEach((card, index) => {
    const video = card.querySelector("video");

    video?.addEventListener("ended", () => {
      if (index === active) setActive(active + 1);
    });
  });

  queueNext();
});


const heroMedia = document.querySelector(".hero__media");

if (heroMedia) {
  const updateHeroParallax = () => {
    const offset = Math.min(window.scrollY * 0.18, 120);
    heroMedia.style.setProperty("--hero-parallax", offset + "px");
  };

  updateHeroParallax();
  window.addEventListener("scroll", updateHeroParallax, { passive: true });
}
