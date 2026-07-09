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
