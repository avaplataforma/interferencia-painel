# Financeiro e gateways de pagamento

O Asaas é o primeiro provedor e a integração principal da operação atual. O
modelo multitenant definitivo permitirá conexões diferentes por organização sem
alterar pedidos, matrículas e histórico financeiro. Consulte
`20-gateways-de-pagamento.md`.

## Liberação acadêmica após pagamento

Quando o webhook do Asaas confirmar uma cobrança vinculada a uma matrícula, o Painel tenta liberar automaticamente o aluno no AVA. O processo é idempotente: reutiliza usuários existentes por CPF ou e-mail, evita matrículas duplicadas e registra falhas como pendências sem interromper o recebimento do webhook financeiro.

A automação vale somente para matrículas criadas após a data de ativação registrada em `AVA_AUTO_RELEASE_FROM`. Matrículas anteriores permanecem no fluxo manual e não são processadas retroativamente.

O sininho informa acessos liberados que ainda aguardam o envio das credenciais e liberações automáticas que exigem revisão.

Após uma nova liberação, o Painel tenta enviar as instruções de acesso por e-mail e registra o resultado no histórico da matrícula. Falhas de comunicação não desfazem a matrícula no AVA e permanecem visíveis no sininho para reenvio manual.

## Permissões sobre cobranças

- Administrador Global e SEDE podem emitir, alterar e cancelar cobranças pendentes ou vencidas.
- Gestores e atendentes autorizados podem emitir cobranças e acessar PIX, fatura e segunda via, mas não podem alterar, cancelar ou dar baixa.
- A confirmação de pagamento é recebida pelo webhook oficial do Asaas; não existe baixa manual no fluxo operacional.
- Cobranças únicas e parceladas podem usar PIX, boleto ou cartão. No cartão, os dados sensíveis são preenchidos pelo cliente somente na página segura da fatura do Asaas.

A exclusão de clientes é exclusiva do Administrador Global e é bloqueada quando existem cobranças, assinaturas ou checkouts vinculados. Essa proteção evita a remoção indireta de recursos financeiros no Asaas.

O módulo Financeiro centraliza clientes, cobranças e recebimentos por unidade,
respeitando os mesmos escopos do CRM e WhatsApp. A primeira integração usa a API
oficial do Asaas.

## Implantação

1. Validar uma conta sandbox.
2. Importar clientes e cobranças existentes em modo de leitura.
3. Manter registros antigos sem unidade como **Legado/sem unidade**, visíveis
   somente a quem possuir `finance.legacy_view`.
4. Conciliar o legado com unidades e contatos do CRM sem associação automática
   por nome.
5. Habilitar gradualmente boleto, Pix, cartão, assinatura, link e checkout.
6. Configurar o `walletId` e a comissão de cada polo antes de ativar splits.

## Segurança e permissões

- `ASAAS_API_KEY` e `ASAAS_WEBHOOK_TOKEN` existem apenas no `.env` da VPS.
- A chave do Asaas também pode ser administrada pela tela protegida do ADM; nesse caso, fica criptografada no banco por uma chave-mestra mantida somente no `.env`.
- `ASAAS_ENVIRONMENT` separa sandbox e produção.
- O webhook autentica, registra o ID único do evento e ignora reentregas.
- `finance.view`: consulta limitada às unidades autorizadas.
- `finance.manage`: sincronização e futuras operações financeiras.
- `finance.legacy_view`: registros importados ainda sem unidade.

O Administrador Global recebe as três permissões. O Gestor recebe consulta por
padrão; o editor de perfis permitirá ampliar esse acesso.

## Próximas entregas

- Conciliação do legado.
- Cadastro financeiro vinculado ao CRM.
- Emissão e manutenção de cobranças.
- Checkout por curso e links de pagamento.
- Configuração e auditoria dos splits.
- Relatórios por polo e consolidado global.

## Conciliação do legado

- A listagem financeira permite buscar por nome, documento, e-mail, telefone ou ID Asaas.
- O detalhe reúne os dados do cliente e até 200 cobranças recentes, com acesso à fatura e ao boleto existentes.
- Apenas o Administrador Global pode atribuir um registro sem unidade a um polo.
- A vinculação opcional ao CRM sugere somente contatos com coincidência de documento, e-mail ou telefone.
- Ao conciliar, todas as cobranças locais daquele cliente passam para a mesma unidade; nenhuma alteração é enviada ao Asaas nessa etapa.

