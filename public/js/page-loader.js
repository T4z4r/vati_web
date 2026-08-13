(() => {
    const loader = document.getElementById("page-loader");

    if (!loader) return;

    const message = loader.querySelector("[data-loader-message]");
    const defaultMessage = message?.textContent ?? "";
    let hideTimer;

    const show = (text = defaultMessage) => {
        window.clearTimeout(hideTimer);
        if (message) message.textContent = text;
        loader.classList.remove("is-hidden");
        loader.setAttribute("aria-hidden", "false");
        document.documentElement.classList.add("page-is-loading");
    };

    const hide = () => {
        loader.classList.add("is-hidden");
        loader.setAttribute("aria-hidden", "true");
        document.documentElement.classList.remove("page-is-loading");
    };

    window.VatiPageLoader = { show, hide };

    const finishInitialLoad = () => {
        hideTimer = window.setTimeout(hide, 180);
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", finishInitialLoad, {
            once: true,
        });
    } else {
        finishInitialLoad();
    }

    window.addEventListener("load", finishInitialLoad, { once: true });
    window.addEventListener("pageshow", hide);

    document.addEventListener(
        "click",
        (event) => {
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            )
                return;

            const link = event.target.closest("a[href]");
            if (
                !link ||
                link.hasAttribute("download") ||
                link.hasAttribute("data-no-loader") ||
                link.hasAttribute("data-confirm") ||
                link.target === "_blank"
            )
                return;

            const url = new URL(link.href, window.location.href);
            if (
                url.origin !== window.location.origin ||
                !["http:", "https:"].includes(url.protocol) ||
                url.href === window.location.href ||
                (url.hash && url.pathname === window.location.pathname) ||
                /\/(?:export|download)(?:\/|$)/i.test(url.pathname)
            )
                return;

            show();
        },
        true,
    );

    window.setTimeout(hide, 15000);
})();
