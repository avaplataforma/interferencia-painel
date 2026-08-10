# Site Institucional por franquia

O módulo separa autonomia editorial e governança estrutural.

## ADM Central

Na aba **Site** do cadastro da franquia, o ADM Central controla:

- liberação do módulo;
- modelo-base visual;
- formatos catálogo e loja;
- páginas personalizadas;
- limites de banners, páginas e cursos em destaque;
- domínio público definitivo, mantido na aba Painel.

O módulo nasce bloqueado. Nenhum site é publicado sem liberação central e publicação explícita pela franquia.

## ADM da franquia

Em **ADM > Site Institucional**, a franquia controla:

- formato autorizado e situação de publicação;
- textos da página inicial e apresentação institucional;
- cursos exibidos, vindos do catálogo financeiro autorizado;
- contatos e redes sociais;
- informações para buscadores;
- banners horizontais da vitrine, armazenados no Spaces da franquia;
- páginas institucionais adicionais e sua presença no menu;
- pedidos recentes iniciados pela loja virtual.

A identidade visual reaproveita logo, favicon e cores já aprovados pelo ADM Central.

## Catálogo e loja

No formato **catálogo**, cada curso encaminha o interessado ao WhatsApp da franquia com uma mensagem contextualizada.

No formato **loja virtual**, cada curso abre uma jornada pública de compra:

1. o visitante escolhe o curso e o polo;
2. informa dados pessoais e endereço de cobrança;
3. o Painel cria ou reutiliza o lead no CRM, respeitando CPF e unidade;
4. um pedido interno é gravado com referência única;
5. o checkout hospedado do Asaas recebe os dados e processa Pix ou cartão;
6. o webhook atualiza a situação do pedido sem considerar o retorno do navegador como confirmação financeira.

O fluxo segue a recomendação oficial do Asaas de conciliar o checkout por `externalReference` e acompanhar o resultado assíncrono por webhook.

## Endereços

- administração central: `https://mundointer.com.br/`;
- painel da franquia: `https://mundointer.com.br/{franquia}`;
- site provisório: `https://mundointer.com.br/{franquia}/site`;
- domínio próprio: apontará para a mesma aplicação após validação no ADM Central.

## Próximas extensões

- modelos-base adicionais;
- publicação por domínio próprio e diagnóstico de DNS/SSL;
- editor visual mais rico para páginas;
- transformação automática de pedidos pagos em matrículas.
