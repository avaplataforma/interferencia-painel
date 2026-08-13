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
    height: 3.25rem !important;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile > .tile-link .tile-top > .tileiconcontainer,
body.mundointer-brand-active #multi_section_tiles.tiles > .tile > .tile-link .tile-top > .tiletopright {
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
    padding: 1rem 0 0 !important;
    line-height: normal !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-textinner {
    position: static !important;
    top: auto !important;
    transform: none !important;
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
body.mundointer-brand-active #multi_section_tiles .mundointer-module-foot {
    display: grid;
    gap: .45rem;
    margin-top: auto;
    padding-top: 1rem;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-module-state {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    color: #647482;
    font-size: .75rem;
    font-weight: 800;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-module-state strong {
    color: #334554;
    font-size: inherit;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-module-bar,
body.mundointer-brand-active .mundointer-course-progressbar {
    position: relative;
    overflow: hidden;
    width: 100%;
    height: .45rem;
    border-radius: 999px;
    background: #e8edf1;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-module-bar > span,
body.mundointer-brand-active .mundointer-course-progressbar > span {
    display: block;
    width: var(--mundointer-progress, 0%);
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, var(--mundointer-primary), color-mix(in srgb, var(--mundointer-primary) 72%, var(--mundointer-secondary)));
    transition: width .35s ease;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.mundointer-module-complete {
    border-color: color-mix(in srgb, #149447 45%, #d9e1e8);
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.mundointer-module-complete .mundointer-module-state strong {
    color: #11783a;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.mundointer-module-current {
    border-color: color-mix(in srgb, var(--mundointer-primary) 62%, #d9e1e8);
    box-shadow: 0 1rem 2.25rem color-mix(in srgb, var(--mundointer-primary) 17%, transparent) !important;
}
/*
 * Trema renders an opened Tiles section as another grid item. Without an
 * explicit span it inherits the width of a single module card, squeezing the
 * heading and its activities into a narrow vertical strip.
 */
body.mundointer-brand-active #multi_section_tiles.tiles > .section.state-visible {
    grid-column: 1 / -1;
    width: 100% !important;
    min-width: 0 !important;
    max-width: none !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .format_tiles_section_content {
    width: 100% !important;
    max-width: none !important;
    min-height: 0 !important;
    padding: 1.25rem !important;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 18%, #d9e1e8);
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 .8rem 2rem rgba(25, 45, 65, .14);
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .pagesechead,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .sectiontitlecontainer,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .sectiontitle {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    height: auto !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .sectiontitle {
    padding: 0 3.25rem 1rem 0;
    border-bottom: 2px solid var(--mundointer-primary);
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .sectiontitle h2 {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    color: #1d2b38;
    font-size: clamp(1.2rem, 2vw, 1.65rem) !important;
    font-weight: 800;
    line-height: 1.25 !important;
    overflow-wrap: anywhere;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .format-tiles-cm-list.subtiles {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: stretch;
    gap: 1rem;
    width: 100% !important;
    max-width: none !important;
    height: auto !important;
    margin: 1rem 0 0 !important;
    padding: 0 !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) {
    width: 100% !important;
    max-width: none !important;
    height: auto !important;
    min-height: 7.5rem;
    margin: 0 !important;
    padding: 1rem !important;
    border: 1px solid #e0e7ed;
    border-radius: .85rem !important;
    background: #fff !important;
    box-shadow: 0 .35rem 1rem rgba(25, 45, 65, .09) !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .cm-link {
    display: grid !important;
    grid-template-columns: 3.25rem minmax(0, 1fr) auto;
    align-items: center !important;
    gap: 1rem;
    width: 100% !important;
    height: 100% !important;
    min-height: 5.5rem;
    padding: .25rem .5rem !important;
    color: #1d2b38;
    text-decoration: none !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .activityiconcontainer {
    position: static !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 3.25rem !important;
    min-width: 3.25rem !important;
    height: 3.25rem !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    border-radius: .85rem !important;
    box-sizing: border-box !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .activityiconcontainer img {
    display: block !important;
    width: 1.75rem !important;
    max-width: none !important;
    height: 1.75rem !important;
    margin: 0 !important;
    padding: 0 !important;
    object-fit: contain;
    line-height: 1 !important;
    vertical-align: middle !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .activityname {
    position: static !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    width: auto !important;
    height: auto !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: left !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile.spacer {
    display: none !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile h5 {
    margin: 0 !important;
    font-size: 1rem !important;
    line-height: 1.35 !important;
    overflow-wrap: anywhere;
    text-align: left !important;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-activity-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.55rem;
    padding: .55rem .85rem;
    border-radius: .7rem;
    color: #fff;
    background: var(--mundointer-primary);
    box-shadow: 0 .35rem .8rem color-mix(in srgb, var(--mundointer-primary) 22%, transparent);
    font-size: .78rem;
    font-weight: 850;
    line-height: 1.1;
    text-align: center;
    white-space: nowrap;
}
body.mundointer-brand-active .mundointer-course-banner {
    overflow: hidden;
    margin-bottom: .85rem !important;
    border: 1px solid #e2e8ee;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 .6rem 1.6rem rgba(25, 45, 65, .08);
}
body.mundointer-brand-active .mundointer-course-banner img {
    display: block;
    width: 100%;
    max-height: 18rem;
    object-fit: cover;
    border-radius: 0 !important;
}
body.mundointer-brand-active .mundointer-course-overview {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    gap: 1rem 1.5rem;
    margin: 0 0 1.35rem;
    padding: 1.1rem 1.2rem;
    border: 1px solid #dde5eb;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 .55rem 1.45rem rgba(25, 45, 65, .08);
}
body.mundointer-brand-active .mundointer-course-overview-copy {
    display: grid;
    gap: .55rem;
    min-width: 0;
}
body.mundointer-brand-active .mundointer-course-overview-heading {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
}
body.mundointer-brand-active .mundointer-course-overview-heading strong {
    color: #1d2b38;
    font-size: 1rem;
}
body.mundointer-brand-active .mundointer-course-overview-heading span {
    color: var(--mundointer-primary);
    font-size: .9rem;
    font-weight: 900;
}
body.mundointer-brand-active .mundointer-course-meta {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem .9rem;
    color: #647482;
    font-size: .78rem;
    font-weight: 700;
}
body.mundointer-brand-active .mundointer-continue-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    padding: .75rem 1rem;
    border: 1px solid var(--mundointer-primary);
    border-radius: .8rem;
    color: #fff !important;
    background: var(--mundointer-primary);
    box-shadow: 0 .45rem 1rem color-mix(in srgb, var(--mundointer-primary) 24%, transparent);
    font-size: .9rem;
    font-weight: 850;
    text-decoration: none !important;
    white-space: nowrap;
}
body.mundointer-brand-active .mundointer-continue-button:hover,
body.mundointer-brand-active .mundointer-continue-button:focus {
    color: #fff !important;
    background: color-mix(in srgb, var(--mundointer-primary) 86%, black);
    transform: translateY(-1px);
}
body.mundointer-brand-active.mundointer-course-experience-mounted .overall-progress {
    display: none !important;
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
    border-left: 3px solid var(--mundointer-primary);
    border-radius: .55rem;
    color: var(--mundointer-secondary);
    background: var(--mundointer-primary-soft);
}
body.mundointer-brand-active #course-index [data-for="cm_completion"].completion_complete {
    color: #149447;
}
body.mundointer-brand-active #course-index [data-for="cm_completion"].completion_incomplete {
    color: #8b98a3;
}
@media (max-width: 760px) {
    body.mundointer-brand-active #multi_section_tiles.tiles {
        grid-template-columns: 1fr;
    }
    body.mundointer-brand-active #multi_section_tiles.tiles > .tile {
        min-height: 9.5rem;
    }
    body.mundointer-brand-active #multi_section_tiles .section.state-visible .format-tiles-cm-list.subtiles {
        grid-template-columns: 1fr;
    }
    body.mundointer-brand-active .mundointer-course-overview {
        grid-template-columns: 1fr;
    }
    body.mundointer-brand-active .mundointer-continue-button {
        width: 100%;
    }
    body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .cm-link {
        grid-template-columns: 3.25rem minmax(0, 1fr);
    }
    body.mundointer-brand-active #multi_section_tiles .mundointer-activity-action {
        grid-column: 1 / -1;
        width: 100%;
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
    function enhanceMundoInterCourse() {
        var tilesRoot = document.querySelector("#multi_section_tiles");
        if (!tilesRoot) {
            return;
        }

        document.querySelectorAll("#multi_section_tiles .tile-text h3").forEach(function(title) {
            if (title.dataset.mundointerOriginalTitle) {
                return;
            }
            var original = (title.textContent || "").trim();
            title.dataset.mundointerOriginalTitle = original;
            var concise = original.replace(/^M[oó]dulo\s+\d+\s*[-–—:]\s*/i, "").trim();
            if (concise) {
                title.textContent = concise;
                title.setAttribute("aria-label", original);
            }
        });

        document.querySelectorAll("#multi_section_tiles.tiles > .tile").forEach(function(tile) {
            if (tile.dataset.mundointerCourseTile) {
                return;
            }
            tile.dataset.mundointerCourseTile = "1";

            var indicator = tile.querySelector(".progress-indic[data-numcomplete][data-numoutof]");
            var complete = indicator ? Number(indicator.getAttribute("data-numcomplete") || 0) : 0;
            var total = indicator ? Number(indicator.getAttribute("data-numoutof") || 0) : 0;
            var percent = total > 0 ? Math.max(0, Math.min(100, Math.round((complete / total) * 100))) : 0;
            var label = "Não iniciado";

            if (total > 0 && complete >= total) {
                label = "Concluído";
                tile.classList.add("mundointer-module-complete");
            } else if (complete > 0) {
                label = "Em andamento";
                tile.classList.add("mundointer-module-current");
            }

            var content = tile.querySelector(".tile-content") || tile;
            var foot = document.createElement("div");
            foot.className = "mundointer-module-foot";

            var state = document.createElement("div");
            state.className = "mundointer-module-state";
            var stateLabel = document.createElement("strong");
            stateLabel.textContent = label;
            var stateCount = document.createElement("span");
            stateCount.textContent = complete + "/" + total;
            state.appendChild(stateLabel);
            state.appendChild(stateCount);

            var bar = document.createElement("div");
            bar.className = "mundointer-module-bar";
            var fill = document.createElement("span");
            fill.style.setProperty("--mundointer-progress", percent + "%");
            bar.appendChild(fill);

            foot.appendChild(state);
            foot.appendChild(bar);
            content.appendChild(foot);
        });

        document.querySelectorAll("#multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .cm-link").forEach(function(link) {
            if (link.querySelector(".mundointer-activity-action")) {
                return;
            }
            var href = link.getAttribute("href") || "";
            var action = document.createElement("span");
            action.className = "mundointer-activity-action";
            action.textContent = href.indexOf("/mod/quiz/") !== -1
                ? "Fazer avaliação"
                : (href.indexOf("/mod/url/") !== -1 ? "Assistir aula" : "Abrir atividade");
            link.appendChild(action);
        });

        if (!document.querySelector(".mundointer-course-overview")) {
            var overall = document.querySelector(".overall-progress[data-numcomplete][data-numoutof]")
                || document.querySelector(".overall-progress");
            var overallComplete = overall ? Number(overall.getAttribute("data-numcomplete") || 0) : 0;
            var overallTotal = overall ? Number(overall.getAttribute("data-numoutof") || 0) : 0;
            var overallPercent = overallTotal > 0
                ? Math.max(0, Math.min(100, Math.round((overallComplete / overallTotal) * 100)))
                : 0;
            var incompleteStatus = document.querySelector("#course-index [data-for=\"cm\"] [data-for=\"cm_completion\"].completion_incomplete");
            var incompleteItem = incompleteStatus ? incompleteStatus.closest("[data-for=\"cm\"]") : null;
            var nextLink = incompleteItem ? incompleteItem.querySelector("a[data-for=\"cm_name\"]") : null;
            var nextHref = nextLink ? nextLink.getAttribute("href") : "";
            var nextTitle = nextLink ? (nextLink.textContent || "").trim() : "";

            if (incompleteItem) {
                var courseIndexSection = incompleteItem.closest(".courseindex-section");
                var sectionNumber = courseIndexSection ? courseIndexSection.getAttribute("data-number") : "";
                var currentTile = sectionNumber !== null && sectionNumber !== ""
                    ? document.querySelector("#tile-" + sectionNumber)
                    : null;
                if (currentTile) {
                    currentTile.classList.add("mundointer-module-current");
                }
            }

            var overview = document.createElement("section");
            overview.className = "mundointer-course-overview";
            overview.setAttribute("aria-label", "Progresso do curso");

            var copy = document.createElement("div");
            copy.className = "mundointer-course-overview-copy";
            var heading = document.createElement("div");
            heading.className = "mundointer-course-overview-heading";
            var headingTitle = document.createElement("strong");
            headingTitle.textContent = "Seu progresso";
            var headingPercent = document.createElement("span");
            headingPercent.textContent = overallPercent + "% concluído";
            heading.appendChild(headingTitle);
            heading.appendChild(headingPercent);

            var progress = document.createElement("div");
            progress.className = "mundointer-course-progressbar";
            progress.setAttribute("role", "progressbar");
            progress.setAttribute("aria-valuemin", "0");
            progress.setAttribute("aria-valuemax", "100");
            progress.setAttribute("aria-valuenow", String(overallPercent));
            var progressFill = document.createElement("span");
            progressFill.style.setProperty("--mundointer-progress", overallPercent + "%");
            progress.appendChild(progressFill);

            var meta = document.createElement("div");
            meta.className = "mundointer-course-meta";
            var activityMeta = document.createElement("span");
            activityMeta.textContent = overallComplete + " de " + overallTotal + " atividades concluídas";
            var moduleMeta = document.createElement("span");
            moduleMeta.textContent = document.querySelectorAll("#multi_section_tiles.tiles > .tile").length + " módulos";
            meta.appendChild(activityMeta);
            meta.appendChild(moduleMeta);

            copy.appendChild(heading);
            copy.appendChild(progress);
            copy.appendChild(meta);
            overview.appendChild(copy);

            var continueButton = document.createElement(nextHref ? "a" : "span");
            continueButton.className = "mundointer-continue-button";
            continueButton.textContent = nextHref ? "Continuar estudando" : "Curso concluído";
            if (nextHref) {
                continueButton.setAttribute("href", nextHref);
                if (nextTitle) {
                    continueButton.setAttribute("title", "Próxima atividade: " + nextTitle);
                }
            }
            overview.appendChild(continueButton);

            var banner = document.querySelector(".mundointer-course-banner");
            var bannerBlock = banner ? banner.parentElement : null;
            if (bannerBlock && bannerBlock.parentElement) {
                bannerBlock.insertAdjacentElement("afterend", overview);
            } else {
                tilesRoot.insertAdjacentElement("beforebegin", overview);
            }
            document.body.classList.add("mundointer-course-experience-mounted");
        }

        var mountedOverview = document.querySelector(".mundointer-course-overview");
        var mountedIncompleteStatus = document.querySelector("#course-index [data-for=\"cm\"] [data-for=\"cm_completion\"].completion_incomplete");
        var mountedIncompleteItem = mountedIncompleteStatus ? mountedIncompleteStatus.closest("[data-for=\"cm\"]") : null;
        var mountedNextLink = mountedIncompleteItem ? mountedIncompleteItem.querySelector("a[data-for=\"cm_name\"]") : null;
        if (mountedOverview && mountedNextLink) {
            var mountedButton = mountedOverview.querySelector(".mundointer-continue-button");
            if (mountedButton && mountedButton.tagName !== "A") {
                var replacementButton = document.createElement("a");
                replacementButton.className = mountedButton.className;
                mountedButton.replaceWith(replacementButton);
                mountedButton = replacementButton;
            }
            if (mountedButton) {
                var mountedNextTitle = (mountedNextLink.textContent || "").trim();
                var mountedNextHref = mountedNextLink.getAttribute("href") || "#";
                var mountedNextTooltip = mountedNextTitle ? "Próxima atividade: " + mountedNextTitle : "";
                if ((mountedButton.textContent || "").trim() !== "Continuar estudando") {
                    mountedButton.textContent = "Continuar estudando";
                }
                if (mountedButton.getAttribute("href") !== mountedNextHref) {
                    mountedButton.setAttribute("href", mountedNextHref);
                }
                if (mountedNextTooltip && mountedButton.getAttribute("title") !== mountedNextTooltip) {
                    mountedButton.setAttribute("title", mountedNextTooltip);
                }
            }
        }
    }

    function mountMundoInterBrand() {
        var brand = document.querySelector(".mundointer-theme-brand[data-franquia]");
        if (!brand) {
            return;
        }

        document.body.classList.add("mundointer-brand-active");
        enhanceMundoInterCourse();
        window.setTimeout(enhanceMundoInterCourse, 250);
        var courseEnhanceAttempts = 0;
        var courseEnhanceTimer = window.setInterval(function() {
            enhanceMundoInterCourse();
            courseEnhanceAttempts += 1;
            if (courseEnhanceAttempts >= 30) {
                window.clearInterval(courseEnhanceTimer);
            }
        }, 500);
        var courseObserver = new MutationObserver(function() {
            enhanceMundoInterCourse();
        });
        courseObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ["class", "data-value"]
        });
        window.setTimeout(function() {
            courseObserver.disconnect();
            window.clearInterval(courseEnhanceTimer);
        }, 15000);
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
