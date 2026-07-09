function initContactForm() {
    const form = document.querySelector(".contact__form");
    const note = document.querySelector(".contact__note");

    if (!form || !note) return;

    form.addEventListener("submit", () => {
        note.textContent = "Opening your email client with the enquiry details.";
    });
}
