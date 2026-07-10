function initContactForm() {
    const form = document.querySelector(".contact__form");
    const note = document.querySelector(".contact__note");
    const submit = form ? form.querySelector('[type="submit"]') : null;
    const startedAt = form ? form.querySelector('[name="started_at"]') : null;

    if (!form || !note || !submit) return;

    if (startedAt) {
        startedAt.value = String(Date.now());
    }

    function setNote(message, state = "") {
        note.textContent = message;
        note.dataset.state = state;
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        submit.disabled = true;
        setNote("Sending your enquiry...");

        try {
            const response = await fetch(form.action, {
                method: "POST",
                body: new FormData(form),
                headers: {
                    Accept: "application/json"
                }
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(result.message || "The enquiry could not be sent.");
            }

            form.reset();

            if (startedAt) {
                startedAt.value = String(Date.now());
            }

            setNote(result.message || "Thanks. Your enquiry was sent.", "success");
        } catch (error) {
            setNote(error.message || "Something went wrong. Please try again.", "error");
        } finally {
            submit.disabled = false;
        }
    });
}
