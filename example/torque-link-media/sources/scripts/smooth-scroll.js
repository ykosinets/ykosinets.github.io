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
