<?php
session_start();

require_once 'conn.php';
$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
$rootPath = '/fila/';

//paginas permitidas sem login
$public_pages = [
    'index.php',
    'conn.php',
    'api.php'
];

// Lógica de logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: " . $rootPath . "login"); // Use a constante base_url para evitar redirecionamento quebrado
    exit();
}

if (!in_array($current_page, $public_pages)) {
    if (!isset($_SESSION['usuario_logado'])) {
        header("Location: " . $rootPath . "login");
        exit();
    }
}

if (isset($_SESSION['usuario_logado'])) {
// dados do usuário
    $dados_sessao = $_SESSION['usuario_logado'];

// Definimos constantes para fácil acesso (recomendado)
// Isso é mais seguro e evita reatribuições
    define('ID_TENANTS', $dados_sessao['usuario']['id_tenants']);
    define('ID_USUARIO', $dados_sessao['usuario']['id']);
    define('NIVEL_ACESSO', $dados_sessao['usuario']['nivel']);
    define('NOME_USUARIO', $dados_sessao['usuario']['nome']);
    define('NOME_TENANT', $dados_sessao['tenant']['nome']);

    // Para MCs, buscar evento ativo específico do MC
    // Para admins/super_admins, verificar se há evento selecionado na sessão
    if (NIVEL_ACESSO === 'mc') {
        $idEventoAtivo = get_id_evento_ativo_mc($pdo, ID_USUARIO);
    } elseif (in_array(NIVEL_ACESSO, ['admin', 'super_admin']) && isset($_SESSION['admin_evento_selecionado'])) {
        // Admin tem evento específico selecionado
        $idEventoAtivo = $_SESSION['admin_evento_selecionado']['id'];
    } else {
        // Fallback para buscar por tenant (compatibilidade)
        $idEventoAtivo = get_id_evento_ativo($pdo, ID_TENANTS);
    }

    if ($idEventoAtivo !== null) {
        define('ID_EVENTO_ATIVO', $idEventoAtivo);
    } else {
        // Se não houver um evento ativo, defina a constante como null ou um valor padrão
        define('ID_EVENTO_ATIVO', null);
        // Exemplo: header("Location: /fila/erro_sem_evento.php");
        // exit();
    }

}
/**
 * Busca o ID do evento ativo para um determinado tenant.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id_tenants O ID do tenant logado.
 * @return int|null O ID do evento ativo ou null se não for encontrado.
 */
function get_id_evento_ativo(PDO $pdo, int $id_tenants): ?int {
    try {
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE id_tenants = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$id_tenants]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($evento) {
            return (int) $evento['id'];
        } else {
            // Se nenhum evento ativo for encontrado, retorne null
            error_log("ERRO: Nenhum evento ativo encontrado para o tenant " . $id_tenants);
            return null;
        }
    } catch (PDOException $e) {
        // Trata erros de conexão ou consulta
        error_log("ERRO: Falha ao buscar evento ativo: " . $e->getMessage());
        return null;
    }
}

/**
 * Busca o ID do evento ativo para um MC específico.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $id_usuario_mc O ID do usuário MC.
 * @return int|null O ID do evento ativo ou null se não for encontrado.
 */
function get_id_evento_ativo_mc(PDO $pdo, int $id_usuario_mc): ?int {
    try {
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE id_usuario_mc = ? AND status = 'ativo' LIMIT 1");
        $stmt->execute([$id_usuario_mc]);
        $evento = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($evento) {
            return (int) $evento['id'];
        } else {
            // Se nenhum evento ativo for encontrado, retorne null
            error_log("ERRO: Nenhum evento ativo encontrado para o MC " . $id_usuario_mc);
            return null;
        }
    } catch (PDOException $e) {
        // Trata erros de conexão ou consulta
        error_log("ERRO: Falha ao buscar evento ativo do MC: " . $e->getMessage());
        return null;
    }
}



// Verificar o nível de acesso
/**
 * Verifica se o nível de acesso do usuário é suficiente.
 *
 * @param string $user_level O nível de acesso do usuário logado.
 * @param string|array $required_levels Um único nível de acesso ou um array de níveis.
 * @return bool True se o usuário tiver acesso suficiente, false caso contrário.
 */
function check_access(string $user_level, $required_levels): bool {
    // Se o usuário for super_admin, ele tem acesso a tudo
    if ($user_level === 'super_admin') {
        return true;
    }

    // Garante que a entrada seja sempre um array para facilitar a checagem
    if (!is_array($required_levels)) {
        $required_levels = [$required_levels];
    }

    // Retorna true se o nível do usuário estiver em qualquer um dos níveis necessários
    return in_array($user_level, $required_levels);
}