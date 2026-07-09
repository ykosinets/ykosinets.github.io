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
