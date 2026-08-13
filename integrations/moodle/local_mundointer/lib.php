<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the active franchise favicon and visual identity.
 */
function local_mundointer_before_standard_html_head(): string
{
    $brand = \local_mundointer\local\brand_resolver::current();
    if ($brand === null) {
        return '';
    }

    $primary = s((string)$brand['primary_color']);
    $secondary = s((string)$brand['secondary_color']);
    $favicon = s((string)$brand['favicon_url']);
    $faviconhtml = $favicon !== '' ? '<link rel="icon" href="'.$favicon.'">' : '';

    return $faviconhtml.'<meta name="theme-color" content="'.$primary.'"><style>
:root {
    --mundointer-primary: '.$primary.';
    --mundointer-secondary: '.$secondary.';
    --mundointer-primary-soft: color-mix(in srgb, var(--mundointer-primary) 12%, white);
}
.btn-primary,
.bg-primary {
    background-color: var(--mundointer-primary) !important;
    border-color: var(--mundointer-primary) !important;
}
.btn-primary:hover,
.btn-primary:focus {
    background-color: color-mix(in srgb, var(--mundointer-primary) 86%, black) !important;
    border-color: color-mix(in srgb, var(--mundointer-primary) 86%, black) !important;
}
.text-primary {
    color: var(--mundointer-primary) !important;
}
.form-control:focus {
    border-color: var(--mundointer-primary);
    box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--mundointer-primary) 20%, transparent);
}
.mundointer-theme-brand {
    display: none;
}
.mundointer-brand-ribbon {
    align-items: center;
    gap: .75rem;
    padding: .7rem 1rem;
    border-bottom: 3px solid var(--mundointer-primary);
    background: #fff;
    color: #17212b;
    position: relative;
    z-index: 1040;
}
.mundointer-brand-ribbon img,
.mundointer-navbar-brand img,
.mundointer-login-brand img {
    object-fit: contain;
}
.mundointer-brand-ribbon img {
    width: 2.45rem;
    height: 2.45rem;
}
.mundointer-brand-copy strong,
.mundointer-brand-copy small {
    display: block;
}
.mundointer-brand-copy small {
    color: #647482;
}

/* Trema: place the identity inside the native login card. */
.pagelayout-login.mundointer-brand-active #page-wrapper::before {
    content: "";
    position: fixed;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 12% 15%, color-mix(in srgb, var(--mundointer-secondary) 55%, transparent), transparent 42%),
        linear-gradient(145deg, color-mix(in srgb, var(--mundointer-secondary) 42%, transparent), transparent 58%, color-mix(in srgb, var(--mundointer-primary) 35%, transparent));
    z-index: 0;
}
.pagelayout-login.mundointer-brand-active #page {
    position: relative;
    z-index: 1;
}
.pagelayout-login.mundointer-brand-active #loginlogo,
.pagelayout-login.mundointer-brand-active .login-logo {
    display: none !important;
}
.mundointer-login-brand {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0 0 1.5rem;
    padding: 1rem 1.15rem;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 22%, #dce3e8);
    border-left: 5px solid var(--mundointer-primary);
    border-radius: .85rem;
    background: linear-gradient(135deg, #fff, var(--mundointer-primary-soft));
    box-shadow: 0 .35rem 1rem rgba(18, 36, 54, .08);
}
.mundointer-login-brand img {
    flex: 0 0 auto;
    width: 4.25rem;
    height: 4.25rem;
}
.mundointer-login-brand strong {
    color: var(--mundointer-secondary);
    font-size: 1.2rem;
    line-height: 1.25;
}
.mundointer-login-brand small {
    margin-top: .25rem;
    line-height: 1.35;
}
.mundointer-login-support {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .35rem .75rem;
    flex-wrap: wrap;
    margin-top: .7rem;
    padding: .6rem .75rem 0;
    border-top: 1px solid #dfe5ea;
    color: #647482;
    font-size: .82rem;
    line-height: 1.2;
    text-align: center;
}
.mundointer-login-support strong {
    color: var(--mundointer-secondary);
}
.mundointer-login-support a {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    color: var(--mundointer-primary);
    font-weight: 600;
    overflow-wrap: anywhere;
}
.mundointer-support-whatsapp-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
}
.mundointer-support-whatsapp-icon svg {
    display: block;
    width: 100%;
    height: 100%;
    fill: currentColor;
}

