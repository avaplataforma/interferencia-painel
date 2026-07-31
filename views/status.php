<?php

declare(strict_types=1);

/** @var Closure(mixed): string $escape */
/** @var string $name */
/** @var string $environment */
/** @var string $basePath */
?>
<span class="status">Fundação operacional</span>
<h1><?= $escape($name) ?></h1>
<p>O núcleo técnico inicial está ativo. Os módulos de negócio ainda não foram habilitados.</p>
<dl>
  <dt>Ambiente</dt><dd><?= $escape($environment) ?></dd>
  <dt>Base</dt><dd><?= $escape($basePath) ?></dd>
  <dt>PHP</dt><dd>8.3+</dd>
</dl>

