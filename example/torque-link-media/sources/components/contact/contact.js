function initContactForm() {
    const form = document.querySelector(".contact__form");
    const note = document.querySelector(".contact__note");
    const submit = form ? form.querySelector('[type="submit"]') : null;
    const siteKey = document.querySelector('meta[name="recaptcha-site-key"]')?.content;

    if (!form || !note || !submit) return;

    let recaptchaLoader = null;

    function setNote(message, state = "") {
        note.textContent = message;
        note.dataset.state = state;
    }

    function loadRecaptcha() {
        if (!siteKey || siteKey === "REPLACE_WITH_RECAPTCHA_SITE_KEY") {
            return Promise.reject(new Error("Missing reCAPTCHA site key."));
        }

        if (window.grecaptcha) {
            return Promise.resolve(window.grecaptcha);
        }

        if (recaptchaLoader) {
            return recaptchaLoader;
        }

        recaptchaLoader = new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.defer = true;
            script.onload = () => resolve(window.grecaptcha);
            script.onerror = () => reject(new Error("Could not load reCAPTCHA."));
            document.head.append(script);
        });

        return recaptchaLoader;
    }

    async function getRecaptchaToken() {
        const action = form.dataset.recaptchaAction || "contact";
        const grecaptcha = await loadRecaptcha();

        return new Promise((resolve) => {
            grecaptcha.ready(() => {
                resolve(grecaptcha.execute(siteKey, { action }));
            });
        });
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        submit.disabled = true;
        setNote("Checking the enquiry and sending it securely...");

        try {
            const recaptchaToken = await getRecaptchaToken();
            const formData = new FormData(form);
            formData.append("recaptcha_token", recaptchaToken);
            formData.append("recaptcha_action", form.dataset.recaptchaAction || "contact");

            const response = await fetch(form.action, {
                method: "POST",
                body: formData,
                headers: {
                    Accept: "application/json"
                }
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || "The enquiry could not be sent.");
            }

            form.reset();
            setNote(result.message || "Thanks. Your enquiry was sent.", "success");
        } catch (error) {
            setNote(error.message || "Something went wrong. Please try again.", "error");
        } finally {
            submit.disabled = false;
        }
    });
}
