<?php
session_start();

require_once 'conn.php';
$current_page = pathinfo($_SERVER['PHP_SELF'], PATHINFO_BASENAME);
$rootPath = '/fila/';

//paginas permitidas sem login
$public_pages = [
    'index.php',
    'conn.php', // O arquivo de conexão
    'api.php' // O endpoint da sua API
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
    define('NIVEL_ACESSO', $dados_sessao['usuario']['nivel']);
    define('NOME_USUARIO', $dados_sessao['usuario']['nome']);
    define('NOME_TENANT', $dados_sessao['tenant']['nome']);

    $idEventoAtivo = get_id_evento_ativo($pdo, ID_TENANTS);

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
        $stmt = $pdo->prepare("SELECT id FROM eventos WHERE id_tenants = ? AND status = 1 LIMIT 1");
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



// Verificar o nível de acesso
/**
 * Verifica se o nível de acesso do usuário é suficiente.
 *
 * @param string $user_level O nível de acesso do usuário logado.
 * @param string|array $required_levels Um único nível de acesso ou um array de níveis.
 * @return bool True se o usuário tiver acesso suficiente, false caso contrário.
 */
function check_access(string $user_level, $required_levels): bool {
    // Se o usuário for admin, ele tem acesso a tudo
    if ($user_level === 'admin') {
        return true;
    }

    // Garante que a entrada seja sempre um array para facilitar a checagem
    if (!is_array($required_levels)) {
        $required_levels = [$required_levels];
    }

    // Retorna true se o nível do usuário estiver em qualquer um dos níveis necessários
    return in_array($user_level, $required_levels);
}