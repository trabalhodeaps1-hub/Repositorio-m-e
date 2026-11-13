# Dados iniciais de áreas e equipamentos

O banco `app.sqlite` já contém 3 áreas e 6 equipamentos. As 100 matrículas do pacote anterior foram preservadas.

O arquivo `back/database.php` também realiza uma carga inicial idempotente em bancos novos (SQLite ou PostgreSQL), sem duplicar patrimônios nem nomes de áreas. A carga não é reaplicada após a primeira execução.

Ao abrir o projeto no Live Server (porta 5500), `back/dados_iniciais.js` preenche `localStorage` na primeira visita à versão atualizada. Ele preserva registros já existentes. Isso é separado do banco SQLite; para dados centralizados, execute a aplicação com PHP.

## Situações
- AF-001: Operando; AF-002: Manutenção
- LTQ-001 e LTF-001: Operando
- PR-001: Parado; FP-002: Manutenção

O campo legado `status_operacional` é booleano e não distingue Parado de Manutenção. Foi adicionado `status_detalhado` para registrar a situação completa no banco.
