<?php
declare(strict_types=1);
/** @var Closure(mixed): string $escape */
?>
<div class="actions"><div><span class="status">Administração</span><h1>Perfis e permissões</h1></div><a href="<?= $escape($basePath) ?>/roles/create">Novo perfil</a></div>
<?php if ($message): ?><p class="alert" style="background:#eaf8ef;color:#176b3a"><?= $escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert"><?= $escape($error) ?></p><?php endif; ?>
<p>Alterações em um perfil passam a valer imediatamente para todos os usuários vinculados.</p>
<table><thead><tr><th>Perfil</th><th>Permissões</th><th>Usuários</th><th></th></tr></thead><tbody>
<?php foreach ($roles as $role): ?><tr><td><strong><?= $escape($role['name']) ?></strong><br><span class="meta"><?= $escape($role['code']) ?></span></td><td><?= $escape($role['permissions'] ?: '—') ?></td><td><?= $escape($role['user_count']) ?></td><td><a href="<?= $escape($basePath) ?>/roles/<?= $escape($role['id']) ?>/edit">Editar</a></td></tr><?php endforeach; ?>
</tbody></table>
<p><a href="<?= $escape($basePath) ?>/">Voltar ao painel</a></p>
