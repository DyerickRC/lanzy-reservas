# Lanzy — Sistema de Reservas de Computadores e Video Games

Sistema simples de reservas para lan house / gamer house. Front-end em HTML/CSS/JS + Bootstrap 5, back-end em PHP puro (PDO), banco de dados MySQL.

## Estrutura

```
lanzy-reservas/
├── admin/              # Painel administrativo (gerenciar estações e ver reservas)
├── assets/css/style.css
├── assets/js/script.js
├── config/database.php # Conexão PDO com o MySQL
├── database/schema.sql # Script de criação do banco + dados de exemplo
├── includes/           # header.php, footer.php, auth.php
├── index.php
├── login.php / cadastro.php / logout.php
├── dashboard.php       # Lista de PCs e video games disponíveis
├── reservar.php / processa_reserva.php
├── minhas-reservas.php / cancelar_reserva.php
```

## Como rodar (XAMPP / WampServer / Laragon)

1. Copie a pasta `lanzy-reservas` para a pasta pública do seu servidor (ex.: `htdocs` no XAMPP).
2. Crie o banco de dados abrindo o phpMyAdmin (ou o cliente MySQL de sua preferência) e importe o arquivo `database/schema.sql`. Isso cria o banco `lanzy_reservas`, as tabelas e alguns dados de exemplo.
3. Por padrão o sistema conecta com `root` sem senha (padrão do XAMPP). Se o MySQL do seu computador tiver senha, copie `config/local.exemplo.php` para `config/local.php` e coloque a senha lá. Não é preciso mexer em `config/database.php`.
4. Acesse `http://localhost/lanzy-reservas/` no navegador.

### Já tinha o banco da versão anterior?

Rode uma vez, nesta ordem, `database/atualizacao_v2.sql` e `database/atualizacao_v3.sql` (pelo phpMyAdmin, aba **Importar**, ou pela aba **SQL**). Elas adicionam as colunas `marca` (estações), `bloqueado` (usuários) e `valor_total` (reservas) e o status "inativa" das estações, sem criar tabelas novas.

## Segurança

- Todo formulário envia um token CSRF; pedidos sem ele são recusados.
- Cookie de sessão com `HttpOnly` e `SameSite=Lax`, e a sessão é renovada no login.
- Erros técnicos não aparecem para o usuário (vão para o log do Apache). Para vê-los enquanto programa, coloque `'mostrar_erros' => true` no `config/local.php`.
- Estações com histórico de reservas são desativadas em vez de excluídas, para não perder o faturamento.

## Como rodar com o servidor embutido do PHP (sem Apache)

```bash
php -S localhost:8000
```

E acesse `http://localhost:8000/`.

## Login de administrador padrão

- **E-mail:** admin@lanzy.com
- **Senha:** admin123

Use essa conta para acessar o Painel Admin (menu superior) e cadastrar/gerenciar as estações (computadores e video games).

## Funcionalidades

- Cadastro e login de clientes (senha com hash bcrypt).
- Listagem de estações (computadores e video games) com filtro por tipo.
- Reserva em horas cheias (grade de horas para o início, lista para o fim), com bloqueio de conflito, inclusive reservas que passam da meia-noite.
- Corujão: sessões de 8 a 12 horas com desconto (8h: 10%, 10h: 15%, 12h: 20%).
- Página "Minhas Reservas" para o cliente ver e cancelar suas reservas.
- Painel administrativo: ver e cancelar reservas, cadastrar/editar estações (nome, marca, valor, configuração), colocar em manutenção, excluir e bloquear/desbloquear clientes (bloquear cancela as reservas futuras do cliente).

## Próximos passos sugeridos

- Recuperação de senha por e-mail.
- Paginação nas listagens quando o volume de dados crescer.