## Organização do menu

- O menu operacional **Financeiro** contém somente **Clientes** nesta fase.
- Credenciais, sincronização, webhook e diagnósticos ficam em **ADM → Integração Asaas**.
- Cursos, preços, formas aceitas e disponibilidade por unidade ficam em **ADM → Cursos e preços**.
- Recursos administrativos exigem `finance.settings.manage`; a consulta de clientes continua limitada por `finance.view` e pelo escopo de unidades.

## Emissão de cobranças

- A ficha do cliente conciliado permite preparar cobranças por PIX ou boleto.
- Unidade, cliente, valor, vencimento e descrição são obrigatórios.
- O envio real permanece bloqueado por padrão com `ASAAS_PAYMENTS_WRITE_ENABLED=false` até a cobrança-piloto.
- Clientes legados precisam ser conciliados antes da emissão.
- Para PIX, o painel recupera o QR Code dinâmico, o código copia-e-cola e a validade diretamente do Asaas.
- Cobranças pendentes ou vencidas podem ter forma, valor, vencimento e descrição alterados; também podem ser canceladas com confirmação explícita.
- Cobranças recebidas não podem ser alteradas nem canceladas por esse fluxo; eventual devolução será tratada separadamente como estorno.
- A central de cobranças consolida indicadores e permite busca e filtros por situação, forma e período, sempre respeitando a unidade ativa.
- A emissão aceita cobrança única ou parcelamento entre 2 e 60 vezes. No parcelamento, o operador informa o valor total e o Asaas calcula as parcelas mensais, compensando eventual diferença de centavos na última.
- Antes do envio é obrigatório revisar e confirmar cliente, unidade, valor, primeiro vencimento e condição de pagamento.

## Assinaturas recorrentes

- O Financeiro separa **Cobranças** e **Assinaturas** em navegação inspirada na ergonomia do Asaas.
- Assinaturas podem usar Pix ou boleto e ciclos semanal, quinzenal, mensal, bimestral, trimestral, semestral ou anual.
- A duração pode ser contínua, limitada por quantidade de cobranças ou por data final.
- Criar uma assinatura agenda cobranças futuras e não representa pagamento recebido.
- Pausar e reativar exigem permissão `finance.manage` e confirmação explícita.
- Cartão recorrente permanece fora desta etapa até a implantação de tokenização segura; o Painel nunca armazenará os dados brutos do cartão.
- Assinaturas já existentes no Asaas são importadas em lotes de até 100 registros, depois de clientes e cobranças.
- Assinaturas antigas sem unidade permanecem como legado e são visíveis somente ao Administrador Global.
- O progresso de clientes, cobranças e assinaturas fica disponível em **ADM → Integração Asaas** e pode ser reiniciado para uma conferência completa sem apagar dados locais.
- Eventos de assinatura recebidos pelo webhook atualizam o cadastro local de forma idempotente.

## Diagnóstico do webhook

- A Integração Asaas mostra os últimos eventos, horário, cobrança relacionada, reentregas e falhas.

## Checkout por curso

- Um cliente conciliado com uma unidade pode receber um checkout avulso criado a partir do catálogo administrativo.
- Usuários com `finance.manage` podem corrigir nome, e-mail, documento, telefones e endereço completo na ficha; a alteração é enviada ao Asaas e atualizada localmente.
- Nesta primeira etapa, o checkout aceita **Pix** e/ou **cartão de crédito**, conforme o curso.
- O link, a validade e a situação ficam registrados na ficha financeira do cliente.
- A página de retorno informa apenas a conclusão da navegação. Somente o webhook do Asaas pode atualizar a situação financeira.
- Parcelamento, recorrência e split serão habilitados em etapas posteriores, com regras explícitas por unidade.
- O ID do evento é único; uma reentrega aumenta o contador, mas não reaplica o processamento.
- O teste interno registra e reentrega um evento sintético sem cliente ou cobrança real.
- URL e token possuem ação de cópia e permanecem restritos ao Administrador Global.
