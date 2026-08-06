# Roadmap

O roadmap orienta a ordem de construção, mas cada fase deverá ganhar critérios
de aceite e estimativas antes de começar.

## Sprint 0 — Fundação (concluída)

- Estrutura inicial do repositório.
- Documentação do produto e arquitetura.
- Configuração de exemplo e disciplina de segredos.
- Base para Composer, Git, testes e migrações.

**Saída:** repositório compreensível e pronto para iniciar o kernel, sem módulos
de negócio.

## Fase 1 — Kernel (atual)

- [x] Inicialização, configuração, tratamento de erros e logs.
- [x] Página de estado da fundação.
- [x] Roteamento, requisição, resposta e templates iniciais.
- [x] Conexão com banco e executor de migrações.
- [x] Sessões, mensagens flash, validação e proteção CSRF.
- [x] Executor inicial de testes; ferramentas adicionais de qualidade pendentes.

## Fase 2 — Identidade e acesso

- [x] Usuários, login, logout e bloqueio inicial de tentativas.
- [x] Papéis, permissões e escopo por unidade.
- [x] Gestão de usuários, ativação, papéis e unidades permitidas.
- [x] Editor de perfis e suas permissões.
- [ ] Recuperação segura e segundo fator.
- [ ] Auditoria ampliada de ações sensíveis.

## Fase 3 — Unidades

- [x] Cadastro, edição e ativação das unidades e seus dados básicos.
- [x] Vínculos entre usuários e unidades.
- [ ] Vínculos entre unidades e números.
- [x] Seleção da unidade ativa limitada ao escopo autorizado.
- [ ] Filtros dos módulos de negócio pelo contexto da unidade ativa.

## Fase 4 — CRM

- [ ] Descoberta e validação completa do processo comercial.
- [x] Cadastro inicial de contatos por unidade, status, curso, interesse e polo.
- [ ] Oportunidades, etapas, atividades e histórico.
- Relatórios iniciais e integrações internas.

## Fase 5 — WhatsApp

- Configuração oficial dos ativos Meta e coexistência.
- Recebimento seguro de webhooks e processamento idempotente.
- Caixa de entrada, envio, templates e vinculação ao CRM.
- Métricas, alertas, consentimento e retenção.

## Fase 6 — Operação e escala

- Filas, tarefas agendadas e resiliência.
- Monitoramento, alertas, backup e recuperação testada.
- Otimização de desempenho e revisão de segurança.

## Fase financeira — gateways de pagamento

- [x] Fundação local, permissões por unidade e conexão protegida.
- [x] Sincronização inicial de clientes e cobranças antigas.
- [ ] Conciliação do legado com unidades e contatos do CRM.
- [ ] Emissão, assinaturas, links e checkout por curso.
- [ ] Split e relatórios por polo.
- [x] Definir abstração multigateway e conexões por organização.
- [ ] Encapsular o Asaas como primeiro conector financeiro.
- [ ] Tornar clientes, cobranças, tentativas e eventos independentes do gateway.
- [ ] Validar capacidades, roteamento e troca com um conector fictício.
- [ ] Integrar um segundo gateway em sandbox antes de qualquer piloto real.

## Regra de passagem

Uma fase só é concluída com documentação atualizada, testes proporcionais ao
risco, migrações revisadas, critérios de aceite atendidos e plano de rollback.

## Fase Mundo Inter — estabilização e multitenancy

- [x] Definir visão do ecossistema, hierarquia e domínios centrais.
- [x] Definir estratégia inicial de isolamento por organização.
- [x] Definir arquitetura de sites, catálogo e loja das franquias.
- [x] Definir estratégia de documentos em armazenamento privado compatível com
  S3.
- [ ] Concluir migração da produção atual para a VPS exclusiva.
- [ ] Criar organização, domínios e vínculos organizacionais.
- [ ] Converter a operação atual na primeira organização.
- [ ] Propagar `organization_id` pelos módulos com testes de acesso cruzado.
- [ ] Validar uma segunda organização somente com dados de teste.

## Fase Sites — presença institucional e catálogo

- [ ] Identidade visual, domínio e tema por organização.
- [ ] Páginas institucionais, unidades, contato, privacidade e SEO.
- [ ] Catálogo e página comercial de curso usando a fonte canônica do painel.
- [ ] Formulário de interesse integrado ao CRM, com campanha, origem e UTM.
- [ ] Pré-visualização, rascunho, publicação e auditoria.
- [ ] Checkout do gateway configurado integrado a pedido, matrícula e AVA.
- [ ] Carrinho, recuperação de abandono e split em fase posterior.

## Fase Catálogos — fornecedores e distribuição

- [x] Definir separação entre fornecedor, curso canônico, oferta e publicação.
- [x] Definir licenciamento seletivo de catálogos por organização.
- [ ] Criar cadastro de fornecedores, contratos e conexões.
- [ ] Criar área de importação, curadoria e deduplicação.
- [ ] Criar catálogo canônico e mapeamento das entregas acadêmicas.
- [ ] Criar licenças e regras comerciais por organização.
- [ ] Criar ofertas e publicação seletiva nas vitrines dos sites.
- [ ] Testar dois fornecedores e duas organizações usando dados fictícios.

## Fase AVA — múltiplas plataformas

- [x] Definir AVA Central compartilhado e AVAs dedicados por organização.
- [x] Definir matrícula comercial com uma ou mais entregas acadêmicas.
- [ ] Criar cadastro de plataformas e autorização por organização.
- [ ] Converter `avacursos.com.br` na primeira plataforma central.
- [ ] Tornar IDs, sincronizações e saúde dependentes da plataforma.
- [ ] Mapear ofertas para cursos remotos em um ou mais AVAs.
- [ ] Alterar suspensão global para suspensão segura por matrícula/curso.
- [ ] Testar uma organização fictícia usando AVA Central e AVA dedicado.

## Fase Contratos — assinatura eletrônica

- [x] Definir níveis, limites e pacote de evidências do serviço próprio.
- [ ] Validar juridicamente modelos, papéis, retenção e critérios de uso.
- [ ] Criar modelos e versões imutáveis de contratos e termos.
- [ ] Criar envelopes, múltiplos signatários e autenticação por código único.
- [ ] Gerar PDF final, relatório de evidências, QR Code e verificador.
- [ ] Cobrir aluno menor, responsável legal e responsável financeiro.
- [ ] Testar adulteração, expiração, reuso e isolamento entre organizações.
- [ ] Executar piloto interno antes de substituir o processo atual.
