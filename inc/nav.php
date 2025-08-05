<?php
$rootPath = '/fila/';

?>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $rootPath; ?>index.php">Karaokê Clube</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'rodadas.php' || $current_page == 'index.php') ? 'active' : ''; ?>" aria-current="page" href="<?php echo $rootPath; ?>rodadas.php">Gerenciar Fila</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'mesas.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>mesas.php">Mesas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'cantores.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>cantores.php">Cantores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'musicas_cantores.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>musicas_cantores.php">Músicas Cantores</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Configuração
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <?php if (check_access(NIVEL_ACESSO, ['admin', 'mc'])): ?>
                            <li><a class="dropdown-item <?php echo ($current_page == 'regras.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>regras.php">Regras</a></li>
                        <?php endif; ?>

                        <?php if (check_access(NIVEL_ACESSO, ['super_admin'])): ?>
                            <li><a class="dropdown-item <?php echo ($current_page == 'tenants.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>tenants.php">Clientes</a></li>
                        <?php endif; ?>

                        <?php if (check_access(NIVEL_ACESSO, ['super_admin'])): ?>
                            <li><a class="dropdown-item <?php echo ($current_page == 'permissao.php') ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>permissao.php">Permissão</a></li>
                        <?php endif; ?>


                        <li><a class="dropdown-item" id="resetarSistema" href="#sectionResetarSistema">Resetar Todo o Sistema</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo $rootPath; ?>index.php?action=logout">Sair</a></li>

                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
