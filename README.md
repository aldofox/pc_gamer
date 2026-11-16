# 🎮 PC Gamer Store

![Status](https://img.shields.io/badge/Status-Em%20Desenvolvimento-orange)
![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/Licença-Sem%20licença-lightgrey)

Loja virtual de produtos gamer desenvolvida em **PHP + MySQL**, com foco em proporcionar uma experiência completa de e-commerce para venda de computadores, periféricos e acessórios gamers.

---

## 📋 Índice

- [Sobre o Projeto](#-sobre-o-projeto)
- [Funcionalidades](#-funcionalidades)
- [Tecnologias Utilizadas](#-tecnologias-utilizadas)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Área Administrativa](#-área-administrativa)
- [Banco de Dados](#-banco-de-dados)
- [Melhorias Futuras](#-melhorias-futuras)
- [Autor](#-autor)

---

## 🚀 Sobre o Projeto

A **PC Gamer Store** foi criada para simular um ambiente completo de e-commerce especializado em tecnologia gamer.

A plataforma permite gerenciar:

- PCs Gamers
- Notebooks
- Placas de Vídeo
- Monitores
- Periféricos
- Headsets
- Cadeiras Gamer
- Componentes e acessórios

O sistema foi desenvolvido utilizando **PHP puro**, com frontend em **HTML, CSS e JavaScript** e banco de dados **MySQL/MariaDB**.

---

## ✅ Funcionalidades

### Loja

- Catálogo de produtos
- Busca por produtos
- Produtos em destaque
- Produtos em promoção
- Banner promocional na página inicial
- Categorias de produtos
- Página de detalhes
- Sistema de avaliações
- Favoritos

### Carrinho e Checkout

- Carrinho de compras
- Atualização de quantidades
- Remoção de produtos
- Endereços de entrega
- Checkout completo
- Confirmação de pedidos

### Usuários

- Cadastro
- Login
- Recuperação de senha
- Área do cliente
- Histórico de pedidos

### Administração

- Dashboard administrativo
- Gestão de produtos
- Gestão de categorias
- Controle de estoque
- Gerenciamento de banners
- Gerenciamento de cupons
- Gerenciamento de pedidos
- Upload de imagens
- Logs administrativos

---

## 🛠 Tecnologias Utilizadas

### Backend

- PHP 8+
- PDO
- PHPMailer

### Banco de Dados

- MySQL
- MariaDB

### Frontend

- HTML5
- CSS3
- JavaScript
- Font Awesome
- Swiper.js

### Infraestrutura

- Apache
- XAMPP
- WAMP
- MAMP

---

## 📁 Estrutura do Projeto

```text
pc_gamer/
├── admin/
├── assets/
├── auth/
├── includes/
├── pages/
├── index.php
├── pc_gamer.sql
├── .htaccess
├── README.md
└── ...
```

---

## ⚙️ Instalação

### 1. Clonar o repositório

```bash
git clone https://github.com/aldofox/pc_gamer.git
```

### 2. Mover para o servidor local

**XAMPP**

```text
C:\xampp\htdocs\pc_gamer
```

**WAMP**

```text
C:\wamp64\www\pc_gamer
```

**Linux**

```text
/var/www/html/pc_gamer
```

### 3. Iniciar os serviços

Inicie:

- Apache
- MySQL

### 4. Importar banco de dados

Importe o arquivo:

```text
pc_gamer.sql
```

Utilizando:

- phpMyAdmin
- MySQL Workbench
- Linha de comando

---

## 🔧 Configuração

Abra o arquivo:

```php
includes/config.php
```

Configure os dados de conexão:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pc_gamer');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Configure também a URL do projeto:

```php
define('SITE_URL', 'http://localhost/pc_gamer/');
```

---

## 🔐 Área Administrativa

URL:

```text
http://localhost/pc_gamer/admin
```

Usuário padrão:

```text
Email: admin@loja.com
Senha: senha123
```

> Recomenda-se alterar as credenciais após a instalação.

---

## 🗄 Banco de Dados

O sistema utiliza o banco:

```text
pc_gamer
```

Principais tabelas:

- users
- categories
- products
- product_images
- reviews
- favorites
- banners
- carts
- cart_items
- addresses
- orders
- order_items
- payments
- admin_logs
- coupons

---

## 📈 Melhorias Futuras

- Integração com Mercado Pago
- Integração com PagSeguro
- Integração PIX automático
- Relatórios gerenciais
- Dashboard analítico
- Busca avançada
- API REST
- Arquitetura MVC
- Testes automatizados
- Implementação de Docker
- Implementação de Composer
- Migração para Laravel

---

## 📸 Capturas de Tela

Adicione imagens do projeto aqui:

```markdown
![Home](assets/screenshots/home.png)
![Produto](assets/screenshots/produto.png)
![Carrinho](assets/screenshots/carrinho.png)
```

---

## 🤝 Contribuição

Contribuições são bem-vindas.

1. Faça um Fork
2. Crie uma Branch

```bash
git checkout -b minha-feature
```

3. Faça suas alterações
4. Commit

```bash
git commit -m "Minha feature"
```

5. Push

```bash
git push origin minha-feature
```

6. Abra um Pull Request

---

## 📄 Licença

Este projeto não possui uma licença definida atualmente.

---

## 👨‍💻 Autor

**Aldo da Silveira**

GitHub:

🔗 https://github.com/aldofox

---

⭐ Se este projeto foi útil para você, deixe uma estrela no repositório.
