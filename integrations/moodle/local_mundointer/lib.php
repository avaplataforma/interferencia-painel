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
    gap: .45rem .9rem;
    flex-wrap: wrap;
    margin-top: 1.35rem;
    padding-top: 1rem;
    border-top: 1px solid #dfe5ea;
    color: #647482;
    font-size: .88rem;
    text-align: center;
}
.mundointer-login-support strong {
    color: var(--mundointer-secondary);
}
.mundointer-login-support a {
    color: var(--mundointer-primary);
    font-weight: 600;
    overflow-wrap: anywhere;
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
                        phoneLink.href = "tel:" + supportPhone.replace(/[^0-9+]/g, "");
                        phoneLink.textContent = supportPhone;
                        support.appendChild(phoneLink);
                    }
                    loginContainer.appendChild(support);
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
