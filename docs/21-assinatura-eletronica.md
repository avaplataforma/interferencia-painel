# Assinatura eletrônica própria

## Objetivo e limite

O Mundo Inter terá um serviço próprio de assinatura eletrônica para contratos de
matrícula, termos, autorizações e documentos privados aceitos pelas partes. O
objetivo é reduzir custo por assinatura sem depender operacionalmente de uma
plataforma terceira.

O serviço não será apresentado como assinatura qualificada ICP-Brasil. Atos que
tenham exigência legal ou contratual de certificado ICP-Brasil, reconhecimento
específico ou formalidade incompatível continuarão em um provedor qualificado.
Os modelos contratuais e o fluxo deverão ser revisados por assessoria jurídica
antes da ativação em produção.

## Níveis previstos

### Aceite eletrônico simples

Para termos de baixo risco, após autenticação do usuário, com registro da versão
aceita e das evidências da sessão.

### Assinatura eletrônica com evidências reforçadas

Será o padrão inicial para contratos de matrícula:

- identificação do signatário e papel no contrato;
- CPF e dados de contato previamente validados;
- código de uso único enviado a canal verificado;
- confirmação expressa da intenção de assinar;
- associação exclusiva entre desafio, signatário e documento;
- hash criptográfico do documento antes e depois da assinatura;
- detecção de qualquer alteração posterior;
- trilha completa de eventos e pacote de evidências.

Esse fluxo buscará características de elevada confiabilidade, mas sua
classificação jurídica dependerá dos requisitos efetivamente implantados, do
documento e da aceitação pelas partes. O sistema não atribuirá automaticamente o
rótulo de assinatura avançada.

### Assinatura qualificada externa

Integração opcional futura com ICP-Brasil ou serviço oficialmente aceito para
documentos que exijam o nível mais elevado de confiança.

## Fluxo

```text
Modelo aprovado
  → Contrato gerado com dados congelados
  → PDF imutável e hash SHA-256
  → Convite individual ao signatário
  → Autenticação e código de uso único
  → Leitura, consentimentos e confirmação
  → Evento de assinatura
  → PDF final + relatório de evidências
  → Armazenamento privado e verificação pública limitada
```

O desenho ou imagem de uma rubrica poderá ser um elemento visual opcional, mas
jamais será a prova principal da assinatura.

## Conteúdo congelado

Antes do primeiro convite, o contrato deverá registrar uma fotografia de:

- organização, unidade e marca contratante;
- aluno, responsável legal e responsável financeiro;
- curso, fornecedor e entregas acadêmicas;
- preço, desconto, campanha, parcelas e forma de pagamento;
- duração, cancelamento, bolsa e demais condições;
- versões dos termos, política de privacidade e consentimentos;
- anexos que componham o instrumento.

Qualquer mudança material gera nova versão e exige novas assinaturas. Um
documento assinado nunca será sobrescrito.

## Signatários e menores de idade

- Cada signatário terá convite, autenticação, eventos e situação próprios.
- O contrato poderá exigir assinatura da organização, do aluno e do responsável.
- Para menor de idade, o vínculo e a qualificação do responsável legal serão
  obrigatórios conforme a regra contratual definida.
- O responsável financeiro poderá ser diferente do responsável legal.
- A conclusão ocorrerá apenas quando todos os papéis obrigatórios assinarem.
- Recusa, expiração, cancelamento e substituição serão registradas sem apagar a
  tentativa anterior.

## Evidências

O pacote de evidências conterá:

- ID público não sequencial do envelope e do documento;
- organização, modelo e versão contratual;
- hash do arquivo e dos anexos;
- nome, CPF parcialmente mascarado e papel do signatário;
- canal validado e resultado do desafio, sem guardar o código em texto claro;
- datas e horas em UTC, fuso apresentado e sequência dos eventos;
- IP, agente do navegador e dados técnicos proporcionais;
- textos de consentimento e intenção apresentados;
- versão do sistema e método de autenticação;
- resultado final, revogações e ocorrências;
- assinatura criptográfica do pacote pelo serviço Mundo Inter.

IP e localização nunca serão tratados isoladamente como prova de identidade.

## Verificação

O PDF final terá QR Code e código de verificação. A página pública exibirá apenas
o necessário para confirmar autenticidade, integridade, situação e signatários
mascarados, sem revelar contrato ou dados pessoais.

Usuários autorizados poderão baixar:

- documento final;
- relatório de evidências;
- anexos integrantes;
- histórico de versões e eventos.

## Segurança

- PDFs, evidências e anexos em armazenamento privado e versionado.
- Criptografia em trânsito e repouso.
- Chaves de assinatura fora do banco, com rotação e identificação da versão.
- Segredos e códigos armazenados somente como hash e com expiração curta.
- Convites de uso único, limitação de tentativas e proteção contra automação.
- Separação obrigatória por organização.
- Auditoria de visualização, download, envio, assinatura e cancelamento.
- Backup, teste de restauração e retenção compatível com obrigações contratuais.
- Carimbo de tempo confiável poderá ser incorporado posteriormente.

## Privacidade e comunicações

- Coletar apenas evidências necessárias e informar sua finalidade.
- Definir retenção específica para contratos e tentativas abandonadas.
- Não incluir códigos, CPF completo ou conteúdo contratual em logs comuns.
- E-mail e WhatsApp transportam o convite; a assinatura acontece em página HTTPS
  autenticada do Mundo Inter.
- Uma organização acessa somente seus envelopes e documentos.

## Entidades previstas

- `signature_templates`
- `signature_template_versions`
- `signature_envelopes`
- `signature_documents`
- `signature_parties`
- `signature_challenges`
- `signature_events`
- `signature_evidence_packages`

Os nomes são conceituais e serão confirmados na implementação.

## Implantação segura

1. Validar juridicamente os contratos, níveis de risco e papéis obrigatórios.
2. Criar modelos versionados e geração determinística do PDF.
3. Implantar envelopes, signatários, desafios e trilha imutável.
4. Criar relatório, QR Code e verificador de integridade.
5. Executar testes de adulteração, reuso, expiração e isolamento multitenant.
6. Rodar piloto interno sem substituir o processo vigente.
7. Ativar para uma organização e um tipo contratual aprovado.
8. Manter conector para assinatura qualificada quando necessária.
