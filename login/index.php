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

    <style>

        .login input[type="email"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }

        .login input[type="password"] {
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
        .login {
            position: relative; /* O pai precisa ser relativo para que os filhos absolutos se posicionem corretamente */
            height: 450px; /* Defina uma altura fixa para evitar o "salto" na página */
            overflow: hidden; /* Oculta qualquer conteúdo que transborde */
        }

        .logar, .cadUser {
            position: absolute; /* Posição absoluta para que os formulários se sobreponham e não afetem o fluxo */
            top: 0;
            left: 0;
            width: 100%;
            /* Adicione as transições para um efeito suave */
            transition: opacity 0.5s ease-in-out, transform 0.2s ease-in-out;
        }

        .logar.hide {
            opacity: 0; /* Torna o formulário invisível */
            transform: translateX(-100%); /* Desliza para a esquerda (sai da tela) */
        }

        .cadUser {
            transform: translateX(100%); /* Posiciona o formulário de cadastro à direita, fora da tela */
            opacity: 0;
        }

        .cadUser.show {
            opacity: 1;
            transform: translateX(0); /* Desliza para a posição original (centro) */
        }
    </style>

</head>
<body class="d-flex align-items-center text-center py-4 bg-body-tertiary h-100">

<div class="container col-lg-2 p-3">
    <main class="login w-100 m-auto">
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
            <button class="btn btn-primary w-100 py-2 mb-2" id="btn_logar">Logar</button>
            <p>Não tem cadastro? <a href="#" id="link-cadastro">Fazer cadastro</a></p>
            <p>&copy <?php echo date('Y') ;?></p>
        </form>

        <form class="cadUser">
            <img src="<?php echo $rootPath ;?>icos/favicon-96x96.png" alt="Logo Boostrap" class="mb-4" />
            <h1 class="h3 mb-3 fw-normal">Fazer cadastro</h1>

            <div class="form-floating">
                <input type="text" class="form-control" id="cad_nome" placeholder="Seu nome">
                <label for="cad_nome">Nome</label>
            </div>
            <div class="form-floating">
                <input type="email" class="form-control" id="cad_email" placeholder="your-email@gmail.com">
                <label for="cad_email">E-mail</label>
            </div>
            <div class="form-floating">
                <input type="text" class="form-control" id="cad_telefone" placeholder="Informe o número do seu telefone">
                <label for="cad_telefone">Telefone</label>
            </div>

            <div class="form-floating">
                <input type="text" class="form-control" id="cad_endereco" placeholder="Qual o seu endereço">
                <label for="cad_endereco">Endereço</label>
            </div>
            <div class="form-floating">
                <input type="password" class="form-control" id="cad_password" placeholder="your-email@gmail.com">
                <label for="cad_password">Senha</label>
            </div>

            <div class="form-check text-start my-3">
                <input type="checkbox" class="form-check-input" id="cad_ver" />
                <label for="cad_ver" class="form-check-label">Ver senha</label>
            </div>
            <button class="btn btn-success w-100 py-2 mb-2" id="btn_cad_user">Cadastrar</button>
            <p>Já tem cadastro? <a href="#" id="link-login">Fazer login</a></p>
            <p>&copy <?php echo date('Y') ;?></p>
        </form>
    </main>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<script>
    $(document).ready(function() {


        // Lógica para mostrar/esconder senha
        $('#ver').on('change', function() {
            $('#password').attr('type', $(this).is(':checked') ? 'text' : 'password');
        });

        $('#cad_ver').on('change', function() {
            $('#cad_password').attr('type', $(this).is(':checked') ? 'text' : 'password');
        });

        const loginForm = $('.logar');
        const cadForm = $('.cadUser');



        cadForm.removeClass('hide').addClass('show');
        cadForm.css('display', 'block');
        loginForm.css('display', 'none');


        // Evento de clique para o link "Fazer cadastro"
        $('#link-cadastro').on('click', function(e) {
            e.preventDefault();
            // Inicia a transição de saída do formulário de login
            loginForm.addClass('hide');

            // Aguarda o formulário de login sair para começar a entrada do formulário de cadastro
            setTimeout(function() {
                loginForm.css('display', 'none'); // Esconde o formulário de login para liberar o espaço
                cadForm.css('display', 'block'); // Torna o formulário de cadastro visível (sem animação ainda)

                // Remove a classe 'hide' para iniciar a transição de entrada
                setTimeout(function() {
                    cadForm.removeClass('hide').addClass('show');
                }, 50); // Pequeno atraso para a animação ser renderizada corretamente
            }, 200); // O tempo precisa ser o mesmo da transição CSS (0.5s)
        });

        // Evento de clique para o link "Fazer login"
        $('#link-login').on('click', function(e) {
            e.preventDefault();
            // Inicia a transição de saída do formulário de cadastro
            cadForm.removeClass('show').addClass('hide');

            // Aguarda o formulário de cadastro sair
            setTimeout(function() {
                cadForm.css('display', 'none');
                loginForm.css('display', 'block');

                // Inicia a transição de entrada do formulário de login
                setTimeout(function() {
                    loginForm.removeClass('hide');
                }, 50);
            }, 200);
        });

    });
</script>

</body>
</html>