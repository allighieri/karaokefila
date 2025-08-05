<?php

/**
 * Adiciona ou atualiza uma regra de configuração de mesa.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int|null $id ID da regra existente (null para nova inserção).
 * @param int $minPessoas Número mínimo de pessoas para esta regra.
 * @param int|null $maxPessoas Número máximo de pessoas para esta regra (null para "ou mais").
 * @param int $maxMusicasPorRodada Número máximo de músicas permitida por rodada para esta regra.
 * @return bool|string True em caso de sucesso, ou uma string com a mensagem de erro.
 */
function adicionarOuAtualizarRegraMesa(PDO $pdo, ?int $id, int $minPessoas, ?int $maxPessoas, int $maxMusicasPorRodada)
{
    error_log("DEBUG (Regra Mesa): INÍCIO da função adicionarOuAtualizarRegraMesa.");
    error_log("DEBUG (Regra Mesa): ID (entrada): " . ($id ?? 'NULL') . ", minPessoas (nova): " . $minPessoas . ", maxPessoas (nova): " . ($maxPessoas !== null ? $maxPessoas : 'NULL') . ", maxMusicasPorRodada (nova): " . $maxMusicasPorRodada);

    // Usa a constante do tenant logado
    $id_tenants = ID_TENANTS;

    try {
        // Validação 1: max_pessoas não pode ser menor que min_pessoas na mesma regra
        if ($maxPessoas !== null && $maxPessoas < $minPessoas) {
            error_log("DEBUG (Regra Mesa): Validação 1 falhou: maxPessoas (" . $maxPessoas . ") < minPessoas (" . $minPessoas . ").");
            return "O valor de 'Máximo de Pessoas' não pode ser menor que o 'Mínimo de Pessoas' para esta regra.";
        }

        // Validação 2: Verificar sobreposição com OUTRAS regras existentes
        // Corrigido: Agora filtra pelo ID_TENANTS logado
        $sqlFetchExisting = "SELECT id, min_pessoas, max_pessoas FROM configuracao_regras_mesa WHERE id_tenants = :id_tenants";
        $paramsFetchExisting = [':id_tenants' => $id_tenants];

        if ($id !== null) { // Se estamos atualizando, excluímos a própria regra da checagem de sobreposição
            $sqlFetchExisting .= " AND id != :current_id_exclude";
            $paramsFetchExisting[':current_id_exclude'] = $id;
        }

        $stmtFetchExisting = $pdo->prepare($sqlFetchExisting);
        $stmtFetchExisting->execute($paramsFetchExisting);
        $regrasExistentes = $stmtFetchExisting->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG (Regra Mesa): Verificando sobreposição com " . count($regrasExistentes) . " regras existentes (excluindo a regra sendo atualizada, se houver).");

        $newMaxAdjusted = $maxPessoas !== null ? $maxPessoas : PHP_INT_MAX;

        foreach ($regrasExistentes as $regraExistente) {
            $existingMin = (int)$regraExistente['min_pessoas'];
            $existingMax = $regraExistente['max_pessoas'] !== null ? (int)$regraExistente['max_pessoas'] : PHP_INT_MAX;

            error_log("DEBUG (Regra Mesa): Comparando com regra existente ID " . $regraExistente['id'] . ": Min: " . $existingMin . ", Max: " . ($regraExistente['max_pessoas'] !== null ? $regraExistente['max_pessoas'] : 'NULL/Infinity') . ".");

            if ($minPessoas <= $existingMax && $newMaxAdjusted >= $existingMin) {
                // ... (O resto da lógica de formatação de erro permanece o mesmo)
                $descricaoRegraExistente = "";
                if ($regraExistente['max_pessoas'] === null) {
                    $descricaoRegraExistente = "com " . $existingMin . " ou mais pessoas";
                } elseif ($existingMin === (int)$regraExistente['max_pessoas']) {
                    $descricaoRegraExistente = "com " . $existingMin . " " . ($existingMin === 1 ? "pessoa" : "pessoas");
                } else {
                    $descricaoRegraExistente = "com " . $existingMin . " a " . $regraExistente['max_pessoas'] . " pessoas";
                }

                $descricaoNovaRegra = "";
                if ($maxPessoas === null) {
                    $descricaoNovaRegra = "com " . $minPessoas . " ou mais pessoas";
                } elseif ($minPessoas === $maxPessoas) {
                    $descricaoNovaRegra = "com " . $minPessoas . " " . ($minPessoas === 1 ? "pessoa" : "pessoas");
                } else {
                    $descricaoNovaRegra = "com " . $minPessoas . " a " . $maxPessoas . " pessoas";
                }

                $msg = "Não foi possível salvar a regra. O intervalo {$descricaoNovaRegra} já está coberto por uma regra existente {$descricaoRegraExistente}. Por favor, ajuste os valores para que não haja sobreposição.";
                error_log("DEBUG (Regra Mesa): Validação 2 falhou: Sobreposição detectada. Mensagem amigável: " . $msg);
                return $msg;
            }
        }

        // --- Lógica de INSERT/UPDATE corrigida ---
        $stmt = null;
        if ($id) {
            // Corrigido: Inclui id_tenants na cláusula WHERE para segurança
            $sql = "UPDATE configuracao_regras_mesa SET min_pessoas = :min_pessoas, max_pessoas = :max_pessoas, max_musicas_por_rodada = :max_musicas_por_rodada WHERE id = :id AND id_tenants = :id_tenants";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':id_tenants', $id_tenants, PDO::PARAM_INT); // Bind do ID do tenant
            error_log("DEBUG (Regra Mesa): Preparando UPDATE SQL para ID: " . $id);
        } else {
            // Corrigido: Inclui a coluna id_tenants no INSERT
            $sql = "INSERT INTO configuracao_regras_mesa (id_tenants, min_pessoas, max_pessoas, max_musicas_por_rodada) VALUES (:id_tenants, :min_pessoas, :max_pessoas, :max_musicas_por_rodada)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id_tenants', $id_tenants, PDO::PARAM_INT); // Bind do ID do tenant
            error_log("DEBUG (Regra Mesa): Preparando INSERT SQL.");
        }

        $stmt->bindValue(':min_pessoas', $minPessoas, PDO::PARAM_INT);
        $stmt->bindValue(':max_pessoas', $maxPessoas, PDO::PARAM_INT);
        $stmt->bindValue(':max_musicas_por_rodada', $maxMusicasPorRodada, PDO::PARAM_INT);

        $result = $stmt->execute();

        if ($result) {
            error_log("DEBUG (Regra Mesa): Operação de banco de dados (INSERT/UPDATE) bem-sucedida.");
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            $msg = "Erro desconhecido ao salvar a regra de mesa. Código SQLSTATE: " . $errorInfo[0] . ", Código Erro: " . $errorInfo[1] . ", Mensagem Erro: " . $errorInfo[2];
            error_log("ERRO (Regra Mesa): Falha no execute. Detalhes: " . $msg);
            return $msg;
        }

    } catch (PDOException $e) {
        error_log("ERRO (Regra Mesa): Exceção PDO ao adicionar/atualizar regra de mesa: " . $e->getMessage());
        return "Erro interno do servidor ao processar a regra: " . $e->getMessage();
    } finally {
        error_log("DEBUG (Regra Mesa): FIM da função adicionarOuAtualizarRegraMesa.");
    }
}


