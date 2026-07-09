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
