<?php
// A função agora recebe o código de acesso em vez do ID do tenant
function cadastrarUsuario(PDO $pdo, $nome, $email, $telefone, $cidade, $uf, $senha_hash, $code) {
    // Validação no lado do servidor
    if (empty($nome) || empty($email) || empty($telefone) || empty($cidade) || empty($uf) || empty($senha_hash) || empty($code)) {
        return ['success' => false, 'message' => 'Todos os campos são obrigatórios.'];
    }

    try {
        // 1. Busca o ID do tenant a partir do código
        $stmt_code = $pdo->prepare("SELECT id_tenants FROM tenant_codes WHERE code = ? AND status = 'active'");
        $stmt_code->execute([$code]);
        $result_code = $stmt_code->fetch(PDO::FETCH_ASSOC);

        // 2. Se o código não for encontrado ou não estiver ativo, retorna um erro
        if (!$result_code) {
            return ['success' => false, 'message' => 'Código de acesso inválido ou expirado.'];
        }

        $idTenants = $result_code['id_tenants'];

        // 3. Verifica se o e-mail já existe para o mesmo tenant
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? AND id_tenants = ?");
        $stmt_check->execute([$email, $idTenants]);
        if ($stmt_check->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'E-mail já cadastrado para esse usuário.'];
        }

        // 4. Insere o novo usuário no banco de dados
        $stmt_insert = $pdo->prepare("INSERT INTO usuarios (id_tenants, nome, email, password, telefone, cidade, uf, nivel, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'user', 1)");
        $stmt_insert->execute([
            $idTenants,
            $nome,
            $email,
            $senha_hash,
            $telefone,
            $cidade,
            $uf
        ]);

        return ['success' => true, 'message' => 'Usuário cadastrado com sucesso!'];

    } catch (PDOException $e) {
        error_log("Erro ao cadastrar usuário: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor. Tente novamente mais tarde.'];
    }
}

function validar_codigo(PDO $pdo, $codigo) {
    // A consulta agora verifica apenas o código e seu status
    $stmt = $pdo->prepare("SELECT status FROM tenant_codes WHERE code = ?");
    $stmt->execute([$codigo]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if ($result['status'] === 'active') {
            return ["success" => true, "message" => "Código válido."];
        } else {
            return ["success" => false, "message" => "O código de acesso expirou."];
        }
    } else {
        // Código não encontrado
        return ["success" => false, "message" => "O código de acesso é inválido."];
    }
}

// Função para sessao
/**
 * Função para buscar um usuário e seu tenant para login.
 * @param string $email O e-mail do usuário.
 * @param string $senha A senha do usuário.
 * @return bool Retorna true se o login for bem-sucedido, caso contrário false.
 */
function logar_usuario($email, $senha) {
    global $pdo; // Assume que a conexão PDO está disponível globalmente

    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.*, 
                t.nome AS tenant_nome, 
                t.email AS tenant_email,
                t.endereco AS tenant_endereco,
                t.cidade AS tenant_cidade,
                t.uf AS tenant_uf,
                t.status AS tenant_status
            FROM usuarios u
            INNER JOIN tenants t ON u.id_tenants = t.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se o usuário não for encontrado
        if (!$resultado) {
            return 'E-mail ou senha inválidos.';
        }

        // Se a senha estiver incorreta
        if (!password_verify($senha, $resultado['password'])) {
            return 'E-mail ou senha inválidos.';
        }

        // Verifica se o status do usuário está ativo
        if ($resultado['status'] != 1) {
            return 'Usuário inativo. Entre em contato com o suporte.';
        }

        // Verifica se o status do tenant está ativo
        if ($resultado['tenant_status'] != 1) {
            return $resultado['tenant_nome'].' inativo. Entre em contato com o suporte.';
        }

        // Separa os dados do usuário e do tenant no objeto de sessão
        $sessao = [
            'usuario' => [
                'id' => $resultado['id'],
                'id_tenants' => $resultado['id_tenants'],
                'nome' => $resultado['nome'],
                'email' => $resultado['email'],
                'telefone' => $resultado['telefone'],
                'cidade' => $resultado['cidade'],
                'uf' => $resultado['uf'],
                'status' => $resultado['status'],
                'nivel' => $resultado['nivel']
            ],
            'tenant' => [
                'id' => $resultado['id_tenants'],
                'nome' => $resultado['tenant_nome'],
                'email' => $resultado['tenant_email'],
                'endereco' => $resultado['tenant_endereco'],
                'cidade' => $resultado['tenant_cidade'],
                'uf' => $resultado['tenant_uf'],
                'status' => $resultado['tenant_status']
            ]
        ];

        // Armazena o objeto na sessão
        $_SESSION['usuario_logado'] = $sessao;

        return 'success';

    } catch (PDOException $e) {
        // Em caso de erro no banco de dados
        return false;
    }
}

// Lógica de logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: ../index.php");
    exit();
}