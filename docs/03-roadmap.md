# Roadmap

O roadmap orienta a ordem de construção, mas cada fase deverá ganhar critérios
de aceite e estimativas antes de começar.

## Sprint 0 — Fundação (atual)

- Estrutura inicial do repositório.
- Documentação do produto e arquitetura.
- Configuração de exemplo e disciplina de segredos.
- Base para Composer, Git, testes e migrações.

**Saída:** repositório compreensível e pronto para iniciar o kernel, sem módulos
de negócio.

## Fase 1 — Kernel

- Inicialização, configuração e tratamento de erros.
- Roteamento, requisição, resposta e templates.
- Conexão com banco e executor de migrações.
- Sessões, logs, validação e proteção CSRF.
- Ambiente de testes e qualidade automatizada.

## Fase 2 — Identidade e acesso

- Usuários, autenticação e recuperação segura de acesso.
- Papéis, permissões e escopo por unidade.
- Auditoria de ações sensíveis e gestão de sessões.

## Fase 3 — Unidades

- Cadastro das seis unidades e seus dados operacionais.
- Vínculos entre usuários, unidades e números.
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

