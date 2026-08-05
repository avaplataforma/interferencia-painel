# Integração Moodle

## Objetivo

O PAINEL INTER será o cadastro central de pessoas, alunos, contratos, atendimento e financeiro. O Moodle permanecerá responsável por cursos, acesso acadêmico, matrículas, progresso, notas e conclusão.

## Fase 1 — conferência somente leitura

- Token armazenado criptografado.
- Acesso administrativo exclusivo do perfil Admin System.
- Teste de conexão pelo serviço web oficial do Moodle.
- Importação em lotes de cursos, usuários matriculados e matrículas.
- Nenhuma escrita no Moodle nesta fase.
- Usuários importados permanecem em uma fila de conciliação até a confirmação do vínculo com um aluno financeiro.

## Identidade unificada

A correspondência automática futura seguirá esta prioridade:

1. CPF em campo institucional do Moodle;
2. vínculo previamente confirmado pelo ID do Moodle;
3. e-mail normalizado;
4. nome e telefone somente como sugestão para revisão manual.

Não haverá vínculo automático por nome isolado.

## Fases seguintes

1. Tela de conciliação Moodle × Alunos.
2. Mapeamento Cursos e preços × Cursos Moodle.
3. Criação ou atualização de usuário acadêmico.
4. Matrícula e suspensão controladas pelo Painel.
5. Consulta de progresso, notas e conclusão.

## Segurança

O serviço externo do Moodle deve conter somente as funções necessárias. O token deve pertencer a um usuário técnico dedicado, ter restrição de IP quando possível e nunca ser enviado por mensagens ou registrado em logs.
# Conciliação de alunos

O Moodle permanece como origem oficial de alunos e matrículas enquanto a integração está em validação. O PAINEL INTER importa os dados em modo somente leitura.

- CPF normalizado e único tem prioridade para o vínculo automático.
- E-mail normalizado e único é usado apenas quando não existe correspondência por CPF.
- Correspondências divergentes ou duplicadas ficam marcadas como conflito.
- Ausências ficam pendentes para revisão manual em **Alunos → Conciliação Moodle**.
- O vínculo manual registra o usuário revisor; nenhuma informação é escrita no Moodle.
