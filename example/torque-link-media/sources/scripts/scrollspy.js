function initScrollspy() {
    const header = document.querySelector(".header");
    const links = [...document.querySelectorAll("[data-scrollspy-link]")];
    const sections = links
        .map((link) => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);

    if (!links.length || !sections.length) return;

    const setActiveLink = (id) => {
        links.forEach((link) => {
            const isActive = link.getAttribute("href") === "#" + id;
            link.classList.toggle("is-active", isActive);

            if (isActive) {
                link.setAttribute("aria-current", "page");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    };

    const updateScrollspy = () => {
        const headerHeight = header?.offsetHeight || 0;
        const scrollPoint = window.scrollY + headerHeight + 96;
        let activeId = sections[0].id;

        sections.forEach((section) => {
            if (section.offsetTop <= scrollPoint) {
                activeId = section.id;
            }
        });

        setActiveLink(activeId);
    };

    links.forEach((link) => {
        link.addEventListener("click", () => {
            setActiveLink(link.getAttribute("href").slice(1));
        });
    });

    updateScrollspy();
    window.addEventListener("scroll", updateScrollspy, { passive: true });
    window.addEventListener("resize", updateScrollspy);
}
