(() => {
    const defaultText = () =>
        document.body?.dataset.loadingText || "Loading...";

    const setLoading = (button) => {
        if (!button || button.disabled) return;
        button.dataset.originalHtml = button.innerHTML;
        button.disabled = true;
        const text = button.dataset.loadingText || defaultText();
        button.innerHTML = `<span class="btn-spinner" aria-hidden="true"></span><span>${text}</span>`;
    };

    document.addEventListener(
        "submit",
        (event) => {
            const form = event.target;
            if (
                !(form instanceof HTMLFormElement) ||
                form.hasAttribute("data-no-loading")
            )
                return;

            window.setTimeout(() => {
                if (event.defaultPrevented) return;
                form.querySelectorAll(
                    'button[type="submit"], button:not([type]), input[type="submit"]',
                ).forEach(setLoading);
            }, 0);
        },
        true,
    );

    window.addEventListener("pageshow", (event) => {
        if (!event.persisted) return;
        document
            .querySelectorAll("button[data-original-html]")
            .forEach((button) => {
                button.disabled = false;
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            });
    });
})();
