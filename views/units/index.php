<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
?>
<div class="actions"><div><span class="status">Administração</span><h1>Unidades</h1></div><a href="<?= $escape($basePath) ?>/units/create">Nova unidade</a></div>
<?php if ($message): ?><p class="alert" style="background:#eaf8ef;color:#176b3a"><?= $escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert"><?= $escape($error) ?></p><?php endif; ?>
<table><thead><tr><th>Unidade</th><th>Cidade</th><th>Código interno</th><th>Usuários</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($units as $unit): ?><tr><td><strong><?= $escape($unit['name']) ?></strong></td><td><?= $escape($unit['city']) ?></td><td><span class="meta"><?= $escape($unit['code']) ?></span></td><td><?= $escape($unit['user_count']) ?></td><td><?= (int) $unit['is_active'] === 1 ? 'Ativa' : 'Inativa' ?></td><td><a href="<?= $escape($basePath) ?>/units/<?= $escape($unit['id']) ?>/edit">Editar</a></td></tr><?php endforeach; ?>
</tbody></table>
<p><a href="<?= $escape($basePath) ?>/">Voltar ao painel</a></p>
