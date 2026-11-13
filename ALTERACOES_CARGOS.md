# Remoção do cargo Supervisor

O cadastro não oferece mais Supervisor. Apenas Gerente pode aprovar relatórios e executar operações gerenciais.

## Contas já existentes

Antes de atualizar o sistema, faça backup do banco de dados em uso. Execute o arquivo `database/migrar_supervisores_para_gerentes.sql` no PostgreSQL ou SQLite realmente utilizado pela aplicação. Isso converte contas Supervisor para Gerente sem apagar usuários.

Se o sistema for aberto apenas pelo Live Server (porta 5500), os cadastros podem estar no armazenamento do navegador, e não no SQLite. Nessa situação, o script SQL não altera os registros locais: faça backup dos dados do navegador e migre os perfis existentes separadamente.

A pasta `.git` não foi incluída no pacote de distribuição.
