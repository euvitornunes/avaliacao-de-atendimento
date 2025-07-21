
---

## 🛠️ Tecnologias Utilizadas

- PHP 7.4+
- MySQL/MariaDB
- HTML5 + CSS3
- JavaScript
- Apache (via XAMPP ou similar)

---

## 💾 Banco de Dados

- Nome: `avaliacao_saas`
- Importe o arquivo: [`avaliacao_saas.sql`](./avaliacao_saas.sql)

### Tabelas principais:

- `usuarios`: gerencia o login e dados dos administradores
- `empresas`: lista de empresas cadastradas no sistema (tenant)
- `funcionarios`: funcionários por empresa
- `avaliacoes`: registros de avaliações feitas por clientes

---

## 🚀 Como Rodar Localmente

1. Instale o [XAMPP](https://www.apachefriends.org/) ou similar
2. Copie os arquivos do projeto para a pasta `htdocs/avaliacao-saas`
3. Inicie Apache e MySQL no painel do XAMPP
4. Crie um banco com o nome `avaliacao_saas` e importe o arquivo `avaliacao_saas.sql`
5. Atualize as credenciais do banco em:  
   `config/database.php`
6. Acesse via navegador:  
   [http://localhost/avaliacao-saas/includes/login.php](http://localhost/avaliacao-saas/includes/login.php)

---

## 🔐 Acesso ao Painel

- Email: `farmacia@gmail.com`  
- Senha: `farmacia123`  
- Nível de acesso: administrador (nivel_acesso = 2)

---

## ✨ Funcionalidades

### 👤 Login e Registro
- Sistema com autenticação por e-mail e senha
- Criação de usuários com nível de acesso

### 🏢 Multi-Empresa
- Cada empresa tem seu próprio painel e usuários
- As telas públicas usam `tenant_id` para identificar a empresa

### 📊 Painel Admin (`/admin`)
- Dashboard com resumo
- Cadastro e listagem de funcionários
- Relatórios de avaliação por funcionário
- Perfil do usuário logado
- Filtros e exportação de relatórios (a desenvolver)

### 📝 Telas Públicas de Avaliação
- URLs como `screens/screen1.php?tenant_id=3`
- Cliente avalia o atendimento e pode deixar comentários

---

## 📦 Recursos Futuramente Planejados

- Exportação de relatórios em PDF
- Suporte a gráficos com Chart.js
- Painel de permissões por tipo de usuário
- Integração com notificações por e-mail
- Versão mobile aprimorada (PWA)

---

## 🧠 Sobre o Projeto

Este projeto foi idealizado para empresas locais que desejam acompanhar a satisfação dos seus clientes e incentivar seus colaboradores com base em dados reais de atendimento.

---

## 📃 Licença

Este projeto está licenciado sob a **MIT License** – sinta-se livre para usar, modificar e contribuir.
