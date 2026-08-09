# Moodle local_mundointer

Conector oficial entre uma instalação Moodle e o ADM Central Mundo Inter.

## Instalação

1. Copie a pasta `local_mundointer` para `local/mundointer` na raiz do Moodle.
2. Acesse **Administração do site → Notificações** e conclua a instalação.
3. Use o serviço predefinido **Mundo Inter Connector** e associe um usuário técnico com a capacidade `local/mundointer:manage`.
4. Gere o token e salve-o apenas no ADM Central Mundo Inter.

Em uma instalação que já possua um serviço do Painel, é possível adicionar a função `local_mundointer_ping` ao serviço existente ou substituir o token pelo serviço predefinido completo.

## Identidade por franquia

O ADM Central envia ao plugin um catálogo de marcas pelo serviço `local_mundointer_sync_brands`. No AVA Cursos, o endereço curto `/franquia.php?slug=interferencia` aplica a marca antes do login. O endereço interno `/local/mundointer/entrar.php?franquia=slug` permanece como alternativa portável. Depois da autenticação, o valor do campo personalizado **Polo Presencial** confirma a franquia e mantém a identidade visual em todas as páginas do AVA.

A franquia selecionada também é preservada em um cookie próprio, seguro e sem dados pessoais. Assim, ao sair do Moodle, o usuário retorna à tela de entrada com a mesma identidade visual. Em um novo acesso autenticado, o **Polo Presencial** volta a ser a fonte principal e atualiza a marca quando necessário.

No tema Trema, a identidade substitui o logotipo global dentro do cartão de login, o favicon e o título da aba, além de usar o espaço nativo da barra superior. Nenhum arquivo do tema é alterado, preservando atualizações futuras do Trema. Em outros temas, o plugin mantém uma apresentação compatível de reserva.

Nesta primeira versão, o plugin expõe somente um diagnóstico autenticado de disponibilidade e versão. Os dados acadêmicos continuam usando as APIs oficiais do Moodle.
