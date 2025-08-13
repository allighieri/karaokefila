<?php
session_start();
if (isset($_POST['pesquisa'])) {
    $pesquisa = trim($_POST['pesquisa']);
    $_SESSION['textoBusca'] = $pesquisa;
    $textoBusca = mb_convert_case($_SESSION['textoBusca'], MB_CASE_UPPER, 'UTF-8');
    unset($_SESSION['textoBusca']);
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include_once("analyticstracking.php") ?>
    <?php include_once('meta.php'); ?>
    <title>Pesquisa de Músicas do Karaokê Clube | Aluguel de Karaokê Brasília</title>
    <link href='css/style.min.css?v=20250812034401' rel='stylesheet' type='text/css'>
    <link href='css/fonts-icones.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/jquery.min.js"></script>



</head>
<body onLoad="document.frmEnviaDados.pesquisa.focus();">

<?php include_once 'inc/menu.php'; ?>

<h1 class="section-title-search">LISTA DE MÚSICAS</h1>
<p class="info-search">As músicas estão organizadas por cantor em ordem alfabética.</p>

<section class="pesquisar">
    <form class="pesquisa" autocomplete="on" action="musicasbusca.php" method="post" name="frmEnviaDados"
          id="frmEnviaDados">
        <label for="pesquisa"></label>
        <input type="text" id="pesquisa" name="pesquisa" placeholder="Pesquisar por cantor, música, código..."
               value="<?php echo $textoBusca ?? ''; ?>" autocomplete="off"/>

        <div id="status-busca">
            <i class="fas fa-magnifying-glass"></i>
            <i class="fas fa-spinner fa-spin" style="display: none;"></i>
        </div>
    </form>
</section>

<section id="mus_lista">
    <?php
    if (isset($_POST['pesquisa'])) {
        include_once('conn.php');
        $pesquisa = trim($_POST['pesquisa']);
        $query = $db->query("SELECT 
                                    l.idLista,
                                    l.interprete,
                                    l.codigo,
                                    l.titulo,
                                    l.inicio,
                                    l.genero,
                                    l.idioma
                                    FROM lista as l
                                    WHERE l.interprete LIKE '%$pesquisa%' OR l.titulo LIKE '%$pesquisa%' OR l.codigo LIKE '%$pesquisa%' OR l.inicio LIKE '%$pesquisa%'
                                    ORDER BY 
                                    interprete ASC, 
                                    titulo ASC
                                ");
    } else {
        $pesquisa = "";
    }

    if (strlen($pesquisa) < 2 || empty($pesquisa)) {
        echo "<p class='error'>Sua pesquisa deve ter mais de 2 caracteres!</p>";
    } else {
        $queryNum = $db->query("SELECT 
                        COUNT(*) as postNum,
                        l.idLista,
                        l.interprete,
                        l.codigo,
                        l.titulo,
                        l.inicio,
                        l.idioma,
                        l.genero
                        FROM lista as l
                        WHERE l.interprete LIKE '%$pesquisa%' OR l.titulo LIKE '%$pesquisa%' OR l.codigo LIKE '%$pesquisa%' OR l.inicio LIKE '%$pesquisa%'
                        ORDER BY 
                        interprete ASC, 
                        titulo ASC
                    ");
        $resultNum = $queryNum->fetch_assoc();
        $rowCount = $resultNum['postNum'];
        if ($query->num_rows > 0) { ?>
            <p class="ocorrencia" style="text-align: center; font-size: 0.8rem;">Sua busca retornou<strong> <?php echo $query->num_rows; ?>
                    resultados </strong>
<!--                <strong>--><?php //echo mb_convert_case($pesquisa, MB_CASE_UPPER, 'UTF-8'); ?><!--</strong></p>-->
            <div class="search-list">
                <?php
                while ($row = $query->fetch_assoc()) {
                    $cod = $row["codigo"];
                    $count = strlen($cod);
                    if ($count < 5) {
                        $codNovo = str_pad($cod, 5, "0", STR_PAD_LEFT);
                    } else {
                        $codNovo = $cod;
                    }

                    // Usa o idioma diretamente como classe
                    $idioma_classe = strtolower($row['idioma']);
                    ?>
                    <div class="search-result-card">
                        <div class="code-circle <?php echo $idioma_classe; ?>">
                            <span><?php echo $row['codigo']; ?></span>
                        </div>
                        <div class="content">
                            <div class="line">
                                <span class="interprete"><?php echo $row['interprete']; ?></span>
                            </div>
                            <div class="line">
                                <span class="flag <?php echo $idioma_classe; ?>"></span>
                                <span class="titulo_list"><?php echo $row['titulo']; ?></span>
                            </div>
                            <p class="letra_list"><?php echo $row['inicio']; ?></p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else {
            echo "<p class='error'>Nenhuma ocorrência para a sua pesquisa <strong>" . mb_convert_case($pesquisa, MB_CASE_UPPER, 'UTF-8') . " </strong></p>";
        };
    };

    ?>
</section>


<div id="box-whats" style="display:none;">
    <a class="fechar_modal_zap" href="#" rel="modal:close" type="button">x</a>
    <h1>Faça seu orçamento</h1>
    <p class="box-whats-info">Atendemos somente em BRASÍLIA-DF*</p>

    <form action="" method="post" id="meuFormulario">
        <div class="input-field">
            <input id="name" type="text" name="name" class="validate">
            <label for="name"><span class="icon icon-vcard-1"></span> Nome</label>
        </div>

        <div class="input-field">
            <input id="local" type="text" name="local" class="validate">
            <label for="local"><span class="icon icon-map"></span> Local</label>
        </div>

        <div class="input-field">
            <input id="data" type="text" name="data" class="validate" readonly>
            <label for="data"><span class="icon icon-calendar"></span> Data</label>
            <span class="info-zap">Se não tiver a data exata, coloque aproximada.</span>
        </div>

        <div class="errors"></div>

        <div class="btn-zap">
            <a href="#" id="btn-zap" rel="modal:close" type="button">Enviar <span class="icon icon-whatsapp"></a>
            <a href="javascript:void(0)" id="btn-zap-cancelar" rel="modal:close" type="button">Cancelar <span class="icon icon-whatsapp"></a>
        </div>

        <hr/>

        <div class="email">
            <p>Você também pode entrar em contato pelo email <a href="mailto:agenciaolhardigital@gmail.com"
                                                                class="underline"
                                                                title="Para um melhor atendimento, recomendamos entrar em contato pelo WhatsApp"><strong>agenciaolhardigital@gmail.com</strong></a>
            </p>
            <p>Ou pelo celular <a href="tel:+5561994619520" class="underline"
                                  title="Ligue e agende o seu karaokê"><strong>61 99461-9520</strong></a></p>
        </div>
    </form>
</div>

<?php include_once 'inc/rodape.php'; ?>

<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script src="js/jquery.modal.min.js" type="text/javascript" charset="utf-8"></script>
<script src="js/modal-functions.js"></script>
<script>
    // =========================================================
    //  FUNÇÕES GLOBAIS
    // =========================================================
    function openModal(modalId) {
        $('#' + modalId).modal({
            fadeDuration: 250,
            showClose: false
        });
    }

    function limparModalWhats() {
        $("#meuFormulario")[0].reset();
        $("label[for='name']").css("top", "12px");
        $("label[for='local']").css("top", "12px");
        $("label[for='data']").css("top", "12px");
    }

    // Função auxiliar para verificar se um elemento está visível na tela
    function isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Função para configurar o observador de animação
    function setupFadeInObserver() {
        // Opções do observador (não muda)
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Lógica para aplicar a animação aos elementos novos
        document.querySelectorAll('.fade-in').forEach(el => {
            if (isElementInViewport(el)) {
                // Se o elemento já estiver visível, adicione a classe 'visible' imediatamente.
                el.classList.add('visible');
            } else {
                // Caso contrário, observe o elemento para quando ele se tornar visível.
                observer.observe(el);
            }
        });
    }

    // =========================================================
    //  INÍCIO DO SCRIPT JQUERY
    // =========================================================
    $(document).ready(function () {

        // Intercepta o envio do formulário e executa a busca via AJAX
        $('#frmEnviaDados').on('submit', function (e) {
            e.preventDefault(); // Esta linha impede o envio padrão do formulário e o refresh da página
            performSearch();
        });

        setupFadeInObserver(); // Ativa a animação inicial

        // =========================================================
        // PESQUISA EM TEMPO REAL
        // =========================================================
        var busca = $('#pesquisa');
        var typingTimer;
        var doneTypingInterval = 400; // Aumentado para 400ms para reduzir requisições

        function performSearch() {
            var searchTerm = busca.val();
            if (searchTerm.length >= 2) {
                $.ajax({
                    url: 'search.php',
                    type: 'POST',
                    dataType: 'json', // Especifica que esperamos JSON
                    data: {
                        pesquisa: searchTerm
                    },
                    beforeSend: function() {
                        $('#status-busca .fa-magnifying-glass').hide();
                        $('#status-busca .fa-spinner').show();
                    },
                    success: function (response) {
                        if (response.success && response.results.length > 0) {
                            var html = '<p class="ocorrencia" style="text-align: center; font-size: 0.8rem;">' + response.message + '</p>';
                            html += '<div class="search-list">';
                            
                            // Renderiza os resultados usando os dados JSON
                            $.each(response.results, function(index, item) {
                                html += '<div class="search-result-card ' + item.fade_class + '" data-relevancia="' + item.relevancia + '">';
                                html += '    <div class="code-circle ' + item.idioma_classe + '">';
                                html += '        <span>' + item.codigo + '</span>';
                                html += '    </div>';
                                html += '    <div class="content">';
                                html += '        <div class="line">';
                                html += '            <span class="interprete">' + item.interprete + '</span>';
                                html += '        </div>';
                                html += '        <div class="line">';
                                html += '            <span class="flag ' + item.idioma_classe + '"></span>';
                                html += '            <span class="titulo_list">' + item.titulo + '</span>';
                                html += '        </div>';
                                html += '        <p class="letra_list">' + item.inicio + '</p>';
                                html += '    </div>';
                                html += '</div>';
                            });
                            
                            html += '</div>';
                            $('#mus_lista').html(html);
                            
                            // Adiciona dados estruturados para SEO
                            updateStructuredData(response.results, searchTerm);
                        } else {
                            $('#mus_lista').html('<p class="error">' + response.message + '</p>');
                        }
                        
                        setupFadeInObserver(); // Re-ativa a animação para os novos cards
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('Erro na busca:', textStatus, errorThrown);
                        $('#mus_lista').html('<p class="error">Ocorreu um erro na busca. Tente novamente.</p>');
                    },
                    complete: function() {
                        $('#status-busca .fa-magnifying-glass').show();
                        $('#status-busca .fa-spinner').hide();
                    }
                });
            } else {
                $('#mus_lista').html('<p class="error">Digite pelo menos 2 caracteres para pesquisar.</p>');
            }
        }

        // Função para atualizar dados estruturados para SEO
        function updateStructuredData(results, query) {
            var structuredData = {
                "@context": "https://schema.org",
                "@type": "SearchResultsPage",
                "name": "Pesquisa de Músicas: " + query,
                "description": "Resultados da busca por músicas de karaokê",
                "url": window.location.href,
                "mainEntity": {
                    "@type": "ItemList",
                    "numberOfItems": results.length,
                    "itemListElement": []
                }
            };
            
            $.each(results, function(index, item) {
                structuredData.mainEntity.itemListElement.push({
                    "@type": "ListItem",
                    "position": index + 1,
                    "item": {
                        "@type": "MusicRecording",
                        "name": item.titulo,
                        "byArtist": {
                            "@type": "Person",
                            "name": item.interprete
                        },
                        "inLanguage": item.idioma === 'BRA' ? 'pt-BR' : (item.idioma === 'ENG' ? 'en' : item.idioma),
                        "identifier": item.codigo
                    }
                });
            });
            
            // Remove dados estruturados anteriores
            $('script[type="application/ld+json"][data-search-results]').remove();
            
            // Adiciona novos dados estruturados
            $('<script type="application/ld+json" data-search-results><\/script>').html(JSON.stringify(structuredData)).appendTo('head');
        }

        busca.on('keyup', function () {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(performSearch, doneTypingInterval);
        });

        busca[0].setSelectionRange(busca.val().length, busca.val().length);

        if (busca.val().length < 2) {
            $('#mus_lista').html('<p class="error">Digite pelo menos 2 caracteres para pesquisar.</p>');
        } else {
            performSearch();
        }

        // =========================================================
        //  LÓGICA DO MENU
        // =========================================================
        $('.open').on('click', function (e) {
            e.preventDefault();
            $("#myNav").css('width', '100%');
        });
        $('.closebtn').on('click', function (e) {
            e.preventDefault();
            $("#myNav").css('width', '0');
        });
        $('.overlay-content a').on('click', function () {
            $("#myNav").css('width', '0');
        });

        // =========================================================
        //  LÓGICA DAS MODAIS E FORMULÁRIOS
        // =========================================================
        $('a[href="#box-whats"]').on('click', function (event) {
            event.preventDefault();
            openModal('box-whats');
        });

        $(".errors").hide();

        $('#btn-zap').on('click', function (e) {
            e.preventDefault();
            const formData = $("#meuFormulario").serialize();
            let nome = $("input[name='name']").val().trim();
            let local = $("input[name='local']").val().trim();
            let data = $("input[name='data']").val().trim();

            if (nome === '' || local === '' || data === '') {
                $(".errors").fadeTo(100, 0.85).html('<span class="danger"><p>Por favor, informe todos os dados.</span>');
                setTimeout(function () {
                    $('.errors').fadeOut("slow");
                }, 3000);
                return false;
            }

            $.ajax({
                type: "POST",
                url: "",
                data: formData,
                success: function () {
                    const mensagem = "Olá, meu nome é " + nome + ". Gostaria de um orçamento para locação do karaokê para o dia " + data + ", " + local + ".";
                    const linkWhatsApp = "https://api.whatsapp.com/send?phone=5561994619520&text=" + encodeURIComponent(mensagem);

                    limparModalWhats();
                    $('.errors').fadeOut("slow");

                    setTimeout(function () {
                        window.open(linkWhatsApp, "_blank");
                    }, 200);

                    $.modal.close();
                }
            });
        });

        $('#btn-zap-cancelar, .fechar_modal_zap').on('click', function (e) {
            e.preventDefault();
            $.modal.close();
            afterCloseWhatsModal();
        });


        $("#data").datepicker({
            dateFormat: 'dd/mm/yy',
            closeText: "Fechar",
            prevText: "&#x3C;Anterior",
            nextText: "Próximo&#x3E;",
            currentText: "Hoje",
            monthNames: ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
            monthNamesShort: ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"],
            dayNames: ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"],
            dayNamesShort: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"],
            dayNamesMin: ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"],
            weekHeader: "Sm",
            firstDay: 1,
            onSelect: function () {
                const div = $(this).parent(".input-field");
                const label = div.children("label");
                if ($(this).val().length > 0) {
                    label.css("top", "-10px");
                } else {
                    label.css("top", "12px");
                }
            }
        });

        $("input").on("focus", function () {
            $(this).parent(".input-field").children("label").css("top", "-10px");
        });
        $("input").on("blur", function () {
            if ($(this).val().length === 0) {
                $(this).parent(".input-field").children("label").css("top", "12px");
            }
        });
    });
</script>

</body>
</html>