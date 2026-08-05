<header class="section-heading"><div><span class="status">ADM</span><h1>Integrações</h1><p class="meta">Conexões externas e sincronizações do PAINEL INTER.</p></div></header>
<section class="admin-links integrations-grid">
  <?php if($navigation['finance_settings']??false):?><a class="admin-link" href="<?= $escape($basePath) ?>/admin/integrations/asaas"><span class="quick-icon"><i class="fa-solid fa-wallet"></i></span><span><strong>Asaas</strong><small>Clientes, cobranças, webhooks e sincronização financeira</small></span><i class="fa-solid fa-chevron-right"></i></a><?php endif;?>
  <?php if($navigation['moodle_settings']??false):?><a class="admin-link" href="<?= $escape($basePath) ?>/admin/integrations/moodle"><span class="quick-icon"><i class="fa-solid fa-graduation-cap"></i></span><span><strong>Moodle</strong><small>Cursos, usuários, matrículas e campos acadêmicos</small></span><i class="fa-solid fa-chevron-right"></i></a><?php endif;?>
</section>
