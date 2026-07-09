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
