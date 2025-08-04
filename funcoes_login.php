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