/**
 * Reseta a tabela de configuração de regras de mesa e insere regras padrão.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True em caso de sucesso, false em caso de erro.
 */
function setRegrasPadrao(PDO $pdo): bool
{
    // Usa a constante do tenant logado
    $id_tenants = ID_TENANTS;

    try {
        $pdo->beginTransaction();

        // Corrigido: Usa DELETE para remover APENAS as regras do tenant logado
        $stmtDelete = $pdo->prepare("DELETE FROM configuracao_regras_mesa WHERE id_tenants = ?");
        $stmtDelete->execute([$id_tenants]);

        // 2. Inserir as regras padrão para o tenant logado
        // Corrigido: Inclui id_tenants nos valores a serem inseridos
        $sql = "INSERT INTO `configuracao_regras_mesa` (`id_tenants`, `min_pessoas`, `max_pessoas`, `max_musicas_por_rodada`) VALUES 
                (?, 1, 2, 1),
                (?, 3, 4, 2),
                (?, 5, NULL, 3)";

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$id_tenants, $id_tenants, $id_tenants]); // Passa o ID do tenant para cada regra

        if ($result) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao definir regras padrão para o tenant " . $id_tenants . ": " . $e->getMessage());
        return false;
    }
}


/**
 * Busca e formata as regras de configuração de mesa do banco de dados.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return array Um array de strings com as regras formatadas, ou um array vazio em caso de erro.
 */
function getRegrasMesaFormatadas(PDO $pdo): array
{
    $regrasFormatadas = [];
    // Usa a constante do tenant logado
    $id_tenants = ID_TENANTS;

    try {
        // Corrigido: Adiciona a cláusula WHERE para filtrar por tenant
        $stmt = $pdo->prepare("SELECT min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa WHERE id_tenants = ? ORDER BY min_pessoas ASC");
        $stmt->execute([$id_tenants]);
        $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($regras)) {
            return ["Nenhuma regra de mesa configurada."];
        }

        foreach ($regras as $regra) {
            $min = (int)$regra['min_pessoas'];
            $max = $regra['max_pessoas'];
            $musicas = (int)$regra['max_musicas_por_rodada'];

            $descricaoPessoas = "";
            if ($max === null) {
                $descricaoPessoas = "com {$min} ou mais cantores";
            } elseif ($min === $max) {
                $descricaoPessoas = "com {$min} " . ($min === 1 ? "cantor" : "cantores");
            } else {
                $descricaoPessoas = "com {$min} a {$max} cantores";
            }

            $descricaoMusicas = "música";
            if ($musicas > 1) {
                $descricaoMusicas = "músicas";
            }

            $regrasFormatadas[] = "Mesas {$descricaoPessoas}, têm direito a {$musicas} {$descricaoMusicas} por rodada.";
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar regras de mesa para o tenant " . $id_tenants . ": " . $e->getMessage());
        return ["Erro ao carregar as regras de mesa."];
    }
    return $regrasFormatadas;
}

/**
 * Busca todas as regras de configuração de mesa do banco de dados para edição.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return array Um array de arrays associativos com as regras, ordenadas por min_pessoas.
 */
function getAllRegrasMesa(PDO $pdo): array
{
    // Usa a constante do tenant logado
    $id_tenants = ID_TENANTS;

    try {
        // Corrigido: Adiciona a cláusula WHERE para filtrar por tenant
        $stmt = $pdo->prepare("SELECT id, min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa WHERE id_tenants = ? ORDER BY min_pessoas ASC");
        $stmt->execute([$id_tenants]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("ERRO ao buscar todas as regras de mesa para o tenant " . $id_tenants . ": " . $e->getMessage());
        return [];
    }
}