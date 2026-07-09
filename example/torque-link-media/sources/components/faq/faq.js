function initAccordions() {
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
}
