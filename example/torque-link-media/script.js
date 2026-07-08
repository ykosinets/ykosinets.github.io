const form = document.querySelector(".contact-form");
const note = document.querySelector(".form-note");
const header = document.querySelector(".site-header");
const menuToggle = document.querySelector(".menu-toggle");
const nav = document.querySelector(".nav");

if (header && menuToggle && nav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = header.classList.toggle("menu-open");
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
      header.classList.remove("menu-open");
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
