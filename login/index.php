<?php
require_once '../funcoes_fila.php';

$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
$rootPath = '/fila/';
?>

<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="dark" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="icos/android-icon-144x144.png" itemprop="image">
    <title>Gerenciador de Karaokê - MC Panel</title>
    <link rel="shortcut icon" href="<?php echo $rootPath; ?>icos/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo $rootPath; ?>icos/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo $rootPath; ?>icos/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo $rootPath; ?>icos/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo $rootPath; ?>icos/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo $rootPath; ?>icos/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo $rootPath; ?>icos/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo $rootPath; ?>icos/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo $rootPath; ?>icos/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $rootPath; ?>icos/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="<?php echo $rootPath; ?>icos/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $rootPath; ?>icos/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $rootPath; ?>icos/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $rootPath; ?>icos/favicon-16x16.png">
    <link rel="manifest" href="<?php echo $rootPath; ?>icos/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <style>

        /* O container principal, que ajusta a altura */
        .login {
            position: relative;
            overflow: hidden;
            transition: height 0.5s ease-in-out;
        }

        /* O container interno que faz o slide */
        .login-inner {
            display: flex;
            width: 200%;
            transition: transform 0.5s ease-in-out;
        }

        .logar, .cadUser {
            flex-shrink: 0;
            width: 50%;
            padding: 0 1rem;
        }

        /* Oculta o ícone de erro padrão do Bootstrap para o form-floating */
        .form-floating .form-control.is-invalid ~ .form-control:not(:focus)::after {
            display: none;
        }

        /* Oculta o ícone de sucesso padrão do Bootstrap */
        .form-floating .form-control.is-valid ~ .form-control:not(:focus)::after {
            display: none;
        }

        /* Estilo do nosso gatilho do tooltip (invisível, mas clicável) */
        .form-floating .tooltip-trigger {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            z-index: 99; /* Garante que ele está por cima */
            width: 24px;  /* Tamanho da área que vai capturar o mouse */
            height: 24px;
            cursor: help;
            pointer-events: all;
        }
    </style>

</head>
<body class="d-flex align-items-center text-center py-4 bg-body-tertiary h-100">



<main class="col-lg-3 p-3 login m-auto">
    <div class="login-inner">
        <form class="logar">
            <img src="<?php echo $rootPath ;?>icos/favicon-96x96.png" alt="Logo Boostrap" class="mb-4" />
            <h1 class="h3 mb-3 fw-normal">Fazer login</h1>

            <div class="form-floating">
                <input type="email" class="form-control" id="email" placeholder="your-email@gmail.com">
                <label for="email">E-mail</label>
            </div>
            <div class="form-floating">
                <input type="password" class="form-control" id="password" placeholder="your-email@gmail.com">
                <label for="password">Senha</label>
            </div>

            <div class="form-check text-start my-3">
                <input type="checkbox" class="form-check-input" id="ver" />
                <label for="ver" class="form-check-label">Ver senha</label>
            </div>

            <div id="alertContainerLogin" class="mt-3"></div>

            <button class="btn btn-primary w-100 py-2 mb-2" id="btn_logar">Logar</button>
            <p>Não tem cadastro? <a href="#" id="link-cadastro">Fazer cadastro</a></p>
            <p>&copy <?php echo date('Y') ;?></p>
        </form>

        <form class="cadUser">
            <img src="<?php echo $rootPath ;?>icos/favicon-96x96.png" alt="Logo Boostrap" class="mb-4" />
            <h1 class="h3 mb-3 fw-normal">Fazer cadastro</h1>

            <div class="form-floating mb-4">
                <input type="text" class="form-control" id="cad_code" name="cad_code" placeholder="Código de Acesso">
                <label for="cad_code">Código de Acesso</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="cad_nome" name="cad_nome" placeholder="Seu nome">
                <label for="cad_nome">Nome</label>
            </div>
            <div class="form-floating">
                <input type="email" class="form-control" id="cad_email" name="cad_email" placeholder="your-email@gmail.com">
                <label for="cad_email">E-mail</label>
            </div>
            <div class="form-floating">
                <input type="text" class="form-control" id="cad_telefone" name="cad_telefone" placeholder="Informe o número do seu telefone">
                <label for="cad_telefone">Telefone</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="cad_cidade" name="cad_cidade" placeholder="De qual cidade você é?">
                <label for="cad_cidade">Cidade</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="cad_uf" name="cad_uf" placeholder="De qual estado você é?" maxlength="2" pattern="[a-zA-Z]{2}">
                <label for="cad_uf">UF</label>
            </div>

            <div class="form-floating">
                <input type="password" class="form-control" id="cad_password" name="cad_password" placeholder="Criar senha">
                <label for="cad_password">Senha</label>
            </div>
            <div class="form-floating">
                <input type="password" class="form-control" id="cad_password_confirma" name="cad_password_confirma" placeholder="Confirmar senha">
                <label for="cad_password_confirma">Confirmar senha</label>
            </div>

            <div class="form-check text-start my-3">
                <input type="checkbox" class="form-check-input" id="cad_ver" />
                <label for="cad_ver" class="form-check-label">Ver senha</label>
            </div>

            <div id="alertContainerCad" class="mt-3"></div>

            <button class="btn btn-success w-100 py-2 mb-2" id="btn_cad_user">Cadastrar</button>
            <p>Já tem cadastro? <a href="#" id="link-login">Fazer login</a></p>
            <p>&copy <?php echo date('Y') ;?></p>
        </form>
    </div>



