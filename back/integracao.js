// Integração entre as telas do Frontend (HTML) e as APIs do Backend (PHP / Armazenamento Local Estrito)

(() => {
    // Detecta o caminho base relativo para a pasta 'back' dependendo de onde o HTML está sendo executado
    const isInsideBack = location.pathname.includes('/back/');
    const BACK_URL = isInsideBack ? './' : 'back/';

    const ENDPOINTS = {
        login: BACK_URL + 'login.php',
        cadastro: BACK_URL + 'cadastro.php',
        area: BACK_URL + 'cadastro_area.php',
        equipamento: BACK_URL + 'cadastrar_equip.php',
        inspecao: BACK_URL + 'inspecoes.php',
        upload: BACK_URL + 'imagem.php',
        dashboard: BACK_URL + 'dashboard.php'
    };

    const $ = (seletor, raiz = document) => raiz.querySelector(seletor);

    /**
     * Limpa contas antigas fictícias/pré-fabricadas do navegador para garantir
     * que APENAS contas criadas na etapa 'Criar Conta' possam ser utilizadas.
     */
    (() => {
        try {
            const raw = localStorage.getItem('autorelatorio_usuarios');
            if (raw) {
                let lista = JSON.parse(raw);
                if (Array.isArray(lista)) {
                    // Remove contas padrão de teste pré-existentes
                    lista = lista.filter(u => 
                        u && u.email && 
                        u.email !== 'admin@arcelormittal.com.br' &&
                        u.matricula !== 'AM-01020'
                    );
                    localStorage.setItem('autorelatorio_usuarios', JSON.stringify(lista));
                }
            }
        } catch (e) {}
    })();

    /**
     * Obtém estritamente a lista de usuários criados pelo usuário
     */
    function obterUsuariosLocais() {
        const raw = localStorage.getItem('autorelatorio_usuarios');
        try {
            const lista = raw ? JSON.parse(raw) : [];
            return Array.isArray(lista) ? lista : [];
        } catch (e) {
            return [];
        }
    }

    /**
     * Salva novo usuário estritamente quando criado no formulário 'Criar Conta'
     */
    function salvarUsuarioLocal(novo) {
        const lista = obterUsuariosLocais();
        const filtrada = lista.filter(u => u.email.toLowerCase() !== novo.email.toLowerCase());
        filtrada.push(novo);
        localStorage.setItem('autorelatorio_usuarios', JSON.stringify(filtrada));
    }

    /**
     * Função segura de requisição HTTP com tratamento de JSON e erros de servidor
     */
    async function requisicao(url, opcoes = {}) {
        if (location.protocol === 'file:') {
            throw new Error('FILE_PROTOCOL');
        }

        let resposta;
        try {
            resposta = await fetch(url, {
                credentials: 'same-origin',
                ...opcoes
            });
        } catch (erroRede) {
            throw new Error('NETWORK_ERROR');
        }

        let dados;
        const textoResposta = await resposta.text();
        try {
            dados = JSON.parse(textoResposta);
        } catch (erroJson) {
            throw new Error(textoResposta.trim() || `Erro interno do servidor (${resposta.status})`);
        }

        if (!resposta.ok || dados.success === false) {
            throw new Error(dados.message || 'Não foi possível concluir a operação.');
        }

        return dados;
    }

    function enviarJSON(url, dados) {
        return requisicao(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(dados)
        });
    }

    /**
     * Exibe mensagens de feedback diretamente no formulário
     */
    function mensagem(formulario, texto, erro = false) {
        const alertaErro = $('#alertaErro');
        const alertaSucesso = $('#alertaSucesso');

        if (alertaErro && alertaSucesso) {
            if (erro) {
                alertaSucesso.style.display = 'none';
                alertaErro.textContent = texto;
                alertaErro.style.display = 'block';
            } else {
                alertaErro.style.display = 'none';
                alertaSucesso.textContent = texto;
                alertaSucesso.style.display = 'block';
            }
            return;
        }

        let elemento = $('.backend-message', formulario);
        if (!elemento) {
            elemento = document.createElement('div');
            elemento.className = 'backend-message';
            formulario.prepend(elemento);
        }

        elemento.textContent = texto;
        elemento.style.display = 'block';
        elemento.style.padding = '10px 14px';
        elemento.style.borderRadius = '6px';
        elemento.style.marginBottom = '16px';
        elemento.style.fontSize = '14px';
        elemento.style.fontWeight = '500';

        if (erro) {
            elemento.style.backgroundColor = '#fde8e8';
            elemento.style.color = '#c81e1e';
            elemento.style.border = '1px solid #f8b4b4';
        } else {
            elemento.style.backgroundColor = '#def7ec';
            elemento.style.color = '#03543f';
            elemento.style.border = '1px solid #bcf0da';
        }
    }

    document.addEventListener('submit', async (evento) => {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement)) return;

        const caminho = location.pathname.toLowerCase();

        // Identificação dos formulários
        const isLogin =
            form.id === 'formLogin' ||
            caminho.endsWith('/pagina_inicial.html') ||
            caminho.endsWith('/entrar.html') ||
            caminho.endsWith('/login.html');

        const isCadastro =
            form.id === 'formCadastro' ||
            caminho.endsWith('/criar_conta.html') ||
            caminho.endsWith('/cadastro.html');

        const isArea = form.id === 'formArea';
        const isEquipamento = form.id === 'formEquipamento';
        const isInspecao = form.id === 'formInspecao';
        const isProblema = form.id === 'formProblema';

        if (!isLogin && !isCadastro && !isArea && !isEquipamento && !isInspecao && !isProblema) {
            return;
        }

        evento.preventDefault();
        evento.stopImmediatePropagation();

        const btnSubmit = form.querySelector('button[type="submit"]') || form.querySelector('button');
        const textoOriginalBtn = btnSubmit ? btnSubmit.textContent : '';
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Processando...';
        }

        try {
            // 1. LOGIN DE USUÁRIO - ESTRITAMENTE COM DADOS CRIADOS NO CADASTRO
            if (isLogin) {
                const identificador = ($('#email', form) || $('#matricula', form) || $('#usuario', form))?.value.trim();
                const senha = $('#senha', form)?.value;

                if (!identificador || !senha) {
                    throw new Error('Preencha seu e-mail (ou matrícula) e sua senha.');
                }

                let resultado = null;
                let erroServidor = null;

                try {
                    resultado = await enviarJSON(ENDPOINTS.login, {
                        email: identificador,
                        matricula: identificador,
                        senha: senha
                    });
                } catch (err) {
                    erroServidor = err;
                    // Se o servidor retornou erro explícito de validação (ex: senha incorreta ou usuário inexistente no banco)
                    if (err.message && !err.message.includes('FILE_PROTOCOL') && !err.message.includes('NETWORK_ERROR') && !err.message.includes('servidor')) {
                        throw new Error(err.message);
                    }
                }

                // Se o PHP estiver offline ou rodando direto via file://, valida estritamente no armazenamento local
                if (!resultado || !resultado.success) {
                    const usuarios = obterUsuariosLocais();
                    const loginLimpo = identificador.toLowerCase();

                    // Procura exclusivamente entre os usuários que foram cadastrados
                    const usuarioLocal = usuarios.find(u =>
                        (u.email && u.email.toLowerCase() === loginLimpo) ||
                        (u.matricula && u.matricula.toLowerCase() === loginLimpo)
                    );

                    if (!usuarioLocal) {
                        throw new Error('Nenhuma conta encontrada com este e-mail/matrícula. É obrigatório criar a conta primeiro na página "Criar conta".');
                    }

                    if (String(usuarioLocal.senha) !== String(senha)) {
                        throw new Error('Senha incorreta para a conta informada. Digite a senha cadastrada.');
                    }

                    resultado = {
                        success: true,
                        message: 'Login realizado com sucesso!',
                        usuario: usuarioLocal
                    };
                }

                // Carrega estritamente as informações do usuário real cadastrado
                const usuario = resultado.usuario;
                const dadosSessao = {
                    id: usuario.id || 1,
                    nome: usuario.nome || identificador,
                    email: usuario.email || identificador,
                    matricula: usuario.matricula || '',
                    telefone: usuario.telefone || '',
                    funcao: usuario.papel || 'Técnico',
                    perfil: usuario.perfil || 'Perfil Técnico',
                    foto: usuario.foto || usuario.foto_perfil || ''
                };

                sessionStorage.setItem('usuarioLogado', JSON.stringify(dadosSessao));
                sessionStorage.setItem('sessaoUsuario', JSON.stringify(dadosSessao));
                localStorage.setItem('usuarioLogado', JSON.stringify(dadosSessao));
                localStorage.setItem('sessaoUsuario', JSON.stringify(dadosSessao));
                localStorage.setItem('perfilUsuario', JSON.stringify(dadosSessao));

                mensagem(form, 'Login realizado com sucesso! Redirecionando...', false);

                setTimeout(() => {
                    location.href = window.location.pathname.includes('/page/') ? 'dashboard.html' : 'page/dashboard.html';
                }, 800);
            }

            // 2. CADASTRO DE CONTA - ONDE O USUÁRIO CRIA SUA INFORMAÇÃO REAL
            else if (isCadastro) {
                const formData = new FormData(form);
                const dados = Object.fromEntries(formData.entries());

                const nome = (dados.nome || '').trim();
                const email = (dados.email || '').trim().toLowerCase();
                const senha = dados.senha || '';
                const confirma = dados.confirmarSenha || dados.confirmar_senha || dados.confirma_senha || '';
                const telefone = (dados.telefone || '').trim();
                const idade = dados.idade ? parseInt(dados.idade, 10) : null;

                if (!nome) {
                    throw new Error('Por favor, informe seu nome completo.');
                }

                if (!email || !email.includes('@')) {
                    throw new Error('Por favor, informe um endereço de e-mail corporativo válido.');
                }

                if (idade !== null && (idade < 1 || idade > 120)) {
                    throw new Error('Por favor, informe uma idade válida.');
                }

                if (!senha || senha.length < 6) {
                    throw new Error('A senha deve ter no mínimo 6 caracteres.');
                }

                if (senha !== confirma) {
                    throw new Error('As senhas informadas não coincidem. Digite a mesma senha nos dois campos.');
                }

                // Verifica se já existe localmente
                const usuariosExistentes = obterUsuariosLocais();
                if (usuariosExistentes.some(u => u.email && u.email.toLowerCase() === email)) {
                    throw new Error('Este e-mail já está cadastrado no sistema. Faça o login diretamente.');
                }

                const matriculaGerada = dados.matricula || ('AM-' + Math.floor(10000 + Math.random() * 90000));
                const payload = {
                    nome: nome,
                    email: email,
                    idade: idade,
                    senha: senha,
                    confirmar_senha: confirma,
                    telefone: telefone,
                    matricula: matriculaGerada,
                    perfil: 'Perfil Técnico',
                    papel: 'funcionario'
                };

                let resultado = null;
                try {
                    resultado = await enviarJSON(ENDPOINTS.cadastro, payload);
                } catch (err) {
                    // Se o servidor retornou erro explícito (ex: email duplicado 409), exibe para o usuário
                    if (err.message && !err.message.includes('FILE_PROTOCOL') && !err.message.includes('NETWORK_ERROR') && !err.message.includes('servidor')) {
                        throw new Error(err.message);
                    }
                }

                // Grava estritamente a conta criada pelo usuário
                salvarUsuarioLocal(payload);

                mensagem(form, 'Conta criada com sucesso! Redirecionando para o login...', false);
                form.reset();

                setTimeout(() => {
                    location.href = window.location.pathname.includes('/page/') ? '../pagina_inicial.html' : 'pagina_inicial.html';
                }, 1200);
            }

            // 3. CADASTRO DE ÁREA
            else if (isArea) {
                const resultado = await enviarJSON(ENDPOINTS.area, {
                    nome: ($('#nomeArea', form) || $('#nome', form))?.value,
                    descricao: ($('#descricaoArea', form) || $('#descricao', form))?.value,
                    icone: ($('#iconeArea', form) || $('#icone', form))?.value || 'factory'
                });

                mensagem(form, resultado.message);
                form.reset();
            }

            // 4. CADASTRO DE EQUIPAMENTO
            else if (isEquipamento) {
                const resultado = await enviarJSON(ENDPOINTS.equipamento, {
                    numero_patrimonio: ($('#patrimonio', form) || $('#numero_patrimonio', form))?.value,
                    nome: ($('#nomeEquipamento', form) || $('#nome', form))?.value,
                    area_id: ($('#areaEquipamento', form) || $('#area_id', form))?.value,
                    localizacao: ($('#localizacao', form))?.value,
                    status_operacional: ($('#statusEquipamento', form) || $('#status_operacional', form))?.value !== 'Inoperante'
                });

                mensagem(form, resultado.message);
                form.reset();
            }

            // 5. CADASTRO DE INSPEÇÃO
            else if (isInspecao) {
                const dataVal = $('#data', form)?.value || '';
                const horaVal = $('#hora', form)?.value || '';
                const realizadaEm = (dataVal && horaVal) ? `${dataVal}T${horaVal}` : (dataVal || '');

                const resultado = await enviarJSON(ENDPOINTS.inspecao, {
                    realizada_em: realizadaEm,
                    area_id: ($('#area', form) || $('#area_id', form))?.value,
                    equipamento_id: ($('#equipamento', form) || $('#equipamento_id', form))?.value,
                    numero_perimetro: ($('#numeroPerimetro', form) || $('#perimetro', form))?.value || 'Não informado',
                    localizacao: ($('#localizacaoInspecao', form) || $('#localizacao', form))?.value || 'Não informado',
                    objetivo: ($('#objetivoInspecao', form) || $('#objetivo', form))?.value || 'Inspeção industrial',
                    observacoes: ($('#observacoes', form))?.value || ''
                });

                sessionStorage.setItem('autorelatorio_inspecao_atual', String(resultado.inspecao_id));
                mensagem(form, resultado.message);
                form.reset();
            }

            // 6. REGISTRO DE PROBLEMA COM FOTO
            else if (isProblema) {
                const areaId = ($('#areaProblema', form) || $('#area_id', form))?.value;
                const equipamentoId = ($('#equipamentoProblema', form) || $('#equipamento_id', form))?.value;

                if (!areaId || !equipamentoId) {
                    throw new Error('Por favor, selecione a área e o equipamento afetado.');
                }

                let fotoCaminho = '';
                const inputFile = form.querySelector('input[type="file"]');
                const arquivo = inputFile?.files?.[0];

                if (arquivo) {
                    const formFoto = new FormData();
                    formFoto.append('foto', arquivo);

                    const uploadRes = await requisicao(ENDPOINTS.upload, {
                        method: 'POST',
                        body: formFoto
                    });

                    fotoCaminho = uploadRes.path || '';
                }

                const resultado = await enviarJSON(ENDPOINTS.cadastro, {
                    nome: 'Relato Técnico',
                    email: 'relato@sistema.local',
                    idade: 30,
                    senha: 'auto-report-password',
                    informacao_problema: ($('#descricaoProblema', form) || $('#descricao', form))?.value || '',
                    fotografia: fotoCaminho
                });

                mensagem(form, resultado.message || 'Problema registrado com sucesso!');
                form.reset();
            }

        } catch (erro) {
            mensagem(form, erro.message || 'Ocorreu um erro ao processar.', true);
        } finally {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.textContent = textoOriginalBtn;
            }
        }
    }, true);
})();