/* Trema: reuse its native navbar-brand link instead of adding another bar. */
.mundointer-navbar-brand {
    display: flex;
    align-items: center;
    gap: .55rem;
    min-width: 0;
}
.mundointer-navbar-brand img {
    flex: 0 0 auto;
    width: 2.45rem !important;
    height: 2.45rem;
}
.mundointer-navbar-brand .mundointer-brand-copy {
    min-width: 0;
}
.mundointer-navbar-brand strong {
    overflow: hidden;
    color: inherit;
    font-size: 1rem;
    line-height: 1.1;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mundointer-navbar-brand small {
    display: none;
}

/* Course format "Tiles/Blocos": modern Mundo Inter presentation layer. */
body.mundointer-brand-active:has(#format-tiles-multi-section-page) #page {
    background: linear-gradient(180deg, #f8fafc 0, #eef3f8 100%);
}
body.mundointer-brand-active:has(#format-tiles-multi-section-page) #region-main {
    background: transparent;
}
body.mundointer-brand-active #format-tiles-multi-section-page {
    width: 100%;
}
body.mundointer-brand-active #multi_section_tiles.tiles {
    counter-reset: mundointer-module;
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(13.75rem, 1fr));
    align-items: stretch;
    gap: 1rem;
    width: 100%;
    margin: 0 !important;
    padding: .25rem 0 1.5rem !important;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile {
    counter-increment: mundointer-module;
    width: 100% !important;
    min-width: 0 !important;
    height: auto !important;
    min-height: 12rem;
    margin: 0 !important;
    overflow: hidden !important;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 15%, #d9e1e8);
    border-radius: 1rem !important;
    background: #fff !important;
    box-shadow: 0 .55rem 1.45rem rgba(25, 45, 65, .08) !important;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile:hover,
body.mundointer-brand-active #multi_section_tiles.tiles > .tile:focus-within {
    z-index: 2;
    transform: translateY(-4px);
    border-color: color-mix(in srgb, var(--mundointer-primary) 45%, #d9e1e8);
    box-shadow: 0 1rem 2.2rem rgba(25, 45, 65, .14) !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-bg {
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    border-top: 4px solid var(--mundointer-primary);
    border-radius: inherit !important;
    background: radial-gradient(circle at 100% 0, color-mix(in srgb, var(--mundointer-primary) 12%, transparent), transparent 45%),
        linear-gradient(145deg, #fff 20%, color-mix(in srgb, var(--mundointer-primary) 4%, #fff)) !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-link {
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    padding: 1.1rem 1.15rem 1rem !important;
    border-radius: inherit;
}
body.mundointer-brand-active #multi_section_tiles .tile-content {
    display: flex !important;
    flex-direction: column;
    width: 100% !important;
    height: 100% !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-top {
    position: relative !important;
    display: flex !important;
    align-items: center;
    gap: .65rem;
    width: 100% !important;
    height: 3rem !important;
}
body.mundointer-brand-active #multi_section_tiles .tileiconcontainer,
body.mundointer-brand-active #multi_section_tiles .tiletopright {
    display: none !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-top::before {
    content: counter(mundointer-module, decimal-leading-zero);
    display: grid;
    place-items: center;
    width: 2.55rem;
    height: 2.55rem;
    flex: 0 0 2.55rem;
    border-radius: .8rem;
    color: #fff;
    background: linear-gradient(145deg, var(--mundointer-primary), color-mix(in srgb, var(--mundointer-primary) 78%, var(--mundointer-secondary)));
    box-shadow: 0 .35rem .8rem color-mix(in srgb, var(--mundointer-primary) 24%, transparent);
    font-size: .88rem;
    font-weight: 900;
    letter-spacing: .04em;
}
body.mundointer-brand-active #multi_section_tiles .tile-top::after {
    content: "MÓDULO";
    color: #71808d;
    font-size: .7rem;
    font-weight: 900;
    letter-spacing: .13em;
}
body.mundointer-brand-active #multi_section_tiles .tile-text {
    position: static !important;
    width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    padding: .7rem 0 0 !important;
    line-height: normal !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-textinner {
    position: static !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-text h3 {
    display: -webkit-box;
    overflow: hidden;
    margin: 0 !important;
    color: #1d2b38;
    font-size: 1.05rem !important;
    font-weight: 800;
    line-height: 1.32 !important;
    letter-spacing: -.018em;
    text-wrap: balance;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 4;
}
body.mundointer-brand-active .mundointer-course-banner {
    overflow: hidden;
    border: 1px solid #e2e8ee;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 .6rem 1.6rem rgba(25, 45, 65, .08);
}
body.mundointer-brand-active .mundointer-course-banner img {
    display: block;
    width: 100%;
    max-height: 24rem;
    object-fit: cover;
    border-radius: 0 !important;
}
body.mundointer-brand-active #course-index .courseindex-section {
    margin: .2rem .4rem;
    border-radius: .65rem;
    transition: background-color .15s ease;
}
body.mundointer-brand-active #course-index .courseindex-section:hover {
    background: var(--mundointer-primary-soft);
}
body.mundointer-brand-active #course-index .courseindex-item.pageitem {
    border-radius: .55rem;
    color: var(--mundointer-secondary);
    background: var(--mundointer-primary-soft);
}
@media (max-width: 760px) {
    body.mundointer-brand-active #multi_section_tiles.tiles {
        grid-template-columns: 1fr;
    }
    body.mundointer-brand-active #multi_section_tiles.tiles > .tile {
        min-height: 9.5rem;
    }
}
@media (max-width: 600px) {
    .mundointer-login-brand {
        gap: .75rem;
        padding: .85rem;
    }
    .mundointer-login-brand img {
        width: 3.25rem;
        height: 3.25rem;
    }
    .mundointer-navbar-brand .mundointer-brand-copy {
        display: none;
    }
}
</style>';
}

