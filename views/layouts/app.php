<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
/** @var string $content */
/** @var string $title */
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?= $escape($title) ?></title>
  <style>
    :root { color-scheme: light; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f5f7; color: #17212b; }
    main { width: min(48rem, calc(100% - 2rem)); background: #fff; border: 1px solid #dfe4e8; border-radius: 1rem; padding: 2rem; box-shadow: 0 1rem 3rem rgb(23 33 43 / 8%); }
    .status { display: inline-flex; align-items: center; gap: .5rem; color: #176b3a; font-weight: 700; }
    .status::before { content: ""; width: .7rem; height: .7rem; border-radius: 50%; background: #27a45d; }
    h1 { margin: .75rem 0; font-size: clamp(1.7rem, 5vw, 2.4rem); }
    p { color: #50606f; line-height: 1.6; }
    dl { display: grid; grid-template-columns: auto 1fr; gap: .65rem 1rem; margin: 1.5rem 0 0; }
    dt { color: #6a7783; } dd { margin: 0; font-weight: 600; }
    label { display: block; margin-top: 1rem; font-weight: 650; }
    input { box-sizing: border-box; width: 100%; margin-top: .4rem; padding: .8rem; border: 1px solid #bcc6ce; border-radius: .55rem; font: inherit; }
    button { margin-top: 1.25rem; padding: .8rem 1.1rem; border: 0; border-radius: .55rem; background: #176b3a; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    .alert { padding: .8rem 1rem; border-radius: .55rem; background: #fff0f0; color: #8c2020; }
    .meta { font-size: .92rem; color: #6a7783; }
  </style>
</head>
<body>
  <main><?= $content ?></main>
</body>
</html>
