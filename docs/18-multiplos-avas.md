# Múltiplos AVAs por organização

## Cenário

Uma organização poderá usar simultaneamente:

- um Moodle próprio e dedicado;
- o Moodle Central `avacursos.com.br` para cursos compartilhados/revendidos;
- somente o Moodle Central, quando não possuir AVA próprio;
- mais de um AVA dedicado no futuro, se houver necessidade contratual.

Por isso, a integração não será modelada como “o Moodle da organização”. O
Mundo Inter tratará cada Moodle como uma **plataforma de aprendizagem** e cada
organização terá autorização para usar uma ou mais plataformas.

## Tipos de plataforma

### AVA Central

- Endereço inicial: `https://avacursos.com.br`.
- Administrado pelo operador do Mundo Inter.
- Hospeda cursos que podem ser comercializados por várias organizações.
- Uma conta acadêmica poderá receber matrículas patrocinadas por organizações
  diferentes sem criar outro usuário para o mesmo CPF.
- Credencial técnica e monitoramento pertencem à plataforma, não são copiados
  para cada franquia.

### AVA dedicado

- Pertence a uma organização específica.
- Usa VPS, domínio, banco, arquivos e credencial técnica próprios.
- Pode conter cursos exclusivos daquela organização.
- Somente organizações explicitamente vinculadas poderão utilizá-lo.

## Modelo conceitual

```text
Pessoa
├── Conta no AVA Central
└── Conta no AVA dedicado da organização

Organização
├── AVA Central (autorizado)
└── AVA dedicado (opcional)

Curso canônico / produto comercial
├── Curso remoto no AVA Central
└── Curso remoto no AVA dedicado

Matrícula comercial
└── Entregas acadêmicas
    ├── acesso ao curso no AVA Central
    └── acesso ao curso no AVA dedicado
```

Uma matrícula comercial poderá gerar uma ou várias entregas acadêmicas. Cada
entrega registra plataforma, curso remoto, usuário remoto, situação, datas,
tentativas, erro e origem da liberação.

## Entidades previstas

- `learning_platforms`: cada Moodle conhecido pela plataforma.
- `organization_learning_platforms`: autorização e configuração de uso pela
  organização.
- `remote_courses`: cursos sincronizados de uma plataforma específica.
- `course_deliveries`: mapeamento entre oferta/produto e curso remoto.
- `learning_accounts`: vínculo da pessoa com sua conta em cada plataforma.
- `enrollment_deliveries`: acessos acadêmicos derivados de uma matrícula.
- `learning_sync_cursors`: controle de sincronização por plataforma.

Os nomes são conceituais e serão confirmados na migração. IDs do Moodle nunca
serão tratados como globais: sua unicidade será sempre composta pela plataforma.

## Regras de identidade

- O Cadastro do Mundo Inter continua sendo a identidade central da pessoa.
- CPF normalizado é o principal identificador de conciliação, mas não a chave
  técnica do banco.
- Uma pessoa tem no máximo uma conta por plataforma, salvo conflito registrado e
  revisado.
- No AVA Central, a conta existente deve ser reutilizada mesmo quando outra
  organização originar a nova matrícula.
- Em AVAs dedicados, a mesma pessoa poderá ter outra conta e outra senha.
- Login, URL e comunicação de acesso são gerados conforme a plataforma da
  entrega, nunca apenas conforme a organização.

## Cursos e ofertas

O curso comercial não pertence diretamente a um Moodle. Uma oferta define quais
entregas acadêmicas estão incluídas, por exemplo:

```text
Oferta: Formação Profissional Completa
├── Curso principal no Moodle próprio da franquia
└── Biblioteca complementar no avacursos.com.br
```

O Admin da organização escolhe apenas mapeamentos permitidos pelo contrato. O
Admin Mundo Inter controla quais cursos do AVA Central cada organização pode
revender, preços de referência, validade, limites e eventual repasse.

## Matrícula e liberação

1. O atendente escolhe aluno, oferta, campanha, unidade e condição comercial.
2. O sistema congela as entregas acadêmicas incluídas naquele contrato.
3. Após pagamento ou dispensa autorizada, cria uma entrega para cada AVA.
4. Cada entrega é processada de forma idempotente e independente.
5. Falha em um AVA não desfaz automaticamente o acesso já criado em outro.
6. A matrícula mostra um resumo e o detalhe de cada plataforma.
7. A mensagem ao aluno reúne os endereços e credenciais correspondentes.

