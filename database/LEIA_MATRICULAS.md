# Matrículas e cargos

A tabela `matriculas_cargos` contém 100 matrículas autorizadas, de AM-00001 a AM-00100.
AM-00001 e as matrículas múltiplas de 5 (AM-00005, AM-00010, ... AM-00100) são Gerentes; as demais são Técnicos de Manutenção.

A tabela de matrículas **não cria contas**: cada pessoa ainda precisa cadastrar nome, e-mail e senha, usando sua matrícula. O backend valida a matrícula e define o cargo a partir da tabela, ignorando qualquer cargo enviado pelo navegador.

O arquivo SQLite incluído já tem a tabela preenchida. No PostgreSQL, a tabela é criada e preenchida automaticamente na inicialização do backend PHP; também há o script `matriculas_cargos.sql` para execução manual.

ATENÇÃO: se você abrir o sistema pelo Live Server (porta 5500), o navegador pode continuar usando `localStorage` sem autenticação segura no servidor. Para permissões reais, execute o PHP e desative o login/cadastro de contingência local. Matrículas por si só não comprovam a identidade de uma pessoa.
