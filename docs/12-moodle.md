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

## Fluxo de matrícula

1. O aluno possui um único Cadastro no Painel.
2. A Matrícula vincula esse aluno a um curso previamente sincronizado do Moodle.
3. O curso e preço financeiro orienta a cobrança emitida pelo Asaas.
4. Após a confirmação financeira, o acesso acadêmico poderá ser liberado no Moodle.
5. Login e instruções serão enviados por canal seguro; senhas permanentes nunca serão exibidas ou armazenadas em texto simples.

Cursos novos são trazidos pelo botão **Sincronizar cursos**, usando a integração oficial somente leitura enquanto a escrita no Moodle não estiver habilitada.

## Segurança

O serviço externo do Moodle deve conter somente as funções necessárias. O token deve pertencer a um usuário técnico dedicado, ter restrição de IP quando possível e nunca ser enviado por mensagens ou registrado em logs.

### Login e senha inicial

- Novas contas criadas pelo Painel usam o CPF, somente com números, como login no AVA.
- O Admin System escolhe entre senha automática gerada pelo AVA ou os cinco primeiros dígitos do CPF.
- A opção automática é o padrão recomendado e delega ao AVA o envio seguro das instruções.
- Quando for escolhida a senha baseada no CPF, ela permanece válida sem troca obrigatória; a política de senhas do Moodle continua prevalecendo.
- A configuração afeta apenas novas contas; usuários existentes não têm suas senhas alteradas.

### Comunicação do acesso

- Após a liberação, a matrícula oferece uma mensagem padronizada com endereço do AVA, login, curso e Unidade.
- O atendente pode copiar a mensagem ou abrir sua composição no WhatsApp e no e-mail.
- Cada abertura de canal é registrada com data, destino e usuário responsável, sem afirmar entrega que ainda não foi confirmada pelo provedor.
- O acesso pode ser reenviado e todo o movimento permanece no histórico da matrícula.
# Conciliação de alunos

O Moodle permanece como origem oficial de alunos e matrículas enquanto a integração está em validação. O PAINEL INTER importa os dados em modo somente leitura.

- CPF normalizado e único tem prioridade para o vínculo automático.
- E-mail normalizado e único é usado apenas quando não existe correspondência por CPF.
- Correspondências divergentes ou duplicadas ficam marcadas como conflito.
- Ausências ficam pendentes para revisão manual em **Alunos → Conciliação Moodle**.
- O vínculo manual registra o usuário revisor; nenhuma informação é escrita no Moodle.

## Campos complementares

- Campos personalizados ficam separados como dados acadêmicos complementares e não se tornam obrigatórios por importação.
- O Admin System define quais campos aparecem, quais são ignorados e o significado de cada um no Painel.
- O mapeamento não sobrescreve automaticamente dados principais do Painel ou informações financeiras do Asaas.
- Campos vazios e campos marcados para ignorar não aparecem na ficha do aluno.
