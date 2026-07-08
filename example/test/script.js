const form = document.querySelector(".contact-form");
const note = document.querySelector(".form-note");

document.querySelectorAll('a[href^="#"]').forEach((link) => {
  link.addEventListener("click", (event) => {
    const target = document.querySelector(link.getAttribute("href"));

    if (!target) return;

    event.preventDefault();
    target.scrollIntoView({ behavior: "smooth", block: "start" });
  });
});

if (form && note) {
  form.addEventListener("submit", () => {
    note.textContent = "Opening your email client with the enquiry details.";
  });
}
