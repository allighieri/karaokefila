<nav class="navbar navbar-expand-lg navbar-dark fixed-top bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Gerenciador de Filas Karaokê</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'rodadas.php' || $current_page == 'index.php') ? 'active' : ''; ?>" aria-current="page" href="rodadas.php">Gerenciar Fila</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'mesas.php') ? 'active' : ''; ?>" href="mesas.php">Mesas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'cantores.php') ? 'active' : ''; ?>" href="cantores.php">Cantores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'musicas_cantores.php') ? 'active' : ''; ?>" href="musicas_cantores.php">Músicas Cantores</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#sectionRegras">Regras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="resetarSistema" href="#sectionResetarSistema">Resetar Sistema</a>
                </li>
            </ul>
        </div>
    </div>
</nav>