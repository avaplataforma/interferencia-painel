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
- Visão central e filtros por escopo autorizado.

## Fase 4 — CRM

- Descoberta e validação do processo comercial.
- Contatos, oportunidades, etapas, atividades e histórico.
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

## Regra de passagem

Uma fase só é concluída com documentação atualizada, testes proporcionais ao
risco, migrações revisadas, critérios de aceite atendidos e plano de rollback.
