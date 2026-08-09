# Moodle local_mundointer

Conector oficial entre uma instalação Moodle e o ADM Central Mundo Inter.

## Instalação

1. Copie a pasta `local_mundointer` para `local/mundointer` na raiz do Moodle.
2. Acesse **Administração do site → Notificações** e conclua a instalação.
3. Use o serviço predefinido **Mundo Inter Connector** e associe um usuário técnico com a capacidade `local/mundointer:manage`.
4. Gere o token e salve-o apenas no ADM Central Mundo Inter.

Em uma instalação que já possua um serviço do Painel, é possível adicionar a função `local_mundointer_ping` ao serviço existente ou substituir o token pelo serviço predefinido completo.

## Identidade por franquia

O ADM Central envia ao plugin um catálogo de marcas pelo serviço `local_mundointer_sync_brands`. O endereço `/local/mundointer/entrar.php?franquia=slug` aplica a marca antes do login. Depois da autenticação, o valor do campo personalizado **Polo Presencial** confirma a franquia e mantém a identidade visual em todas as páginas do AVA.

Nesta primeira versão, o plugin expõe somente um diagnóstico autenticado de disponibilidade e versão. Os dados acadêmicos continuam usando as APIs oficiais do Moodle.