## Suspensão e cancelamento

No AVA Central, o sistema deve suspender a **matrícula no curso**, e não o usuário
global, pois ele pode possuir outro curso ativo comprado por outra organização.
Uma conta só poderá ser suspensa globalmente quando não houver nenhuma entrega
ativa legítima naquela plataforma e a ação for autorizada pelo operador central.

No AVA dedicado, a suspensão global também verificará todas as matrículas ativas
da pessoa antes de bloquear a conta.

## Progresso e privacidade

- Progresso, notas, conclusão e último acesso pertencem à combinação
  plataforma + pessoa + curso remoto.
- A organização visualiza somente entregas que patrocinou ou para as quais
  recebeu autorização explícita.
- O uso do AVA Central não permite que uma franquia liste alunos, matrículas ou
  desempenho comercializados por outra.
- Administradores de franquias não recebem administração ampla do Moodle
  Central; a operação ocorre preferencialmente pelo PAINEL INTER.
- Coortes, categorias, campos institucionais e papéis restritos poderão apoiar a
  segregação no Moodle, mas a autorização principal permanece no Mundo Inter.

## Sincronização e resiliência

- Cada plataforma possui fila, cursor, limites e estado de saúde próprios.
- Tarefas carregam `organization_id` quando atuam em nome de uma organização e
  `learning_platform_id` obrigatoriamente.
- Eventos usam chave de idempotência por plataforma.
- Uma plataforma indisponível não bloqueia sincronizações das demais.
- O painel informa conexão, atraso da última sincronização e falhas por AVA.

## Migração do cenário atual

1. Registrar `avacursos.com.br` como AVA Central.
2. Vincular a organização Interferência ao AVA Central.
3. Converter integrações, cursos, usuários e matrículas atuais sem mudar IDs
   remotos.
4. Validar o fluxo atual após a conversão.
5. Cadastrar um AVA dedicado fictício e testar uma matrícula com duas entregas.
6. Somente depois vincular uma franquia externa real ao AVA Central.

## Fundação implementada no ADM Central

- `ava_connections` centraliza o AVA Cursos e os Moodles próprios sem expor tokens às franquias.
- `organization_ava_settings` define se cada franquia usa o AVA compartilhado, o próprio ou ambos.
- A aba **AVA** do cadastro da franquia concentra essa escolha e os testes de conexão.
- **ADM → Integrações → AVA Cursos** administra a credencial global de `avacursos.com.br`.
- **ADM → Integrações → Painel Inter** distribui o ZIP oficial do plugin, compara a versão instalada, monitora a disponibilidade de cada Moodle e preserva o histórico das verificações.
- A integração Moodle antiga é copiada para o registro compartilhado durante a migração, preservando o fluxo que já está em produção.

## Conector Moodle Mundo Inter

O código-fonte do plugin fica em `integrations/moodle/local_mundointer` e deve ser instalado em cada Moodle como `local/mundointer`. Apenas criar uma pasta `/painel` na raiz não registra capacidades, serviços web nem atualizações no Moodle.

## Identidade visual no AVA compartilhado

O AVA Cursos atende várias franquias sem duplicar os cursos. O plugin Mundo Inter recebe do ADM Central um catálogo de identidades visuais contendo marca, cores, logo, favicon e os valores de **Polo Presencial** vinculados a cada franquia.

- Antes da autenticação, cada franquia divulga seu endereço exclusivo no AVA Cursos: `/franquia.php?slug={codigo}`. O endereço interno do plugin continua disponível como alternativa.
- O endereço grava a marca na sessão e encaminha o visitante ao login padrão do Moodle.
- Depois da autenticação, o campo personalizado **Polo Presencial** prevalece sobre a sessão e confirma a identidade em todas as páginas internas.
- O mesmo curso e as mesmas atividades continuam compartilhados; somente a apresentação e o contexto da franquia mudam.
- O endereço genérico do Moodle continua disponível com a identidade padrão quando nenhuma franquia tiver sido identificada.

A sincronização é iniciada no ADM Central em **Integrações → Painel Inter → Identidades do AVA compartilhado**. Alterações de marca ou novos mapeamentos de polo devem ser sincronizados novamente.

A primeira versão fornece:

- serviço web autenticado para diagnóstico e versão;
- serviço predefinido com as funções acadêmicas usadas pelo Mundo Inter;
- identificador anônimo da instalação;
- configuração do endereço do ADM Central;
- base segura para futuras personalizações do painel, notas e experiência do aluno.
