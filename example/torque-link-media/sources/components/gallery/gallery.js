function initGallery() {
    const gallery = document.querySelector("#campaign-gallery");
    let galleryIsotope;

    function initIsotopeGallery() {
        if (!gallery || !window.Isotope) return;

        if (galleryIsotope) {
            galleryIsotope.layout();
            return;
        }

        galleryIsotope = new Isotope(gallery, {
            itemSelector: ".gallery__item",
            layoutMode: "masonry",
            percentPosition: true,
            transitionDuration: "0.45s",
            masonry: {
                columnWidth: ".gallery__sizer",
                gutter: ".gallery__gutter",
            },
        });

        gallery.querySelectorAll("img").forEach((image) => {
            if (image.complete) return;
            image.addEventListener("load", () => galleryIsotope?.layout(), {once: true});
        });
    }

    initIsotopeGallery();
    window.addEventListener("load", initIsotopeGallery);

    if (!gallery) return;

    import("https://unpkg.com/photoswipe@5.4.4/dist/photoswipe-lightbox.esm.js")
        .then(({default: PhotoSwipeLightbox}) => {
            const lightbox = new PhotoSwipeLightbox({
                gallery: "#campaign-gallery",
                children: "a",
                showHideAnimationType: "zoom",
                pswpModule: () => import("https://unpkg.com/photoswipe@5.4.4/dist/photoswipe.esm.js"),
            });
            lightbox.init();
        })
        .catch(() => {
            gallery.dataset.lightbox = "fallback";
        });
}
