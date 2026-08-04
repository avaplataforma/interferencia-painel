# Financeiro e Asaas

O módulo Financeiro centraliza clientes, cobranças e recebimentos por unidade,
respeitando os mesmos escopos do CRM e WhatsApp. A integração usa a API oficial
do Asaas.

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
- Recursos administrativos exigem `finance.settings.manage`; a consulta de clientes continua limitada por `finance.view` e pelo escopo de unidades.

## Diagnóstico do webhook

- A Integração Asaas mostra os últimos eventos, horário, cobrança relacionada, reentregas e falhas.
- O ID do evento é único; uma reentrega aumenta o contador, mas não reaplica o processamento.
- O teste interno registra e reentrega um evento sintético sem cliente ou cobrança real.
- URL e token possuem ação de cópia e permanecem restritos ao Administrador Global.