</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
    // Função para exibir alertas dinamicamente
    window.showAlert = function(message, type, containerId) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            '<span>' + message + '</span>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        $('#' + containerId).html(alertHtml);
        setTimeout(function() {
            $('#' + containerId + ' .alert').alert('close');
        }, 5000); // Alerta desaparece após 5 segundos
    }

    $(document).ready(function() {
        // Inicialização global dos tooltips do Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Lógica para mostrar/esconder senha
        $('#ver').on('change', function() {
            $('#password').attr('type', $(this).is(':checked') ? 'text' : 'password');
        });

        $('#cad_ver').on('change', function() {
            $('#cad_password, #cad_password_confirma').attr('type', $(this).is(':checked') ? 'text' : 'password');
        });

        const loginForm = $('.logar');
        const cadForm = $('.cadUser');
        const loginInner = $('.login-inner');
        const loginContainer = $('.login');

        loginContainer.css('height', loginForm.outerHeight() + 'px');

        $('#link-cadastro').on('click', function(e) {
            e.preventDefault();
            loginContainer.css('height', cadForm.outerHeight() + 'px');
            loginInner.css('transform', 'translateX(-50%)');
        });

        $('#link-login').on('click', function(e) {
            e.preventDefault();
            loginContainer.css('height', loginForm.outerHeight() + 'px');
            loginInner.css('transform', 'translateX(0)');
        });

        $('.cadUser').validate({
            // ... (regras e mensagens permanecem as mesmas) ...
            rules: {
                cad_code: { required: true },
                cad_nome: { required: true },
                cad_email: { required: true, email: true },
                cad_telefone: { required: true },
                cad_cidade: { required: true },
                cad_uf: { required: true, maxlength: 2 },
                cad_password: { required: true, minlength: 6 },
                cad_password_confirma: { required: true, minlength: 6, equalTo: '#cad_password' }
            },
            messages: {
                cad_code: "Por favor, digite o código de acesso.",
                cad_nome: "Por favor, digite seu nome.",
                cad_email: { required: "Por favor, digite seu e-mail.", email: "Por favor, digite um e-mail válido." },
                cad_telefone: "Por favor, digite seu telefone.",
                cad_cidade: "Por favor, digite sua cidade.",
                cad_uf: { required: "Por favor, digite o estado (UF).", maxlength: "O estado deve ter 2 letras." },
                cad_password: { required: "Por favor, crie uma senha.", minlength: "A senha deve ter no mínimo 6 caracteres." },
                cad_password_confirma: { required: "Por favor, confirme sua senha.", minlength: "A senha deve ter no mínimo 6 caracteres.", equalTo: "As senhas não coincidem." }
            },

            errorPlacement: function(error, element) {
                const parent = element.closest('.form-floating');

                // Remove o gatilho do tooltip anterior, se existir
                parent.find('.tooltip-trigger').remove();

                // Adiciona a classe de erro do Bootstrap
                element.addClass('is-invalid');

                // Cria o elemento que será o gatilho do tooltip
                const tooltipTrigger = $('<span>')
                    .addClass('tooltip-trigger')
                    .attr('title', error.text())
                    .attr('data-bs-toggle', 'tooltip');

                parent.append(tooltipTrigger);

                // Inicializa o tooltip imediatamente
                new bootstrap.Tooltip(tooltipTrigger[0]);
            },

            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
                $(element).closest('.form-floating').find('.tooltip-trigger').remove();
            },

            success: function(label, element) {
                $(element).removeClass('is-invalid').addClass('is-valid');
                $(element).closest('.form-floating').find('.tooltip-trigger').remove();
            },

            // Esta função é executada APENAS se o formulário for válido
            submitHandler: function(form) {
                const $btn = $('#btn_cad_user');

                const nome = $('#cad_nome').val().trim();
                const email = $('#cad_email').val().trim();
                const code = $('#cad_code').val().trim();
                const telefone = $('#cad_telefone').val().trim();
                const cidade = $('#cad_cidade').val().trim();
                const uf = $('#cad_uf').val().trim();
                const password = $('#cad_password').val();

                $btn.prop('disabled', true).text('Cadastrando...');

                $.ajax({
                    url: '<?php echo $rootPath; ?>api.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cadastrar_usuario',
                        nome: nome,
                        email: email,
                        telefone: telefone,
                        cidade: cidade,
                        uf: uf,
                        senha: password,
                        code: code
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('Cadastrar');

                        if (response.success) {
                            const alertType = 'success';
                            window.showAlert(response.message, alertType, "alertContainerCad");

                            $('#cad_nome, #cad_email, #cad_telefone, #cad_cidade, #cad_uf, #cad_password, #cad_password_confirma, #cad_code').val("").removeClass('is-valid');

                            setTimeout(() => {
                                $('#link-login').trigger('click');
                            }, 3000);
                        } else {
                            const alertType = 'danger';
                            window.showAlert(response.message, alertType, "alertContainerCad");
                            // Remove a classe de validação se houver erro do servidor
                            $('.cadUser').find('.is-valid').removeClass('is-valid');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na requisição AJAX:", status, error);
                        window.showAlert("Ocorreu um erro na comunicação com o servidor.", "danger", "alertContainerCad");
                        $btn.prop('disabled', false).text('Cadastrar');
                    }
                });
            }
        });
    });
</script>

</body>
</html>