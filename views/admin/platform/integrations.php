<style>
.integration-hub{max-width:88rem;margin:0 auto}.integration-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:2rem;margin-bottom:1.35rem}.integration-hero h1{margin:.15rem 0 .35rem;font-size:clamp(2rem,4vw,3rem)}.integration-hero p{max-width:48rem;margin:0;color:var(--inter-muted)}.integration-count{display:flex;align-items:center;gap:.7rem;padding:.8rem 1rem;border:1px solid #dce4e9;border-radius:1rem;background:#fff;box-shadow:0 .45rem 1.25rem rgb(23 33 43 / 6%)}.integration-count i{display:grid;place-items:center;width:2.45rem;height:2.45rem;border-radius:.75rem;color:var(--inter-accent);background:#fff0f1}.integration-count strong,.integration-count small{display:block}.integration-count small{color:var(--inter-muted)}
.integration-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.integration-card{--integration-color:#ed1c24;--integration-soft:#fff0f1;position:relative;display:grid;grid-template-columns:auto minmax(0,1fr);grid-template-areas:"icon copy" "icon status";gap:.75rem 1rem;min-height:11.5rem;padding:1.35rem 4.6rem 1.35rem 1.4rem;color:var(--inter-ink);text-decoration:none;border:1px solid #dce4e9;border-radius:1.15rem;background:linear-gradient(145deg,#fff 55%,var(--integration-soft));box-shadow:0 .5rem 1.5rem rgb(23 33 43 / 6%);overflow:hidden;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}.integration-card::before{content:"";position:absolute;inset:0 auto 0 0;width:.32rem;background:var(--integration-color)}.integration-card:hover{color:var(--inter-ink);transform:translateY(-3px);border-color:color-mix(in srgb,var(--integration-color) 45%,#dce4e9);box-shadow:0 .85rem 2rem rgb(23 33 43 / 11%)}.integration-card.is-wide{grid-column:1/-1;min-height:10.5rem}.integration-icon{grid-area:icon;display:grid;place-items:center;width:3.7rem;height:3.7rem;border-radius:1rem;color:var(--integration-color);background:var(--integration-soft);font-size:1.55rem;box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--integration-color) 14%,transparent)}.integration-copy{grid-area:copy;align-self:start;min-width:0}.integration-kicker{display:block;margin-bottom:.25rem;color:var(--integration-color);font-size:.72rem;font-weight:850;letter-spacing:.08em;text-transform:uppercase}.integration-copy strong{display:block;margin-bottom:.35rem;font-size:1.32rem;overflow-wrap:anywhere}.integration-copy small{display:block;max-width:38rem;color:var(--inter-muted);font-size:.91rem;line-height:1.5}.integration-state{grid-area:status;align-self:end;justify-self:start;display:inline-flex;align-items:center;gap:.38rem;width:max-content;max-width:100%;padding:.32rem .62rem;border-radius:999px;font-size:.75rem;font-weight:800}.integration-state.is-ready{color:#087443;background:#dff7ea}.integration-state.is-pending{color:#8b5e00;background:#fff0c2}.integration-arrow{position:absolute;right:1.25rem;bottom:1.25rem;display:grid;place-items:center;width:2.5rem;height:2.5rem;border-radius:.75rem;color:var(--integration-color);background:#fff;border:1px solid color-mix(in srgb,var(--integration-color) 22%,#dce4e9)}
.integration-card.asaas{--integration-color:#1677ff;--integration-soft:#edf5ff}.integration-card.digitalocean{--integration-color:#0069ff;--integration-soft:#edf4ff}.integration-card.openai{--integration-color:#0f766e;--integration-soft:#e8f8f5}.integration-card.catalogs{--integration-color:#7c3aed;--integration-soft:#f3edff}.integration-card.ava{--integration-color:#e11d48;--integration-soft:#fff0f4}.integration-card.ava .integration-copy small{max-width:52rem}
@media(max-width:780px){.integration-hero{align-items:flex-start;flex-direction:column}.integration-grid{grid-template-columns:1fr}.integration-card.is-wide{grid-column:auto}.integration-card{min-height:0}}
</style>

<div class="integration-hub">
 <header class="integration-hero">
  <div><p class="eyebrow">ADM Central</p><h1>Integrações</h1><p>Serviços centrais que conectam pagamentos, arquivos, inteligência artificial, fornecedores de conteúdo e o ambiente acadêmico do Mundo Inter.</p></div>
  <div class="integration-count"><i class="fa-solid fa-plug-circle-bolt"></i><div><strong>5 integrações</strong><small>Gestão centralizada</small></div></div>
 </header>

 <section class="integration-grid" aria-label="Integrações do ADM Central">
  <a class="integration-card asaas" href="<?= $escape($basePath) ?>/admin/platform/integrations/asaas">
   <span class="integration-icon"><i class="fa-solid fa-wallet"></i></span>
   <span class="integration-copy"><span class="integration-kicker">Financeiro</span><strong>Asaas</strong><small>Cobranças das franquias, contratos, webhooks, mensalidades e split de pagamentos.</small></span>
   <span class="integration-state <?= $asaasConfigured&&$asaasActive?'is-ready':'is-pending' ?>"><i class="fa-solid <?= $asaasConfigured&&$asaasActive?'fa-circle-check':'fa-clock' ?>"></i><?= $asaasConfigured&&$asaasActive?'Ativa':'Configuração pendente' ?></span>
   <span class="integration-arrow"><i class="fa-solid fa-arrow-right"></i></span>
  </a>

  <a class="integration-card digitalocean" href="<?= $escape($basePath) ?>/admin/platform/integrations/digital-ocean">
   <span class="integration-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
   <span class="integration-copy"><span class="integration-kicker">Armazenamento</span><strong>DigitalOcean</strong><small>Spaces privado para arquivos do ADM Central, franquias, alunos, documentos e personalizações.</small></span>
   <span class="integration-state <?= $spacesConfigured&&$spacesActive?'is-ready':'is-pending' ?>"><i class="fa-solid <?= $spacesConfigured&&$spacesActive?'fa-circle-check':'fa-clock' ?>"></i><?= $spacesConfigured&&$spacesActive?'Ativa':'Configuração pendente' ?></span>
   <span class="integration-arrow"><i class="fa-solid fa-arrow-right"></i></span>
  </a>

  <a class="integration-card openai" href="<?= $escape($basePath) ?>/admin/platform/integrations/image-generation">
   <span class="integration-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
   <span class="integration-copy"><span class="integration-kicker">Inteligência artificial</span><strong>IA - OpenAI</strong><small>Geração assistida de capas comerciais, otimização automática e armazenamento definitivo no Spaces.</small></span>
   <span class="integration-state <?= $imageAiConfigured&&$imageAiActive?'is-ready':'is-pending' ?>"><i class="fa-solid <?= $imageAiConfigured&&$imageAiActive?'fa-circle-check':'fa-clock' ?>"></i><?= $imageAiConfigured&&$imageAiActive?'Ativa':'Configuração pendente' ?></span>
   <span class="integration-arrow"><i class="fa-solid fa-arrow-right"></i></span>
  </a>

  <a class="integration-card catalogs" href="<?= $escape($basePath) ?>/admin/platform/integrations/course-providers">
   <span class="integration-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
   <span class="integration-copy"><span class="integration-kicker">Conteúdo acadêmico</span><strong>Fornecedores/Catálogos</strong><small>Cada fornecedor concentra suas credenciais, Cursos individuais, Trilhas de origem, curadoria e política comercial.</small></span>
   <span class="integration-state <?= $courseProviderConfigured&&$courseProviderActive?'is-ready':'is-pending' ?>"><i class="fa-solid <?= $courseProviderConfigured&&$courseProviderActive?'fa-circle-check':'fa-clock' ?>"></i><?= $courseProviderConfigured&&$courseProviderActive?((int)$courseProviderCourses.' curso(s) ativos'):'Configuração pendente' ?></span>
   <span class="integration-arrow"><i class="fa-solid fa-arrow-right"></i></span>
  </a>

  <a class="integration-card ava is-wide" href="<?= $escape($basePath) ?>/admin/platform/painel-inter">
   <span class="integration-icon"><i class="fa-solid fa-graduation-cap"></i></span>
   <span class="integration-copy"><span class="integration-kicker">Ambiente acadêmico</span><strong>AVA Cursos</strong><small>Conexão Moodle, plugin oficial, identidades, saúde dos ambientes e Formações publicadas no AVA compartilhado.</small></span>
   <span class="integration-state <?= $avaConfigured&&$avaActive?'is-ready':'is-pending' ?>"><i class="fa-solid <?= $avaConfigured&&$avaActive?'fa-circle-check':'fa-clock' ?>"></i><?= $avaConfigured&&$avaActive?'Ativa':'Configuração pendente' ?></span>
   <span class="integration-arrow"><i class="fa-solid fa-arrow-right"></i></span>
  </a>
 </section>
</div>
