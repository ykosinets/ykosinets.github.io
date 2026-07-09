function initVerticalVideoSliders() {
    document.querySelectorAll("[data-vertical-slider]").forEach((slider) => {
        const cards = [...slider.querySelectorAll(".vertical-video__card")];
        let active = Math.max(0, cards.findIndex((card) => card.classList.contains("is-active")));
        let slideTimer;

        function resetVideo(card) {
            const video = card?.querySelector("video");

            if (!video) return;

            video.pause();
            video.currentTime = 0;
        }

        function queueNext() {
            const card = cards[active];
            const video = card?.querySelector("video");

            window.clearTimeout(slideTimer);

            if (!video) {
                slideTimer = window.setTimeout(() => setActive(active + 1), 4000);
                return;
            }

            video.loop = false;
            video.currentTime = 0;
            video.play().catch(() => {
                slideTimer = window.setTimeout(() => setActive(active + 1), 4000);
            });
        }

        function setActive(nextIndex) {
            const previous = active;

            cards.forEach((card) => card.classList.remove("was-active"));
            cards[previous]?.classList.remove("is-active");
            cards[previous]?.classList.add("was-active");
            resetVideo(cards[previous]);

            active = (nextIndex + cards.length) % cards.length;
            cards[active]?.classList.add("is-active");
            queueNext();
        }

        cards.forEach((card, index) => {
            const video = card.querySelector("video");

            video?.addEventListener("ended", () => {
                if (index === active) setActive(active + 1);
            });
        });

        queueNext();
    });
}
