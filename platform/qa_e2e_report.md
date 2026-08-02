# Relatório QA Sênior - Testes Fim a Fim (E2E) no Navegador

**Data da Execução:** Fevereiro de 2026
**Perfis Testados:** Administrador Global (`admin@admin.com`) e Administrador Institucional (`institution@email.com`)
**Ambiente Inicial:** Localhost (Conexão baseada em *SQLite* em memória com Seeders Iniciais)

O relatório abaixo sumariza os testes manuais e simulações feitas atuando como usuário final real, preenchendo formulários e operando a navegação como uma entidade humana validando as funcionalidades sob viés comportamental.

---

## 5. Resumo Executivo
*   **Total de telas testadas:** 10 (incluindo Modais nativos e validações)
*   **Total de casos executados:** > 30 interações
*   **Total de aprovações:** 22
*   **Total de falhas/Bugs:** 7 interações com quebra de fluxo
*   **Riscos Críticos Encontrados:** O sistema apresenta um bloqueio sistêmico na **edição de dados transversais** para a Instituição (Professores e Alunos), tornando impossível atualizar nomes, inativar acessos de terceiros ou mesclar turmas no momento atual.

---

## 1. Inventário de Telas Testadas

| Nome da Tela | Rota (URL Mapeada) | Objetivo Funcional | CRUD Encontrado |
| :--- | :--- | :--- | :--- |
| **Login** | `/login` | Porta de acesso e sessões ativas | Apenas Validação (Login) |
| **Painel Admin** | `/dashboard` | Resumo financeiro/SaaS (Redirecionava propositalmente) | N/A |
| **Planos (SaaS)** | `/admin/plans` | Controle financeiro corporativo do sistema | C, R, U, D |
| **Novo Plano** | `/admin/plans/create` | Inicialização de pacotes de vendas | Validações e C |
| **Configuração de Conta**| `/profile` | Dados do Admin / Senha | U, D |
| **Dashboard Instituição**| `/institution/dashboard`| Concentrador de Turmas/Provas do Diretor | R (Métricas) |
| **Professores** | `/institution/teachers` | Listagem e gestão de docentes da escola | C, R, U (Bloqueado), D |
| **Novo Professor**| Modais e `/create` | Entrada de staff e validações de input | C |
| **Turmas** | `/institution/classes` | Gestão de turmas e atribuições escolares | C, R, U |
| **Alunos** | `/institution/students` | Censo escolar do Locatário | R, U (Parcial) |

---

## 2. Matriz de Cobertura por Tela

| Tela | READ | CREATE | UPDATE | DELETE | Validações de Formulário | Status |
| :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **Planos** | ✅ | ✅ | ✅ | ✅ | ✅ (Preenchimento nativo browser) | **PASSED** |
| **Professores** | ✅ | ✅ | ❌ | ❌ | ✅ | **FAILED (UPDATE QUEBRADO)** |
| **Turmas** | ✅ | ✅ | ✅ | N/A | ⚠️ (Campo opcional forçado) | **PARTIAL PASSED** |
| **Alunos** | ✅ | N/A | ❌ | N/A | N/A | **FAILED (VINCULAÇÕES)** |
| **Configurações**| ✅ | N/A | ✅ | ✅ | ✅ | **PASSED** |

---

## 3. Lista de Bugs Encontrados (Priorizados)

| ID | Severidade | Título do Bug | Passo a Passo / Módulo |
| :--- | :--- | :--- | :--- |
| **B-01** | 🔴 **CRÍTICO** | **Update de Professor (Mass Assignment / Route Model)** | 1. Logar `institution@email.com`<br>2. Ir em Professores<br>3. Editar `QA_TESTE_Teacher`<br>4. Clicar em "Atualizar". <br> **Obs:** A página não persistiu a mudança devido a falhas no Request/Controller de update. |
| **B-02** | 🔴 **CRÍTICO** | **Erro na Associação Aluno-Turma (Pivot falho)** | 1. Ir em Alunos<br>2. Editar um aluno existente e marcar a Turma pre-existente.<br>3. Salvar.<br>**Obs:** A lista de alunos na tabela HTML continuará acusando que o aluno está em turma "Nenhuma" (Provável falha no `sync()` do model). |
| **B-03** | 🟠 **ALTO** | **Inativação (Soft Deletes) não funciona** | 1. Na Lista de Professores, clique em "Desativar".<br>2. Recarregue.<br>**Obs:** O professor continuará com badge "Ativo". |
| **B-04** | 🟡 **MÉDIO** | **Mensagens Toast Duplicadas (UX + Flash Sessions)** | 1. Crie / Edite / ou Exclua um Plano de Admin.<br>2. A View injeta dupla de validação de "Plano Excluído / Criado com sucesso". |
| **B-05** | 🟡 **MÉDIO** | **Validação Mentirosa em 'Ano Letivo'** | 1. Vá a Turmas.<br>2. Crie uma Turma deixando o Ano Letivo em branco (que na label possui `(Opcional)` ao lado).<br>3. Receba "The year field is required." no controller. |

---

## 4. Melhorias de UX Observadas

1. **Ausência de Modal:** Ao invés de usar modais estilizados, o botão de excluir/deletar um plano no painel SaaS ainda apela para a API nativa rudimentar do Windows/Chrome (`window.confirm()`).
2. **Posicionamento Perigoso:** Botões de `UPDATE` e `DELETE` em formato de ícone estão minúsculos e encostados na tabela do Admin, gerando alta propensão a cliques acidentais e destruição de pacotes de dados.
3. **Máscaras Limitadas:** O preenchimento monetário do plano ainda sofre para computar digitações sequenciais dinâmicas do usuário (se esperar R$ 99,00, a digitação de 99 pode causar travamentos por falta de libs como `imask` configuradas para live currency).
4. **Vínculo Transversal Inexistente:** Na criação de uma "Turma", não existe um combobox de "Selecione o Professor Líder" para já resolver a lógica matricial `Turma-Professor` de uma vez. O usuário tem de voltar, ir no professor e editar... Mas a edição também falhou.

---

## 6. Próximos Testes Recomendados

*   🛠 **Regressão Imediata:** Subida de Pull Request com as correções aos bugs do `Update` de Usuarios/Professores, re-executando o caso até alcançar o VERDE definitivo.
*   🔒 **Permissões Escalonadas:** Testes de intersecção ("IDOR" manual para validar se a Instituição A consegue modificar professores e acessar rotas injetando IDs da Instituição B na URL).
*   📱 **Responsividade Móvel (Viewports pequenos):** Disparar o web bot simulando tamanho de tela `375x667` para testar reflow das tabelas densas, menu hambúrguer e modals.
*   🚀 **Estresse / Performance:** Realocar milhares de instâncias falsas via factory para checar se a paginação nas classes relatórias funciona e a view não esgota memória do PHP.
