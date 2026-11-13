# 🚀 Sprint 1: Fundação do Sistema & Sincronização da Equipe

> **Meta da Sprint (Sprint Goal)**:  
> Sincronizar o repositório da equipe (Brenda, Kauã, Amanda), garantir a conexão resiliente do banco e validar o fluxo completo de cadastro, inspeções e laudos.

---

### ⏱️ Informações Gerais
* **Período**: Sprint 1 (Ativa)
* **Scrum Master**: Anti (Antigravity AI)
* **Status Geral**: 🟢 Backend 100% Validado, Migrado para PostgreSQL Local com Fallback Resiliente

---

### 🎯 Status das Entregas

| ID | Funcionalidade | Responsáveis | Status |
| :--- | :--- | :---: | :---: |
| **US01** | Cadastro de usuário por matrícula (`f`, `g`, `s`) e cargo | Time | 🟢 Concluído |
| **US02** | Login e Controle de Sessão | Time | 🟢 Concluído |
| **US04** | Cadastro e listagem de Áreas Industriais | Time | 🟢 Concluído |
| **US05** | Gestão de Equipamentos | Time | 🟢 Concluído |
| **US06** | Registro de Nova Inspeção em Campo | Time | 🟢 Concluído |
| **US08** | Registro de Problema com Fotos | Time | 🟢 Concluído |
| **US09** | Fluxo de Aprovação de Laudos | Time | 🟢 Concluído |
| **US11** | Painel do Dashboard com Métricas | Time | 🟢 Concluído |

---

### 💬 Registro de Dailies (Standup Log)

#### Dia 1:
* **Realizado**: Conflitos de merge resolvidos; branch sincronizada com os commits remotos (`3aaee47`).
* **Correções Técnicas de Backend Aplicadas**:
  1. Corrigido erro fatal de sintaxe (Parse Error) no [`back/dashboard.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/dashboard.php).
  2. Removido texto fora da tag PHP no [`back/cadastrar_equip.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/cadastrar_equip.php).
  3. Corrigidas aspas duplas em literais SQL em [`back/inspecoes.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/inspecoes.php) e [`back/relatorio.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/relatorio.php).
  4. Otimizada conexão e compatibilidade com SQLite em [`back/database.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/database.php).
  5. Compatibilizado formulário de matrícula e de cargo em [`back/cadastro.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/cadastro.php).

#### Dia 2 (Migração e Padronização PostgreSQL Local):
* **Realizado**:
  1. Criada configuração centralizada em [`config/database.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/config/database.php) com PostgreSQL como banco primário (`127.0.0.1:5432`, db `autorelatorio`).
  2. Reescrito [`back/database.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/database.php) com auto-criação do banco `autorelatorio`, DDL das 7 tabelas e dados de seed inicial.
  3. Implementada função `db_last_insert_id()` em [`config/bootstrap.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/config/bootstrap.php) com suporte a sequences do PostgreSQL (`tabela_id_seq`).
  4. Atualizados todos os endpoints (`cadastro.php`, `inspecoes.php`, `relatorio.php`, `cadastrar_equip.php`, `cadastro_area.php`) para compatibilidade com PostgreSQL e booleans.
  5. Corrigido o bug de truncamento de fotos Base64 em [`back/relatorio.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/relatorio.php).
  6. Removido arquivo corrompido com erro de encoding (`conexo.php`) e cópia redundante (`cerro.php`).
  7. Criado utilitário [`iniciar_postgresql.bat`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/iniciar_postgresql.bat) e painel diagnóstico [`back/teste_conexao.php`](file:///c:/xampp/htdocs/trabalho/Repositorio-m-e/back/teste_conexao.php).
* **Impedimentos**: Nenhum. Todos os 12 arquivos PHP validados sem erros de sintaxe (100% de aprovação no linter).
