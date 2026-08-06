# Migração para o Mundo Inter multitenant

## Objetivo

Converter a operação atual na primeira organização sem interromper CRM,
financeiro, WhatsApp, AVA ou atendimento, e só depois permitir novas franquias.

## Etapas

### 1. Estabilização da nova VPS

- Concluir DNS, TLS, sincronização final e troca de produção.
- Validar backups, restauração, tarefas agendadas e integrações.
- Observar a operação antes de iniciar mudanças estruturais.

### 2. Fundação organizacional

- Criar organizações, domínios, marcas e vínculos de usuários.
- Criar a organização inicial e associar as seis unidades existentes.
- Adicionar contexto organizacional sem mudar o comportamento visível.

### 3. Isolamento dos módulos

- Propagar `organization_id` em identidade, CRM, alunos, financeiro, WhatsApp,
  tickets, documentos e integrações.
- Revisar índices, unicidade, chaves estrangeiras e auditoria.
- Criar testes negativos de acesso entre organizações.

### 4. Site e catálogo

- Separar o curso acadêmico do conteúdo comercial publicado.
- Implementar domínio, tema, páginas, unidades e catálogo.
- Integrar formulários públicos ao CRM com atribuição de origem.

### 5. Segunda organização piloto

- Criar uma organização sem dados reais e executar testes completos.
- Simular domínio, usuários, lead, aluno, pedido, cobrança, matrícula e AVA.
- Revisar logs para comprovar ausência de vazamento cruzado.

### 6. Primeira franquia real

- Importação assistida e plano de rollback.
- Ativação gradual dos módulos contratados.
- Monitoramento reforçado no período inicial.

## Restrições durante a migração

- Não misturar a troca de VPS com a primeira migração estrutural multitenant.
- Não abrir cadastro livre de organizações antes dos testes de isolamento.
- Não copiar credenciais entre organizações.
- Não usar domínio, nome ou unidade como substituto do ID da organização.
- Não executar transformações irreversíveis sem snapshot e rollback testado.

## Critério de conclusão

O modo multitenant estará pronto quando duas organizações de teste puderem
executar os mesmos fluxos e os testes demonstrarem que nenhuma delas consegue
listar, acessar, alterar, baixar ou inferir dados da outra.
