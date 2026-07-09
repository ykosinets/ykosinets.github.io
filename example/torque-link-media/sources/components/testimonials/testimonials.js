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
