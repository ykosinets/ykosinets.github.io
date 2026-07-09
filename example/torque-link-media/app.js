function initSmoothScroll() {
  const form = document.querySelector(".contact__form");
  const header = document.querySelector(".header");
  const menuToggle = document.querySelector(".header__toggle");

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
}

function initScrollspy() {
    const header = document.querySelector(".header");
    const links = [...document.querySelectorAll("[data-scrollspy-link]")];
    const sections = links
        .map((link) => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);

    if (!links.length || !sections.length) return;

    const setActiveLink = (id) => {
        links.forEach((link) => {
            const isActive = link.getAttribute("href") === "#" + id;
            link.classList.toggle("is-active", isActive);

            if (isActive) {
                link.setAttribute("aria-current", "page");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    };

    const updateScrollspy = () => {
        const headerHeight = header?.offsetHeight || 0;
        const scrollPoint = window.scrollY + headerHeight + 96;
        let activeId = sections[0].id;

        sections.forEach((section) => {
            if (section.offsetTop <= scrollPoint) {
                activeId = section.id;
            }
        });

        setActiveLink(activeId);
    };

    links.forEach((link) => {
        link.addEventListener("click", () => {
            setActiveLink(link.getAttribute("href").slice(1));
        });
    });

    updateScrollspy();
    window.addEventListener("scroll", updateScrollspy, { passive: true });
    window.addEventListener("resize", updateScrollspy);
}

function initRevealAnimations() {
  const revealItems = [...document.querySelectorAll("[data-reveal], .services__card, .process__list li")];
  const groupSelectors = [
    ".services__grid",
    ".proof__table",
    ".process__list",
    ".faq__list",
    ".gallery__grid",
    ".testimonials__grid",
    ".vertical-video__grid",
    ".section",
  ].join(", ");
  const groupCounts = new WeakMap();

  function setRevealDelay(item) {
    const group = item.closest(groupSelectors) || item.parentElement || document.body;
    const index = groupCounts.get(group) || 0;

    item.style.setProperty("--reveal-delay", Math.min(index, 5) * 70 + "ms");
    groupCounts.set(group, index + 1);
  }

  revealItems.forEach(setRevealDelay);

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

    revealItems.forEach((item) => revealObserver.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add("is-visible"));
  }
}

function initCountAnimations() {
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
}

function initHeader() {
    const header = document.querySelector(".header");
    const menuToggle = document.querySelector(".header__toggle");
    const nav = document.querySelector(".header__nav");

    if (!header || !menuToggle || !nav) return;

    menuToggle.addEventListener("click", () => {
        const isOpen = header.classList.toggle("header--open");
        menuToggle.setAttribute("aria-expanded", String(isOpen));
        menuToggle.setAttribute("aria-label", isOpen ? "Close menu" : "Open menu");
    });
}

function initHeroParallax() {
    const heroMedia = document.querySelector(".hero__media");

    if (!heroMedia) return;

    const updateHeroParallax = () => {
        const offset = Math.min(window.scrollY * 0.18, 120);
        heroMedia.style.setProperty("--hero-parallax", offset + "px");
    };

    updateHeroParallax();
    window.addEventListener("scroll", updateHeroParallax, {passive: true});
}

function initProofSection() {
}

function initChannelsTicker() {
}

function initMediaBand() {
}

function initServicesSection() {
}

function initTestimonialSliders() {
    document.querySelectorAll("[data-slider]").forEach((slider) => {
        const slides = [...slider.querySelectorAll(".testimonial-slider__slide")];
        const prev = slider.querySelector("[data-slider-prev]");
        const next = slider.querySelector("[data-slider-next]");
        const dots = slider.querySelector(".testimonial-slider__dots");
        let index = 0;

        if (!slides.length || !dots) return;

        dots.innerHTML = slides
            .map((_, dotIndex) => '<span class="' + (dotIndex === 0 ? "is-active" : "") + '"></span>')
            .join("");
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
}

function initWhySection() {
}

function initProcessSection() {
}

// ./components/faq/faq.js
// ./components/gallery/gallery.js
// ./components/videos/videos.js
// ./components/vertical-video/vertical-video.js
function initCtaSection() {
}

function initContactForm() {
    const form = document.querySelector(".contact__form");
    const note = document.querySelector(".contact__note");

    if (!form || !note) return;

    form.addEventListener("submit", () => {
        note.textContent = "Opening your email client with the enquiry details.";
    });
}

// ./components/socials/socials.js
// ./components/media-credits/media-credits.js
function initFooter() {
}


document.addEventListener("DOMContentLoaded", () => {
  initHeader();
  initSmoothScroll();
  initScrollspy();
  initRevealAnimations();
  initCountAnimations();
  initHeroParallax();
  initProofSection();
  initChannelsTicker();
  initMediaBand();
  initServicesSection();
  initTestimonialSliders();
  initWhySection();
  initProcessSection();
  // initAccordions();
  // initGallery();
  // initVideosMarquee();
  // initVerticalVideoSliders();
  initCtaSection();
  initContactForm();
  // initSocialLinks();
  initFooter();
});
