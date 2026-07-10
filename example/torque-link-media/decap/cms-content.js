function setText(selector, value) {
    const element = document.querySelector(selector);
    if (element && typeof value === "string") element.textContent = value;
}

function setParagraphs(containerSelector, paragraphs) {
    const container = document.querySelector(containerSelector);
    if (!container || !Array.isArray(paragraphs)) return;

    const existing = [...container.querySelectorAll("p")];
    existing.forEach((paragraph, index) => {
        if (paragraphs[index]) paragraph.textContent = paragraphs[index];
    });
}

function setImage(selector, image, alt) {
    const element = document.querySelector(selector);
    if (!element) return;
    if (image) element.setAttribute("src", image);
    if (alt) element.setAttribute("alt", alt);
}

function updateNavigation(items) {
    if (!Array.isArray(items)) return;

    const headerNav = document.querySelector(".header__nav");
    if (headerNav) {
        headerNav.innerHTML = items
            .map((item, index) =>
                '<a ' +
                (index === 0 ? 'class="is-active" ' : "") +
                'href="' +
                item.url +
                '" data-scrollspy-link>' +
                item.label +
                "</a>"
            )
            .join("");
    }
}

function updateFramework(framework) {
    if (!framework) return;
    setText("#proof-title", framework.title);

    document.querySelectorAll(".proof__row").forEach((row, index) => {
        const item = framework.items?.[index];
        if (!item) return;

        const spans = row.querySelectorAll("span");
        if (spans[0]) spans[0].textContent = item.label;
        const value = row.querySelector("strong");
        if (value) value.textContent = item.value;
        if (spans[1]) spans[1].textContent = item.description;
    });
}

function updateMediaBand(mediaBand) {
    if (!mediaBand) return;
    setText(".media-band .section__eyebrow", mediaBand.eyebrow);
    setText("#media-title", mediaBand.title);

    document.querySelectorAll(".media-band__card").forEach((card, index) => {
        const item = mediaBand.cards?.[index];
        if (!item) return;

        setImage(".media-band__card:nth-child(" + (index + 1) + ") img", item.image, item.alt);
        const title = card.querySelector("strong");
        const caption = card.querySelector("figcaption");
        if (title) title.textContent = item.title;
        if (caption) {
            [...caption.childNodes].forEach((node) => {
                if (node.nodeType === Node.TEXT_NODE) node.textContent = " " + item.text;
            });
        }
    });
}

function updateServices(services) {
    if (!Array.isArray(services)) return;

    document.querySelectorAll(".services__card").forEach((card, index) => {
        const item = services[index];
        if (!item) return;

        setText(".services__card:nth-child(" + (index + 1) + ") h3", item.title);
        setText(".services__card:nth-child(" + (index + 1) + ") p", item.text);
    });
}

function updateNotes(notes) {
    if (!notes) return;
    setText(".testimonials .section__eyebrow", notes.eyebrow);
    setText("#testimonials-title", notes.title);
    setText(".testimonials__copy", notes.copy);

    document.querySelectorAll(".testimonial-slider__slide").forEach((slide, index) => {
        const item = notes.slides?.[index];
        if (!item) return;

        const image = slide.querySelector("img");
        if (image) {
            if (item.image) image.setAttribute("src", item.image);
            if (item.alt) image.setAttribute("alt", item.alt);
        }
        setText(".testimonial-slider__slide:nth-child(" + (index + 1) + ") blockquote", "“" + item.quote + "”");
        setText(".testimonial-slider__slide:nth-child(" + (index + 1) + ") cite", item.cite);
    });
}

function updateWhy(why) {
    if (!why) return;
    setText("#why-title", why.title);
    setParagraphs(".why__copy", why.paragraphs);
    setImage(".why__image img", why.image, why.imageAlt);

    document.querySelectorAll(".why__panel > div").forEach((item, index) => {
        const benefit = why.benefits?.[index];
        if (!benefit) return;
        const title = item.querySelector("strong");
        const text = item.querySelector("span");
        if (title) title.textContent = benefit.title;
        if (text) text.textContent = benefit.text;
    });
}

function updateProcess(process) {
    if (!process) return;
    setText("#process-title", process.title);

    document.querySelectorAll(".process__list li").forEach((item, index) => {
        const step = process.steps?.[index];
        if (!step) return;
        const title = item.querySelector("h3");
        const text = item.querySelector("p");
        if (title) title.textContent = step.title;
        if (text) text.textContent = step.text;
    });

    setImage(".process__media img", process.mediaImage, process.mediaAlt);
    setText(".process__media strong", process.mediaTitle);
    const caption = document.querySelector(".process__media figcaption");
    if (caption && process.mediaText) {
        [...caption.childNodes].forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) node.textContent = " " + process.mediaText;
        });
    }
}

function updateContact(contact) {
    if (!contact) return;
    setText("#contact-title", contact.title);
    setText(".contact__grid > div > p", contact.text);

    const form = document.querySelector(".contact__form");
    if (form && form.getAttribute("action") === "contact.php") {
        form.setAttribute("action", "../contact.php");
    }
}

function applyCmsContent(content) {
    if (content.meta?.title) document.title = content.meta.title;
    const description = document.querySelector('meta[name="description"]');
    if (description && content.meta?.description) description.setAttribute("content", content.meta.description);

    updateNavigation(content.navigation);
    setText(".hero .section__eyebrow", content.hero?.eyebrow);
    setText("#hero-title", content.hero?.title);
    setText(".hero__tagline", content.hero?.tagline);
    setText(".hero__copy", content.hero?.copy);
    setText(".hero__actions .button--primary", content.hero?.primaryCta);
    setText(".hero__actions .button--secondary", content.hero?.secondaryCta);
    updateFramework(content.framework);
    updateMediaBand(content.mediaBand);
    updateServices(content.services);
    updateNotes(content.notes);
    updateWhy(content.why);
    updateProcess(content.process);
    setText(".cta .section__eyebrow", content.cta?.eyebrow);
    setText("#cta-title", content.cta?.title);
    setText(".cta .button", content.cta?.button);
    updateContact(content.contact);
    setText(".footer small span", content.footer?.copyright);

    if (typeof initSmoothScroll === "function") initSmoothScroll();
    if (typeof initScrollspy === "function") initScrollspy();
}

document.addEventListener("DOMContentLoaded", async () => {
    try {
        const response = await fetch("content/site.json", { cache: "no-store" });
        if (!response.ok) throw new Error("Could not load CMS content.");
        applyCmsContent(await response.json());
    } catch (error) {
        console.warn(error);
    }
});
