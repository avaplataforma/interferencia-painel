<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
?>
<div class="actions"><div><span class="status">Administração</span><h1>Usuários</h1></div><a href="<?= $escape($basePath) ?>/users/create">Novo usuário</a></div>
<?php if ($message): ?><p class="alert" style="background:#eaf8ef;color:#176b3a"><?= $escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert"><?= $escape($error) ?></p><?php endif; ?>
<table><thead><tr><th>Usuário</th><th>Papéis</th><th>Unidades</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($users as $item): ?><tr><td><strong><?= $escape($item['name']) ?></strong><?php if ((int) ($item['is_master'] ?? 0) === 1): ?><br><span class="meta">Gerido pela Central</span><?php endif; ?><br><span class="meta"><?= $escape($item['email']) ?></span></td><td><?= $escape($item['roles'] ?: '-') ?></td><td><?= $escape($item['unit_count']) ?></td><td><?= (int) $item['is_active'] === 1 ? 'Ativo' : 'Inativo' ?></td><td><?php if ((int) ($item['is_master'] ?? 0) === 1): ?><span class="meta">—</span><?php else: ?><a href="<?= $escape($basePath) ?>/users/<?= $escape($item['id']) ?>/edit">Editar</a><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table>
<p><a href="<?= $escape($basePath) ?>/">Voltar ao painel</a></p>
