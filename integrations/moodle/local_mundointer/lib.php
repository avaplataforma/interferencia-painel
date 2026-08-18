<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the active franchise favicon and visual identity.
 */
function local_mundointer_before_standard_html_head(): string
{
    if (is_siteadmin()) {
        return '';
    }
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
a.mundointer-link,
.mundointer-welcome a,
.mundointer-support-float a {
    color: var(--mundointer-primary);
}
.nav-link.active,
.nav-tabs .nav-link.active {
    border-bottom-color: var(--mundointer-primary) !important;
    color: var(--mundointer-primary) !important;
}
.mundointer-mycourses-hero {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin: 0 0 1.2rem;
    padding: 1rem 1.25rem;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 22%, #dce3e8);
    border-left: 5px solid var(--mundointer-primary);
    border-radius: .9rem;
    background: linear-gradient(135deg, #fff, var(--mundointer-primary-soft));
}
.mundointer-mycourses-hero img {
    width: 3.6rem;
    height: 3.6rem;
    object-fit: contain;
}
.mundointer-mycourses-copy strong,
.mundointer-mycourses-copy small {
    display: block;
}
.mundointer-mycourses-copy strong {
    color: var(--mundointer-secondary);
    font-size: 1.15rem;
}
.mundointer-mycourses-copy small {
    color: #647482;
    margin-top: .15rem;
}
.mundointer-mycourses-contacts {
    margin-left: auto;
    display: flex;
    gap: .45rem;
    flex-wrap: wrap;
}
.mundointer-mycourses-contacts a {
    display: inline-flex;
    align-items: center;
    padding: .45rem .8rem;
    border-radius: .6rem;
    background: var(--mundointer-primary);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
}
.mundointer-mycourses-contacts a:hover {
    background: color-mix(in srgb, var(--mundointer-primary) 86%, black);
}
body.mundointer-mycourses #region-main h1,
body.mundointer-mycourses #region-main .page-context-header,
body.mundointer-mycourses #region-main .page-header-headings,
body.mundointer-mycourses #page-header,
body.mundointer-mycourses #region-main > .card > .card-body > h1 {
    display: none !important;
}
body.mundointer-brand-active #usermenu a[href*="/user/profile.php"],
body.mundointer-brand-active #usermenu a[href*="/calendar/view.php"],
body.mundointer-brand-active #usermenu a[href*="/user/files.php"],
body.mundointer-brand-active #usermenu a[href*="/user/preferences.php"],
body.mundointer-brand-active #usermenu a[href*="/report/"],
body.mundointer-brand-active #usermenu a[href*="/grade/report/"],
body.mundointer-brand-active a.dropdown-item[href*="/user/profile.php"],
body.mundointer-brand-active a.dropdown-item[href*="/calendar/view.php"],
body.mundointer-brand-active a.dropdown-item[href*="/user/files.php"],
body.mundointer-brand-active a.dropdown-item[href*="/user/preferences.php"],
body.mundointer-brand-active a.dropdown-item[href*="/report/"],
body.mundointer-brand-active nav a[href*="/report/"],
body.mundointer-mycourses #usermenu a[href*="/user/profile.php"],
body.mundointer-mycourses #usermenu a[href*="/calendar/view.php"],
body.mundointer-mycourses #usermenu a[href*="/user/files.php"],
body.mundointer-mycourses #usermenu a[href*="/user/preferences.php"],
body.mundointer-mycourses #usermenu a[href*="/report/"],
body.mundointer-mycourses #usermenu a[href*="/grade/report/"],
body.mundointer-mycourses a.dropdown-item[href*="/user/profile.php"],
body.mundointer-mycourses a.dropdown-item[href*="/calendar/view.php"],
body.mundointer-mycourses a.dropdown-item[href*="/user/files.php"],
body.mundointer-mycourses a.dropdown-item[href*="/user/preferences.php"],
body.mundointer-mycourses a.dropdown-item[href*="/report/"] {
    display: none !important;
}
.mundointer-welcome {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin: 0 0 1rem;
    padding: .9rem 1.1rem;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 22%, #dce3e8);
    border-left: 5px solid var(--mundointer-primary);
    border-radius: .85rem;
    background: linear-gradient(135deg, #fff, var(--mundointer-primary-soft));
}
.mundointer-welcome strong,
.mundointer-welcome small {
    display: block;
}
.mundointer-welcome small {
    color: #647482;
    margin-top: .2rem;
}
.mundointer-welcome .mundointer-welcome-actions {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-wrap: wrap;
}
.mundointer-welcome .mundointer-welcome-actions a {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .45rem .8rem;
    border-radius: .6rem;
    background: var(--mundointer-primary);
    color: #fff;
    font-weight: 600;
    text-decoration: none;
}
.mundointer-welcome .mundointer-welcome-actions a:hover {
    background: color-mix(in srgb, var(--mundointer-primary) 86%, black);
}
.mundointer-support-float {
    position: fixed;
    z-index: 1300;
    right: 1rem;
    bottom: 1rem;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: .5rem;
}
.mundointer-support-float .mundointer-support-chip {
    display: none;
    flex-direction: column;
    gap: .35rem;
    padding: .75rem .85rem;
    border: 1px solid #dfe5ea;
    border-radius: .8rem;
    background: #fff;
    box-shadow: 0 .5rem 1.4rem rgba(18, 36, 54, .18);
}
.mundointer-support-float.is-open .mundointer-support-chip {
    display: flex;
}
.mundointer-support-float .mundointer-support-chip a {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    color: var(--mundointer-primary);
    font-weight: 600;
    text-decoration: none;
}
.mundointer-support-float .mundointer-support-chip strong {
    color: var(--mundointer-secondary);
    font-size: .85rem;
}
.mundointer-support-toggle {
    display: grid;
    place-items: center;
    width: 3.1rem;
    height: 3.1rem;
    border: 0;
    border-radius: 50%;
    background: var(--mundointer-primary);
    color: #fff;
    font-size: 1.15rem;
    cursor: pointer;
    box-shadow: 0 .5rem 1.4rem color-mix(in srgb, var(--mundointer-primary) 38%, transparent);
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
.mundointer-navbar-brand .mundointer-brand-copy {
    display: none !important;
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
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.spacer {
    display: none !important;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile:not(.spacer) {
    display: flex !important;
    flex-basis: auto !important;
    width: 100% !important;
    max-width: none !important;
    min-width: 0 !important;
    height: auto !important;
    min-height: 15rem;
    margin: 0 !important;
    overflow: hidden !important;
    border: 1px solid color-mix(in srgb, var(--mundointer-primary) 15%, #d9e1e8);
    border-radius: 1rem !important;
    background: #fff !important;
    box-shadow: 0 .55rem 1.45rem rgba(25, 45, 65, .08) !important;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile:not(.spacer):hover,
body.mundointer-brand-active #multi_section_tiles.tiles > .tile:not(.spacer):focus-within {
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
    position: relative !important;
    display: flex !important;
    flex: 1 1 auto;
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
    min-height: 100% !important;
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
    content: attr(data-mundointer-number);
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
    content: attr(data-mundointer-label);
    color: #71808d;
    font-size: .7rem;
    font-weight: 900;
    letter-spacing: .13em;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.mundointer-tile-book {
    border-color: color-mix(in srgb, #2563eb 42%, #d9e1e8);
    background: #f7fbff !important;
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-book .tile-bg {
    border-top-color: #2563eb;
    background: radial-gradient(circle at 100% 0, rgba(37, 99, 235, .14), transparent 45%),
        linear-gradient(145deg, #fff 20%, #eff6ff) !important;
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-book .tile-top::before {
    background: linear-gradient(145deg, #2563eb, #1d4ed8);
    box-shadow: 0 .35rem .8rem rgba(37, 99, 235, .24);
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-book .tile-top::after {
    color: #1d4ed8;
}
body.mundointer-brand-active #multi_section_tiles.tiles > .tile.mundointer-tile-assessment {
    border-color: color-mix(in srgb, #16a34a 42%, #d9e1e8);
    background: #f5fff8 !important;
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-assessment .tile-bg {
    border-top-color: #16a34a;
    background: radial-gradient(circle at 100% 0, rgba(22, 163, 74, .14), transparent 45%),
        linear-gradient(145deg, #fff 20%, #ecfdf3) !important;
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-assessment .tile-top::before {
    background: linear-gradient(145deg, #22c55e, #15803d);
    box-shadow: 0 .35rem .8rem rgba(22, 163, 74, .24);
}
body.mundointer-brand-active #multi_section_tiles .tile.mundointer-tile-assessment .tile-top::after {
    color: #15803d;
}
body.mundointer-brand-active #multi_section_tiles .tile-text {
    position: static !important;
    width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    flex: 1 1 auto;
    overflow: hidden !important;
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
    font-size: .96rem !important;
    font-weight: 800;
    line-height: 1.25 !important;
    letter-spacing: -.018em;
    text-wrap: balance;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 5;
}
body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-medium {
    font-size: .88rem !important;
    line-height: 1.22 !important;
}
body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-long {
    font-size: .79rem !important;
    line-height: 1.18 !important;
}
body.mundointer-brand-active #multi_section_tiles .mundointer-module-foot {
    position: relative;
    z-index: 1;
    flex: 0 0 auto;
    display: grid;
    gap: .45rem;
    margin-top: .75rem;
    padding-top: .6rem;
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
    border-bottom: 0 !important;
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
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr));
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
    min-height: 6.5rem;
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
    min-height: 4.5rem;
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
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.modtype_label,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.label {
    grid-column: 1 / -1;
    width: 100% !important;
    max-width: none !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: .75rem .9rem !important;
    border: 0 !important;
    border-left: 3px solid color-mix(in srgb, var(--mundointer-primary) 45%, #dfe6ec) !important;
    border-radius: .65rem !important;
    color: #334554;
    background: #f8fafc !important;
    box-shadow: none !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label .contentwithoutlink,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label .activityinstance,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label .description,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label .label_content {
    display: block !important;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    color: inherit !important;
    font-size: .82rem !important;
    line-height: 1.5 !important;
    text-align: left !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label h3,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label h4,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label h5,
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label p:last-child {
    margin-bottom: 0 !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label h5 {
    color: #334554 !important;
    font-size: .82rem !important;
    line-height: 1.4 !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-label .mundointer-subtitle {
    display: block !important;
    color: #203246 !important;
    font-size: 1.125rem !important;
    font-weight: 800 !important;
    line-height: 1.35 !important;
    letter-spacing: .035em !important;
    text-transform: uppercase !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-note {
    padding: 1rem !important;
    border-left-color: #d69b16 !important;
    background: #fffaf0 !important;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-note .label_content > .no-overflow > .no-overflow {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: .75rem;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-note .label_content > .no-overflow > .no-overflow > p {
    margin: 0 !important;
    padding: .75rem !important;
    border: 1px solid #eadfbf;
    border-radius: .7rem;
    background: #fff;
}
body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.mundointer-content-note img {
    max-width: 2.25rem !important;
    height: auto !important;
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
body.mundointer-brand-active .mundointer-activity-navigation {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: stretch;
    gap: .65rem;
    margin: 1rem 0;
    padding: .7rem;
    border: 1px solid #dde5eb;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 .45rem 1.25rem rgba(25, 45, 65, .08);
}
body.mundointer-brand-active .mundointer-activity-nav-link {
    display: flex;
    align-items: center;
    gap: .65rem;
    min-width: 0;
    min-height: 3.15rem;
    padding: .65rem .8rem;
    border: 1px solid #dfe6ec;
    border-radius: .78rem;
    color: var(--mundointer-secondary) !important;
    background: #fff;
    text-decoration: none !important;
    transition: border-color .15s ease, background-color .15s ease, transform .15s ease;
}
body.mundointer-brand-active .mundointer-activity-nav-link:hover,
body.mundointer-brand-active .mundointer-activity-nav-link:focus {
    border-color: color-mix(in srgb, var(--mundointer-primary) 45%, #dfe6ec);
    background: var(--mundointer-primary-soft);
    transform: translateY(-1px);
}
body.mundointer-brand-active .mundointer-activity-nav-link.is-next {
    justify-content: flex-end;
    text-align: right;
}
body.mundointer-brand-active .mundointer-activity-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    flex: 0 0 2rem;
    border-radius: .65rem;
    color: var(--mundointer-primary);
    background: var(--mundointer-primary-soft);
    font-size: 1.05rem;
    font-weight: 900;
}
body.mundointer-brand-active .mundointer-activity-nav-copy {
    display: grid;
    gap: .12rem;
    min-width: 0;
}
body.mundointer-brand-active .mundointer-activity-nav-copy small {
    color: #748390;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
}
body.mundointer-brand-active .mundointer-activity-nav-copy strong {
    overflow: hidden;
    font-size: .79rem;
    line-height: 1.25;
    text-overflow: ellipsis;
    white-space: nowrap;
}
body.mundointer-brand-active .mundointer-back-to-modules {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3.15rem;
    padding: .65rem 1rem;
    border: 1px solid var(--mundointer-primary);
    border-radius: .78rem;
    color: #fff !important;
    background: var(--mundointer-primary);
    font-size: .78rem;
    font-weight: 850;
    text-align: center;
    text-decoration: none !important;
    white-space: nowrap;
}
body.mundointer-brand-active.mundointer-course-experience-mounted .overall-progress {
    display: none !important;
}
body.mundointer-brand-active #course-index .courseindex-section {
    margin: .3rem .4rem;
    overflow: hidden;
    border: 1px solid transparent;
    border-radius: .75rem;
    transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-tools {
    position: sticky;
    top: 0;
    z-index: 5;
    display: grid;
    gap: .55rem;
    margin: 0 0 .55rem;
    padding: .65rem .55rem;
    border-bottom: 1px solid #dfe6ec;
    background: rgba(255, 255, 255, .96);
    backdrop-filter: blur(8px);
}
body.mundointer-brand-active #course-index .mundointer-courseindex-search {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1fr);
    align-items: center;
    min-height: 2.65rem;
    overflow: hidden;
    border: 1px solid #d7e0e7;
    border-radius: .7rem;
    color: #647482;
    background: #fff;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-search span {
    display: grid;
    place-items: center;
    font-size: 1rem;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-search input {
    width: 100%;
    min-width: 0;
    height: 2.55rem;
    padding: 0 .65rem 0 0;
    border: 0;
    outline: 0;
    color: #111827;
    background: transparent;
    font-size: .78rem;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .35rem;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-filter {
    min-width: 0;
    min-height: 2.15rem;
    padding: .4rem .25rem;
    border: 1px solid #d7e0e7;
    border-radius: .6rem;
    color: #405262;
    background: #fff;
    font-size: .67rem;
    font-weight: 800;
    line-height: 1.15;
}
body.mundointer-brand-active #course-index .mundointer-courseindex-filter.is-active {
    border-color: var(--mundointer-primary);
    color: #fff;
    background: var(--mundointer-primary);
}
body.mundointer-brand-active #course-index .mundointer-courseindex-result {
    color: #71808d;
    font-size: .68rem;
    font-weight: 750;
    text-align: right;
}
body.mundointer-brand-active #course-index .courseindex-section.mundointer-filter-hidden {
    display: none !important;
}
body.mundointer-brand-active #course-index .courseindex-section:hover {
    background: #f8fafc;
}
body.mundointer-brand-active #course-index .courseindex-section.mundointer-courseindex-active {
    border-color: color-mix(in srgb, var(--mundointer-primary) 42%, #dfe6ec) !important;
    border-left: 3px solid var(--mundointer-primary) !important;
    background: #fff !important;
    box-shadow: 0 .25rem .75rem rgba(20, 39, 60, .07);
}
body.mundointer-brand-active #course-index .courseindex-section-title {
    min-height: 2.75rem;
    padding: .35rem .45rem;
    border-radius: .7rem;
}
body.mundointer-brand-active #course-index .courseindex-section-title .courseindex-link,
body.mundointer-brand-active #course-index .courseindex-section-title a {
    min-width: 0;
    color: #111827 !important;
    font-size: .82rem;
    font-weight: 800;
    line-height: 1.25;
    white-space: normal;
}
body.mundointer-brand-active #course-index .courseindex-item-content {
    padding: 0 .3rem .35rem;
}
body.mundointer-brand-active #course-index .courseindex-item-content .courseindex-item {
    margin-top: .15rem;
    border-radius: .55rem;
}
body.mundointer-brand-active #course-index .courseindex-item-content .courseindex-item a,
body.mundointer-brand-active #course-index .courseindex-item-content .courseindex-item .courseindex-link,
body.mundointer-brand-active #course-index .courseindex-item-content .courseindex-item span:not([data-for="cm_completion"]) {
    color: #111827 !important;
}
body.mundointer-brand-active #course-index .courseindex-item.pageitem {
    border-left: 3px solid var(--mundointer-primary);
    border-radius: .55rem;
    color: #111827 !important;
    background: #f8fafc;
}
body.mundointer-brand-active #course-index .courseindex-section-title.pageitem,
body.mundointer-brand-active #course-index .courseindex-section-title.pageitem:hover,
body.mundointer-brand-active #course-index .courseindex-section-title.pageitem:focus-within {
    border-left: 3px solid var(--mundointer-primary) !important;
    color: #111827 !important;
    background: #f8fafc !important;
}
body.mundointer-brand-active #course-index .courseindex-section-title.pageitem a,
body.mundointer-brand-active #course-index .courseindex-section-title.pageitem .courseindex-link {
    color: #111827 !important;
}
body.mundointer-brand-active #course-index [data-for="cm_completion"].completion_complete {
    color: #149447;
}
body.mundointer-brand-active #course-index [data-for="cm_completion"].completion_incomplete {
    color: #8b98a3;
}
@media (max-width: 760px) {
    body.mundointer-brand-active #theme_boost-drawers-courseindex {
        width: min(88vw, 20rem);
        max-width: 20rem;
    }
    body.mundointer-brand-active #course-index .courseindex-section {
        margin-inline: .25rem;
    }
    body.mundointer-brand-active #multi_section_tiles.tiles {
        grid-template-columns: minmax(0, 1fr);
        gap: .85rem;
    }
    body.mundointer-brand-active #multi_section_tiles.tiles > .tile:not(.spacer) {
        width: 100% !important;
        max-width: none !important;
        min-height: 12.5rem;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-link {
        padding: 1rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text {
        padding-top: .7rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3 {
        font-size: .93rem !important;
        -webkit-line-clamp: 4;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-medium {
        font-size: .85rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-long {
        font-size: .77rem !important;
        -webkit-line-clamp: 5;
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
    body.mundointer-brand-active .mundointer-activity-navigation {
        grid-template-columns: 1fr 1fr;
    }
    body.mundointer-brand-active .mundointer-back-to-modules {
        grid-column: 1 / -1;
        grid-row: 1;
    }
    body.mundointer-brand-active .mundointer-activity-nav-copy strong {
        white-space: normal;
    }
    body.mundointer-brand-active #multi_section_tiles .section.state-visible .activity.subtile:not(.spacer) .cm-link {
        grid-template-columns: 3.25rem minmax(0, 1fr);
    }
    body.mundointer-brand-active #multi_section_tiles .mundointer-activity-action {
        grid-column: 1 / -1;
        width: 100%;
    }
}
@media (max-width: 420px) {
    body.mundointer-brand-active #multi_section_tiles.tiles {
        gap: .75rem;
    }
    body.mundointer-brand-active #multi_section_tiles.tiles > .tile:not(.spacer) {
        min-height: 12rem;
        border-radius: .85rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-link {
        padding: .85rem .9rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-top {
        height: 2.75rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-top::before {
        width: 2.25rem;
        height: 2.25rem;
        flex-basis: 2.25rem;
        border-radius: .7rem;
        font-size: .78rem;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3 {
        font-size: .88rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-medium {
        font-size: .81rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .tile-text h3.mundointer-module-title-long {
        font-size: .74rem !important;
    }
    body.mundointer-brand-active #multi_section_tiles .mundointer-module-state {
        font-size: .7rem;
    }
    body.mundointer-brand-active .mundointer-activity-navigation {
        gap: .5rem;
        padding: .55rem;
    }
    body.mundointer-brand-active .mundointer-activity-nav-link {
        align-items: flex-start;
        min-height: 4rem;
        padding: .6rem;
    }
    body.mundointer-brand-active .mundointer-activity-nav-icon {
        display: none;
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
    if (is_siteadmin()) {
        return '';
    }
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

    $html = '<span class="mundointer-theme-brand" data-franquia="'.$slug.'" data-favicon="'.$favicon.'" data-page-title="'.$pagetitle.'" data-support-email="'.$supportemail.'" data-support-phone="'.$supportphone.'" data-brand-name="'.s((string)($brand['name'] ?? '')).'" data-site-url="'.s((string)($brand['site_url'] ?? '')).'" data-brand-logo="'.s((string)($brand['logo_url'] ?? '')).'" data-student-name="'.s((string)(isloggedin()&&!isguestuser()?(fullname($USER)):'')).'" data-welcome-text="'.s((string)($brand['welcome_text'] ?? '')).'" data-moodle-base="'.s((string)(new moodle_url('/'))->out(false)).'" data-support-float="'.((bool)(get_config('local_mundointer','supportbutton') ?? true)?'1':'0').'" data-welcome="'.((bool)(get_config('local_mundointer','homewelcome') ?? true)?'1':'0').'" data-login-back="'.((bool)(get_config('local_mundointer','loginback') ?? true)?'1':'0').'">'
        .$logohtml
        .'<span class="mundointer-brand-copy"><strong>'.$name.'</strong><small>'.$welcome.'</small></span>'
        .'</span>';

    return $html.'<script>
(function() {
    function setMundoInterCourseIndexSection(section, open) {
        if (!section) {
            return;
        }
        var toggle = section.querySelector(".courseindex-section-title a[data-toggle=\"collapse\"]");
        var contentId = toggle ? (toggle.getAttribute("aria-controls") || "") : "";
        var content = contentId ? document.getElementById(contentId) : section.querySelector(".courseindex-item-content.collapse");

        section.classList.toggle("mundointer-courseindex-active", open);
        if (toggle) {
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
            toggle.classList.toggle("collapsed", !open);
        }
        if (content) {
            content.classList.toggle("show", open);
        }
    }

    function findMundoInterCourseIndexSection(root) {
        var match = (window.location.hash || "").match(/^#section-(\d+)$/);
        var number = match ? match[1] : "";

        if (!number) {
            var params = new URLSearchParams(window.location.search || "");
            number = params.get("section") || "";
        }
        if (!number) {
            var currentItem = root.querySelector(".courseindex-item.pageitem");
            var currentSection = currentItem ? currentItem.closest(".courseindex-section") : null;
            number = currentSection ? (currentSection.getAttribute("data-number") || "") : "";
        }
        if (!number || number === "0") {
            var incomplete = root.querySelector("[data-for=\"cm_completion\"].completion_incomplete");
            var incompleteSection = incomplete ? incomplete.closest(".courseindex-section") : null;
            number = incompleteSection ? (incompleteSection.getAttribute("data-number") || "") : number;
        }
        if (!number || number === "0") {
            var firstModule = root.querySelector(".courseindex-section[data-number]:not([data-number=\"0\"])");
            number = firstModule ? (firstModule.getAttribute("data-number") || "1") : "0";
        }

        return root.querySelector(".courseindex-section[data-number=\"" + number + "\"]");
    }

    function setupMundoInterCourseIndexTools(root, sections) {
        if (root.querySelector(".mundointer-courseindex-tools")) {
            return;
        }

        var tools = document.createElement("div");
        tools.className = "mundointer-courseindex-tools";

        var searchWrap = document.createElement("label");
        searchWrap.className = "mundointer-courseindex-search";
        searchWrap.setAttribute("aria-label", "Buscar módulo ou atividade");
        var searchIcon = document.createElement("span");
        searchIcon.setAttribute("aria-hidden", "true");
        searchIcon.textContent = "\u2315";
        var search = document.createElement("input");
        search.type = "search";
        search.placeholder = "Buscar módulo ou atividade";
        search.autocomplete = "off";
        searchWrap.appendChild(searchIcon);
        searchWrap.appendChild(search);

        var filters = document.createElement("div");
        filters.className = "mundointer-courseindex-filters";
        var filterDefinitions = [
            {key: "all", label: "Todos"},
            {key: "progress", label: "Em andamento"},
            {key: "complete", label: "Concluídos"}
        ];
        var activeFilter = "all";
        filterDefinitions.forEach(function(definition) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "mundointer-courseindex-filter" + (definition.key === "all" ? " is-active" : "");
            button.dataset.filter = definition.key;
            button.setAttribute("aria-pressed", definition.key === "all" ? "true" : "false");
            button.textContent = definition.label;
            filters.appendChild(button);
        });

        var result = document.createElement("div");
        result.className = "mundointer-courseindex-result";
        result.setAttribute("aria-live", "polite");

        function applyFilters() {
            var query = (search.value || "").trim().toLocaleLowerCase("pt-BR");
            var visibleModules = 0;
            var currentSection = findMundoInterCourseIndexSection(root);
            sections.forEach(function(section) {
                var number = section.getAttribute("data-number") || "";
                var completion = Array.prototype.slice.call(section.querySelectorAll("[data-for=\"cm_completion\"]"));
                var completeCount = completion.filter(function(item) {
                    return item.classList.contains("completion_complete");
                }).length;
                var isComplete = number !== "0" && completion.length > 0 && completeCount === completion.length;
                var isProgress = number !== "0" && !isComplete;
                var haystack = (section.textContent || "").replace(/\s+/g, " ").trim().toLocaleLowerCase("pt-BR");
                var matchesQuery = query === "" || haystack.indexOf(query) !== -1;
                var matchesFilter = activeFilter === "all"
                    || (activeFilter === "complete" && isComplete)
                    || (activeFilter === "progress" && isProgress);
                var visible = matchesQuery && matchesFilter;

                section.classList.toggle("mundointer-filter-hidden", !visible);
                if (visible && number !== "0") {
                    visibleModules += 1;
                }
                if (query !== "" && visible && number !== "0") {
                    if (!section.classList.contains("mundointer-courseindex-active")) {
                        section.dataset.mundointerSearchOpened = "1";
                    }
                    setMundoInterCourseIndexSection(section, true);
                } else if (query === "" && section.dataset.mundointerSearchOpened) {
                    delete section.dataset.mundointerSearchOpened;
                    setMundoInterCourseIndexSection(section, section === currentSection);
                }
            });
            result.textContent = visibleModules + (visibleModules === 1 ? " módulo" : " módulos");
        }

        search.addEventListener("input", applyFilters);
        filters.addEventListener("click", function(event) {
            var button = event.target.closest(".mundointer-courseindex-filter");
            if (!button) {
                return;
            }
            activeFilter = button.dataset.filter || "all";
            filters.querySelectorAll(".mundointer-courseindex-filter").forEach(function(candidate) {
                var selected = candidate === button;
                candidate.classList.toggle("is-active", selected);
                candidate.setAttribute("aria-pressed", selected ? "true" : "false");
            });
            applyFilters();
        });

        tools.appendChild(searchWrap);
        tools.appendChild(filters);
        tools.appendChild(result);
        root.insertBefore(tools, root.firstChild);
        applyFilters();
    }

    function setupMundoInterCourseIndex() {
        var root = document.querySelector("#course-index");
        if (!root) {
            return;
        }

        var sections = Array.prototype.slice.call(root.querySelectorAll(".courseindex-section[data-number]"));
        if (!sections.length) {
            return;
        }

        setupMundoInterCourseIndexTools(root, sections);

        if (!root.dataset.mundointerCourseIndexMounted) {
            root.dataset.mundointerCourseIndexMounted = "1";
            var activeSection = findMundoInterCourseIndexSection(root);
            sections.forEach(function(section) {
                setMundoInterCourseIndexSection(section, section === activeSection);
                var toggle = section.querySelector(".courseindex-section-title a[data-toggle=\"collapse\"]");
                if (!toggle || toggle.dataset.mundointerAccordion) {
                    return;
                }
                toggle.dataset.mundointerAccordion = "1";
                toggle.addEventListener("click", function() {
                    window.setTimeout(function() {
                        var opening = toggle.getAttribute("aria-expanded") === "true";
                        sections.forEach(function(otherSection) {
                            if (otherSection !== section && opening) {
                                setMundoInterCourseIndexSection(otherSection, false);
                            }
                        });
                        section.classList.toggle("mundointer-courseindex-active", opening);
                    }, 0);
                });
            });
        }

        if (!document.body.dataset.mundointerCourseIndexHash) {
            document.body.dataset.mundointerCourseIndexHash = "1";
            window.addEventListener("hashchange", function() {
                var currentRoot = document.querySelector("#course-index");
                if (!currentRoot) {
                    return;
                }
                var currentSection = findMundoInterCourseIndexSection(currentRoot);
                currentRoot.querySelectorAll(".courseindex-section[data-number]").forEach(function(section) {
                    setMundoInterCourseIndexSection(section, section === currentSection);
                });
            });
        }

        if (window.matchMedia("(max-width: 760px)").matches && !document.body.dataset.mundointerMobileIndexClosed) {
            document.body.dataset.mundointerMobileIndexClosed = "1";
            var drawer = document.getElementById("theme_boost-drawers-courseindex");
            var closeButton = drawer ? drawer.querySelector("[data-action=\"closedrawer\"]") : null;
            if (drawer && drawer.classList.contains("show") && closeButton) {
                closeButton.click();
            }
        }
    }

    function getMundoInterCourseStorageKey() {
        var courseClass = Array.prototype.find.call(document.body.classList, function(className) {
            return /^course-\d+$/.test(className);
        }) || "course-current";
        return "mundointer:lastActivity:" + courseClass;
    }

    function getMundoInterActivityItems(root) {
        return Array.prototype.slice.call(root.querySelectorAll("[data-for=\"cm\"]")).filter(function(item) {
            return Boolean(item.querySelector("a[data-for=\"cm_name\"]"));
        });
    }

    function rememberMundoInterActivity(link) {
        if (!link) {
            return;
        }
        try {
            window.localStorage.setItem(getMundoInterCourseStorageKey(), link.href || link.getAttribute("href") || "");
        } catch (error) {
            // Navegadores com armazenamento restrito continuam usando a primeira pendencia.
        }
    }

    function findMundoInterContinueLink(root) {
        var items = getMundoInterActivityItems(root);
        var storedHref = "";
        try {
            storedHref = window.localStorage.getItem(getMundoInterCourseStorageKey()) || "";
        } catch (error) {
            storedHref = "";
        }

        if (storedHref) {
            var storedItem = items.find(function(item) {
                var link = item.querySelector("a[data-for=\"cm_name\"]");
                return link && link.href === storedHref && !item.querySelector("[data-for=\"cm_completion\"].completion_complete");
            });
            if (storedItem) {
                return storedItem.querySelector("a[data-for=\"cm_name\"]");
            }
        }

        var incompleteItem = items.find(function(item) {
            return Boolean(item.querySelector("[data-for=\"cm_completion\"].completion_incomplete"));
        });
        return incompleteItem ? incompleteItem.querySelector("a[data-for=\"cm_name\"]") : null;
    }

    function createMundoInterActivityNavLink(link, direction) {
        if (!link) {
            return document.createElement("span");
        }
        var anchor = document.createElement("a");
        anchor.className = "mundointer-activity-nav-link is-" + direction;
        anchor.href = link.href || link.getAttribute("href") || "#";
        anchor.addEventListener("click", function() {
            rememberMundoInterActivity(link);
        });

        var icon = document.createElement("span");
        icon.className = "mundointer-activity-nav-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = direction === "previous" ? "\u2190" : "\u2192";

        var copy = document.createElement("span");
        copy.className = "mundointer-activity-nav-copy";
        var eyebrow = document.createElement("small");
        eyebrow.textContent = direction === "previous" ? "Atividade anterior" : "Pr\u00f3xima atividade";
        var title = document.createElement("strong");
        title.textContent = (link.textContent || "").trim();
        copy.appendChild(eyebrow);
        copy.appendChild(title);

        if (direction === "next") {
            anchor.appendChild(copy);
            anchor.appendChild(icon);
        } else {
            anchor.appendChild(icon);
            anchor.appendChild(copy);
        }
        return anchor;
    }

    function setupMundoInterLearningNavigation() {
        var root = document.querySelector("#course-index");
        if (!root) {
            return;
        }

        var items = getMundoInterActivityItems(root);
        items.forEach(function(item) {
            var link = item.querySelector("a[data-for=\"cm_name\"]");
            if (!link || link.dataset.mundointerRememberActivity) {
                return;
            }
            link.dataset.mundointerRememberActivity = "1";
            link.addEventListener("click", function() {
                rememberMundoInterActivity(link);
            });
        });

        var currentItem = root.querySelector("[data-for=\"cm\"].pageitem")
            || root.querySelector(".courseindex-item.pageitem");
        if (!currentItem || document.querySelector(".mundointer-activity-navigation")) {
            return;
        }

        var currentIndex = items.indexOf(currentItem);
        if (currentIndex < 0) {
            return;
        }
        rememberMundoInterActivity(currentItem.querySelector("a[data-for=\"cm_name\"]"));

        var courseLink = document.querySelector("a[href*=\"/course/view.php?id=\"]");
        var courseHref = courseLink ? courseLink.href : "";
        if (!courseHref) {
            var courseClass = Array.prototype.find.call(document.body.classList, function(className) {
                return /^course-\d+$/.test(className);
            }) || "";
            var courseId = courseClass.replace("course-", "");
            courseHref = courseId ? (window.location.origin + "/course/view.php?id=" + courseId) : "#";
        }

        var navigation = document.createElement("nav");
        navigation.className = "mundointer-activity-navigation";
        navigation.setAttribute("aria-label", "Navegacao entre atividades");
        navigation.appendChild(createMundoInterActivityNavLink(
            currentIndex > 0 ? items[currentIndex - 1].querySelector("a[data-for=\"cm_name\"]") : null,
            "previous"
        ));

        var modulesLink = document.createElement("a");
        modulesLink.className = "mundointer-back-to-modules";
        modulesLink.href = courseHref;
        modulesLink.textContent = "Voltar aos m\u00f3dulos";
        navigation.appendChild(modulesLink);

        navigation.appendChild(createMundoInterActivityNavLink(
            currentIndex < items.length - 1 ? items[currentIndex + 1].querySelector("a[data-for=\"cm_name\"]") : null,
            "next"
        ));

        var regionMain = document.querySelector("#region-main");
        if (!regionMain) {
            return;
        }
        var nativeNavigation = regionMain.querySelector(".activity-navigation");
        if (nativeNavigation) {
            nativeNavigation.insertAdjacentElement("beforebegin", navigation);
        } else {
            regionMain.appendChild(navigation);
        }
    }

    function enhanceMundoInterCourse() {
        setupMundoInterCourseIndex();
        setupMundoInterLearningNavigation();
        var tilesRoot = document.querySelector("#multi_section_tiles");
        if (!tilesRoot) {
            return;
        }

        if (!tilesRoot.dataset.mundointerDynamicSections) {
            tilesRoot.dataset.mundointerDynamicSections = "1";
            tilesRoot.addEventListener("click", function(event) {
                if (event.target.closest(".tile, .sectiontitlecontainer, .sectiontitle")) {
                    [120, 450, 1000].forEach(function(delay) {
                        window.setTimeout(enhanceMundoInterCourse, delay);
                    });
                }
            });
        }

        document.querySelectorAll("#multi_section_tiles.tiles > .tile.spacer .mundointer-module-foot").forEach(function(foot) {
            foot.remove();
        });

        document.querySelectorAll("#multi_section_tiles .section.state-visible .activity:not(.spacer)").forEach(function(activity) {
            var activityLink = activity.querySelector(".cm-link[href], a[href*=\"/mod/\"]");
            activity.classList.toggle("mundointer-content-label", !activityLink);
            var activityText = (activity.textContent || "").replace(/\\s+/g, " ").trim();
            var paragraphs = activity.querySelectorAll(".label_content p").length;
            activity.classList.toggle("mundointer-content-note", !activityLink && (activityText.length > 180 || paragraphs > 1));
        });

        document.querySelectorAll("#multi_section_tiles.tiles > .tile:not(.spacer) .tile-text h3").forEach(function(title) {
            var original = title.dataset.mundointerOriginalTitle || (title.textContent || "").trim();
            title.dataset.mundointerOriginalTitle = original;
            var normalized = typeof original.normalize === "function"
                ? original.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase()
                : original.toLowerCase();
            var tile = title.closest(".tile");
            if (tile) {
                tile.classList.toggle("mundointer-tile-book", normalized.indexOf("apostila") === 0 || normalized.indexOf("livro e materiais interativos") === 0);
                tile.classList.toggle("mundointer-tile-assessment", normalized.indexOf("avaliacao") === 0 || normalized.indexOf("avp - avaliacao") === 0);
            }
            var concise = original.replace(/^M[oó]dulo\s+\d+\s*[-–—:]\s*/i, "").trim();
            if (concise) {
                title.textContent = concise;
                title.setAttribute("aria-label", original);
            }
            var titleLength = (concise || original).length;
            title.classList.toggle("mundointer-module-title-medium", titleLength > 34 && titleLength <= 54);
            title.classList.toggle("mundointer-module-title-long", titleLength > 54);
        });

        var moduleSequence = 0;
        document.querySelectorAll("#multi_section_tiles.tiles > .tile:not(.spacer)").forEach(function(tile) {
            var tileTop = tile.querySelector(".tile-top");
            if (tile.classList.contains("mundointer-tile-book")) {
                if (tileTop) {
                    tileTop.dataset.mundointerNumber = "L";
                    tileTop.dataset.mundointerLabel = "LIVRO";
                }
            } else if (tile.classList.contains("mundointer-tile-assessment")) {
                if (tileTop) {
                    tileTop.dataset.mundointerNumber = "✓";
                    tileTop.dataset.mundointerLabel = "AVALIAÇÃO";
                }
            } else {
                moduleSequence++;
                if (tileTop) {
                    tileTop.dataset.mundointerNumber = String(moduleSequence).padStart(2, "0");
                    tileTop.dataset.mundointerLabel = "MÓDULO";
                }
            }
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
            var courseIndexRoot = document.querySelector("#course-index");
            var nextLink = courseIndexRoot ? findMundoInterContinueLink(courseIndexRoot) : null;
            var incompleteItem = nextLink ? nextLink.closest("[data-for=\"cm\"]") : null;
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
            moduleMeta.textContent = document.querySelectorAll("#multi_section_tiles.tiles > .tile:not(.spacer)").length + " módulos";
            meta.appendChild(activityMeta);
            meta.appendChild(moduleMeta);

            copy.appendChild(heading);
            copy.appendChild(progress);
            copy.appendChild(meta);
            overview.appendChild(copy);

            var continueButton = document.createElement(nextHref ? "a" : "span");
            continueButton.className = "mundointer-continue-button";
            continueButton.textContent = nextHref ? "Continuar de onde parou" : "Curso concluído";
            if (nextHref) {
                continueButton.setAttribute("href", nextHref);
                continueButton.addEventListener("click", function(event) {
                    rememberMundoInterActivity(event.currentTarget);
                });
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
        var mountedCourseIndex = document.querySelector("#course-index");
        var mountedNextLink = mountedCourseIndex ? findMundoInterContinueLink(mountedCourseIndex) : null;
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
                if ((mountedButton.textContent || "").trim() !== "Continuar de onde parou") {
                    mountedButton.textContent = "Continuar de onde parou";
                }
                if (mountedButton.getAttribute("href") !== mountedNextHref) {
                    mountedButton.setAttribute("href", mountedNextHref);
                }
                if (mountedNextTooltip && mountedButton.getAttribute("title") !== mountedNextTooltip) {
                    mountedButton.setAttribute("title", mountedNextTooltip);
                }
                if (!mountedButton.dataset.mundointerRememberActivity) {
                    mountedButton.dataset.mundointerRememberActivity = "1";
                    mountedButton.addEventListener("click", function(event) {
                        rememberMundoInterActivity(event.currentTarget);
                    });
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
        // O formato Tiles termina partes da montagem de forma assíncrona. Três
        // passagens pontuais cobrem essa inicialização sem observar cada mutação
        // do Moodle, evitando ciclos de renderização e travamentos no navegador.
        [400, 1200, 3000].forEach(function(delay) {
            window.setTimeout(enhanceMundoInterCourse, delay);
        });
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
            var faviconUrl = brand.getAttribute("data-favicon");
            var brandImage = brand.querySelector("img");
            if (faviconUrl && brandImage) {
                brandImage.src = faviconUrl;
                brandImage.removeAttribute("srcset");
            }
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
</script><script></script><script>
(function () {
  var brand = document.querySelector(".mundointer-theme-brand[data-franquia]");
  if (!brand) return;
  var isLogin = document.body.classList.contains("pagelayout-login") || document.body.id.indexOf("page-login-") === 0;
  var siteUrl = brand.getAttribute("data-site-url") || "";
  var brandName = brand.getAttribute("data-brand-name") || "";
  var supportEmail = brand.getAttribute("data-support-email") || "";
  var supportPhone = brand.getAttribute("data-support-phone") || "";
  var basePath = brand.getAttribute("data-moodle-base") || "";
  var digits = supportPhone.replace(/\D/g, "");
  var whatsapp = digits.length >= 10 ? "https://wa.me/55" + digits : "";

  if (isLogin && siteUrl && brand.getAttribute("data-login-back") === "1") {
    var loginContainer = document.querySelector(".login-container");
    if (loginContainer && !loginContainer.querySelector(".mundointer-back-site")) {
      var back = document.createElement("a");
      back.className = "mundointer-back-site";
      back.href = siteUrl;
      back.textContent = "Voltar ao site da franquia";
      back.style.cssText = "display:flex;justify-content:center;margin-top:.9rem;color:var(--mundointer-primary);font-weight:600;text-decoration:none;";
      loginContainer.appendChild(back);
    }
  }

document.addEventListener("click", function (event) {
    var link = event.target.closest && event.target.closest("a[href*=\"/login/logout.php\"]");
    if (!link) return;
    var activeBrand = document.querySelector(".mundointer-theme-brand[data-franquia]");
    var slug = activeBrand ? activeBrand.getAttribute("data-franquia") : "";
    if (!slug) return;
    event.preventDefault();
    window.location.href = basePath + "local/mundointer/logout.php?slug=" + encodeURIComponent(slug);
  });

function hideMundoInterCourseExtras() {

    document.querySelectorAll("#usermenu a, .dropdown-menu a, nav a, .moremenu a").forEach(function (link) {
      var label = (link.textContent || "").replace(/\s+/g, " ").trim();
      if (label === "Relatórios" || label === "Reports") {
        link.style.display = "none";
      }
    });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", hideMundoInterCourseExtras);
  } else {
    hideMundoInterCourseExtras();
  }
  [800, 2000].forEach(function (delay) { window.setTimeout(hideMundoInterCourseExtras, delay); });

  var isMyCourses = location.pathname.indexOf("/my/courses.php") !== -1;
  var mountMundoInterCoursesHero = function () {
    if (!isMyCourses || document.querySelector(".mundointer-mycourses-hero")) return;
    document.body.classList.add("mundointer-mycourses");
    document.querySelectorAll("#region-main h1, #region-main .page-context-header, #region-main .page-header-headings, #page-header h1").forEach(function (heading) {
      heading.style.display = "none";
    });

    var region = document.querySelector("#region-main") || document.querySelector(".drawercontent");
    if (region) {
      var hero = document.createElement("header");
      hero.className = "mundointer-mycourses-hero";
      var heroLogo = "";
      var logoUrl = brand.getAttribute("data-brand-logo");
      if (logoUrl) heroLogo = "<img src=\"" + logoUrl + "\" alt=\"\">";
      var studentName = brand.getAttribute("data-student-name") || "";
      var welcome = brand.getAttribute("data-welcome-text") || "";
      var siteUrl = brand.getAttribute("data-site-url") || "";
      var contacts = "";
      if (siteUrl) contacts += "<a href=\"" + siteUrl + "\" target=\"_blank\" rel=\"noopener\">Site da franquia</a>";
      hero.innerHTML = heroLogo
        + "<div class=\"mundointer-mycourses-copy\"><strong>" + brandName + "</strong>"
        + "<small>" + (studentName || welcome || "") + "</small></div>"
        + (contacts ? "<div class=\"mundointer-mycourses-contacts\">" + contacts + "</div>" : "");
      region.insertBefore(hero, region.firstChild);
    }
  };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mountMundoInterCoursesHero);
  } else {
    mountMundoInterCoursesHero();
  }
  [600, 1600].forEach(function (delay) { window.setTimeout(mountMundoInterCoursesHero, delay); });
  var isMyHome = location.pathname === "/my/" || location.pathname.indexOf("/my/index.php") !== -1;
  if (isMyHome) {
    try {
      if (!sessionStorage.getItem("mundointer-home-redirected")) {
        sessionStorage.setItem("mundointer-home-redirected", "1");
        window.location.replace(basePath + "my/courses.php");
      }
    } catch (error) {}
  }
  if (document.body.classList.contains("pagelayout-mydashboard") && brand.getAttribute("data-welcome") === "1" && !document.querySelector(".mundointer-welcome")) {
    var main = document.querySelector("#region-main") || document.querySelector(".drawercontent");
    if (main) {
      var banner = document.createElement("div");
      banner.className = "mundointer-welcome";
      var copy = document.createElement("div");
      var strong = document.createElement("strong");
      strong.textContent = brandName ? "Bem-vindo(a) à " + brandName + "!" : "Bem-vindo(a)!";
      var small = document.createElement("small");
      small.textContent = "Seus cursos e o suporte da sua franquia estão logo aqui.";
      copy.appendChild(strong);
      copy.appendChild(small);
      var actions = document.createElement("div");
      actions.className = "mundointer-welcome-actions";
      var inst2 = document.createElement("a");
      inst2.href = basePath + "local/mundointer/instituicao.php";
      inst2.textContent = "Minha instituição";
      actions.appendChild(inst2);
      if (siteUrl) {
        var siteLink = document.createElement("a");
        siteLink.href = siteUrl;
        siteLink.target = "_blank";
        siteLink.rel = "noopener";
        siteLink.textContent = "Site da franquia";
        actions.appendChild(siteLink);
      }
      banner.appendChild(copy);
      banner.appendChild(actions);
      main.insertBefore(banner, main.firstChild);
    }
  }
})();</script>';
}