/**
 * Renders a portable brand marker. In Trema it is moved into native components.
 */
function local_mundointer_before_standard_top_of_body_html(): string
{
    $brand = \local_mundointer\local\brand_resolver::current();
    if ($brand === null) {
        return '';
    }

    $logo = s((string)$brand['logo_url']);
    $name = s((string)$brand['login_title']);
    $welcome = s((string)$brand['welcome_text']);
    $slug = s((string)$brand['slug']);
    $favicon = s((string)$brand['favicon_url']);
    $supportemail = s((string)($brand['support_email'] ?? ''));
    $supportphone = s((string)($brand['support_phone'] ?? ''));
    $pagetitle = s((string)$brand['login_title'].' | AVA');
    $logohtml = $logo !== '' ? '<img src="'.$logo.'" alt="">' : '';

    $html = '<span class="mundointer-theme-brand" data-franquia="'.$slug.'" data-favicon="'.$favicon.'" data-page-title="'.$pagetitle.'" data-support-email="'.$supportemail.'" data-support-phone="'.$supportphone.'">'
        .$logohtml
        .'<span class="mundointer-brand-copy"><strong>'.$name.'</strong><small>'.$welcome.'</small></span>'
        .'</span>';

    return $html.'<script>
(function() {
    function mountMundoInterBrand() {
        var brand = document.querySelector(".mundointer-theme-brand[data-franquia]");
        if (!brand) {
            return;
        }

        document.body.classList.add("mundointer-brand-active");
        var pageTitle = brand.getAttribute("data-page-title");
        if (pageTitle) {
            document.title = pageTitle;
        }
        var favicon = brand.getAttribute("data-favicon");
        if (favicon) {
            document.querySelectorAll("link[rel~=\'icon\'], link[rel=\'shortcut icon\'], link[rel=\'apple-touch-icon\']").forEach(function(link) {
                link.remove();
            });
            var icon = document.createElement("link");
            icon.rel = "icon";
            icon.href = favicon;
            document.head.appendChild(icon);
        }
        var login = document.body.classList.contains("pagelayout-login") || document.body.id.indexOf("page-login-") === 0;
        if (login) {
            var loginContainer = document.querySelector(".login-container");
            if (loginContainer) {
                loginContainer.querySelectorAll("#loginlogo, .login-logo").forEach(function(nativeLogo) {
                    nativeLogo.remove();
                });
                brand.classList.add("mundointer-login-brand");
                loginContainer.insertBefore(brand, loginContainer.firstChild);
                var supportEmail = brand.getAttribute("data-support-email") || "";
                var supportPhone = brand.getAttribute("data-support-phone") || "";
                if ((supportEmail || supportPhone) && !loginContainer.querySelector(".mundointer-login-support")) {
                    var support = document.createElement("div");
                    support.className = "mundointer-login-support";
                    var supportLabel = document.createElement("strong");
                    supportLabel.textContent = "Suporte";
                    support.appendChild(supportLabel);
                    if (supportEmail) {
                        var emailLink = document.createElement("a");
                        emailLink.href = "mailto:" + supportEmail;
                        emailLink.textContent = supportEmail;
                        support.appendChild(emailLink);
                    }
                    if (supportPhone) {
                        var phoneLink = document.createElement("a");
                        var whatsappNumber = supportPhone.replace(/[^0-9]/g, "");
                        if (whatsappNumber.length === 10 || whatsappNumber.length === 11) {
                            whatsappNumber = "55" + whatsappNumber;
                        }
                        phoneLink.href = "https://wa.me/" + whatsappNumber;
                        phoneLink.target = "_blank";
                        phoneLink.rel = "noopener noreferrer";
                        phoneLink.setAttribute("aria-label", "Falar com o suporte pelo WhatsApp: " + supportPhone);
                        phoneLink.title = "Falar pelo WhatsApp";
                        var whatsappIcon = document.createElement("span");
                        whatsappIcon.className = "mundointer-support-whatsapp-icon";
                        whatsappIcon.setAttribute("aria-hidden", "true");
                        whatsappIcon.innerHTML = "<svg viewBox=\"0 0 448 512\" focusable=\"false\"><path d=\"M380.9 97.1C339 55.1 283.2 32 223.9 32 101.5 32 1.9 131.6 1.9 254c0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.8l-6.7-4-69.8 18.3L72 359.1l-4.4-7c-18.5-29.4-28.2-63.4-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.7-186.6 184.7zm101.2-138.4c-5.5-2.8-32.8-16.1-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8s-14.3 18-17.6 21.8c-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z\"/></svg>";
                        phoneLink.appendChild(whatsappIcon);
                        phoneLink.appendChild(document.createTextNode(supportPhone));
                        support.appendChild(phoneLink);
                    }
                    (loginContainer.querySelector("[role=\"main\"]") || loginContainer.querySelector("main") || loginContainer).appendChild(support);
                }
                return;
            }
        }

        var navbar = document.querySelector("#page-wrapper nav.navbar .navbar-brand");
        if (navbar) {
            brand.classList.add("mundointer-navbar-brand");
            navbar.textContent = "";
            navbar.appendChild(brand);
            return;
        }

        brand.classList.add("mundointer-brand-ribbon");
        brand.style.display = "flex";
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", mountMundoInterBrand);
    } else {
        mountMundoInterBrand();
    }
})();
</script>';
}
