# Product Backlog — AutoRelatório Industrial

> **Responsável**: Scrum Master (Anti) & Equipe de Desenvolvimento (Brenda, Kauã, Amanda)  
> **Última Atualização**: Outubro/2026  
> **Status**: Em Execução da Sprint 1  

---

## 📌 Épicos do Projeto

1. **[EP01] Acesso, Autenticação e Perfis**
2. **[EP02] Gestão de Áreas Industriais e Equipamentos**
3. **[EP03] Módulo de Inspeção em Campo**
4. **[EP04] Registro de Problemas, Fotos e Anomalias**
5. **[EP05] Fluxo de Aprovação e Notificações**
6. **[EP06] Dashboard e Relatórios Executivos**

---

## 📋 Histórias de Usuário (User Stories)

### [EP01] Acesso, Autenticação e Perfis

#### **US01 - Cadastro de Usuário com Papéis por Matrícula**
* **Como** novo colaborador industrial,  
* **Eu quero** criar minha conta informando nome, matrícula, e-mail e senha,  
* **Para que** meu perfil de acesso (`funcionario`, `gerente` ou `supervisor`) seja atribuído automaticamente pela minha matrícula.
* **Critérios de Aceite**:
  - Matrícula com prefixo `f` atribui papel `funcionario`.
  - Matrícula com prefixo `g` atribui papel `gerente`.
  - Matrícula com prefixo `s` atribui papel `supervisor`.
  - Senha com no mínimo 6/8 caracteres e hash seguro no banco.
  - Retornar erro amigável se e-mail ou matrícula já estiverem cadastrados.
* **Prioridade**: Alta | **Estimativa**: 5 pts | **Status**: 🟢 Concluído

#### **US02 - Autenticação e Controle de Sessão**
* **Como** usuário cadastrado,  
* **Eu quero** efetuar login no sistema usando matrícula ou e-mail e senha,  
* **Para que** eu possa acessar os módulos permitidos para o meu nível de perfil.
* **Critérios de Aceite**:
  - Bloquear acesso a páginas restritas sem sessão ativa.
  - Redirecionar usuário conforme permissões (ex: gerente vê aprovações; funcionário vê novas inspeções).
* **Prioridade**: Alta | **Estimativa**: 5 pts | **Status**: 🟢 Concluído

#### **US03 - Gestão do Perfil e Troca de Senha**
* **Como** usuário logado,  
* **Eu quero** visualizar meus dados, atualizar minha foto de perfil e alterar minha senha,  
* **Para que** eu mantenha minhas informações de contato e segurança atualizadas.
* **Prioridade**: Média | **Estimativa**: 3 pts | **Status**: 🟡 Em andamento

---

### [EP02] Gestão de Áreas Industriais e Equipamentos

#### **US04 - Cadastro e Listagem de Áreas Industriais**
* **Como** supervisor ou gerente,  
* **Eu quero** cadastrar as áreas/setores da fábrica (ex: Laminação, Alto-Forno, Pátio),  
* **Para que** possamos categorizar os equipamentos e inspeções.
* **Prioridade**: Alta | **Estimativa**: 3 pts | **Status**: 🟢 Concluído

#### **US05 - Cadastro e Gestão de Equipamentos**
* **Como** supervisor ou gerente,  
* **Eu quero** registrar equipamentos vinculados a uma área, com número de patrimônio e localização,  
* **Para que** o time operacional saiba exatamente onde realizar as inspeções.
* **Prioridade**: Alta | **Estimativa**: 5 pts | **Status**: 🟢 Concluído

---

### [EP03] Módulo de Inspeção em Campo

#### **US06 - Registro de Nova Inspeção em Campo**
* **Como** funcionário de inspeção,  
* **Eu quero** iniciar uma inspeção selecionando área, equipamento e informando o perímetro e objetivo,  
* **Para que** o histórico de visitas e checagens preventivas fique registrado digitalmente.
* **Prioridade**: Alta | **Estimativa**: 5 pts | **Status**: 🟢 Concluído

#### **US07 - Consulta do Histórico de Inspeções**
* **Como** supervisor,  
* **Eu quero** filtrar e visualizar o histórico de inspeções realizadas por período, área ou equipamento,  
* **Para que** eu possa auditar a rotina preventiva da fábrica.
* **Prioridade**: Média | **Estimativa**: 3 pts | **Status**: 🟡 Em andamento

---

### [EP04] Registro de Problemas, Fotos e Anomalias

#### **US08 - Registro de Problema com Evidência Fotográfica**
* **Como** inspetor de campo,  
* **Eu quero** registrar anomalias detectadas durante a inspeção com título, descrição técnica, nível de criticidade e foto,  
* **Para que** a equipe de manutenção tenha clareza do problema encontrado.
* **Prioridade**: Alta | **Estimativa**: 8 pts | **Status**: 🟢 Concluído

---

### [EP05] Fluxo de Aprovação e Notificações

#### **US09 - Fluxo de Aprovação de Laudos pelo Gerente**
* **Como** gerente de manutenção,  
* **Eu quero** analisar os problemas reportados e aprovar ou reprovar o laudo com justificativa,  
* **Para que** apenas ordens de serviço validadas sejam encaminhadas para execução.
* **Prioridade**: Alta | **Estimativa**: 5 pts | **Status**: 🟡 Em andamento

#### **US10 - Sistema de Notificações do Usuário**
* **Como** usuário do sistema,  
* **Eu quero** receber notificações quando meus laudos forem aprovados/reprovados,  
* **Para que** eu seja informado rapidamente sobre atualizações críticas.
* **Prioridade**: Baixa | **Estimativa**: 3 pts | **Status**: 🟢 Concluído

---

### [EP06] Dashboard e Relatórios Executivos

#### **US11 - Painel de Indicadores (Dashboard)**
* **Como** gestor da planta,  
* **Eu quero** visualizar métricas em tempo real (total de inspeções, problemas críticos abertos, taxa de aprovação),  
* **Para que** eu tome decisões ágeis de segurança e manutenção.
* **Prioridade**: Média | **Estimativa**: 5 pts | **Status**: 🟢 Concluído
