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
function adicionarOuAtualizarRegraMesa(PDO $pdo, ?int $id, int $minPessoas, ?int $maxPessoas, int $maxMusicasPorRodada) // Retorna bool|string para erros de validação
{
    error_log("DEBUG (Regra Mesa): INÍCIO da função adicionarOuAtualizarRegraMesa.");
    error_log("DEBUG (Regra Mesa): ID (entrada): " . ($id ?? 'NULL') . ", minPessoas (nova): " . $minPessoas . ", maxPessoas (nova): " . ($maxPessoas !== null ? $maxPessoas : 'NULL') . ", maxMusicasPorRodada (nova): " . $maxMusicasPorRodada);

    try {
        // Validação 1: max_pessoas não pode ser menor que min_pessoas na mesma regra
        if ($maxPessoas !== null && $maxPessoas < $minPessoas) {
            error_log("DEBUG (Regra Mesa): Validação 1 falhou: maxPessoas (" . $maxPessoas . ") < minPessoas (" . $minPessoas . ").");
            return "O valor de 'Máximo de Pessoas' não pode ser menor que o 'Mínimo de Pessoas' para esta regra.";
        }

        // Validação 2: Verificar sobreposição com OUTRAS regras existentes
        // Pega todas as regras, exceto a que pode estar sendo editada (se $id não for NULL)
        $sqlFetchExisting = "SELECT id, min_pessoas, max_pessoas FROM configuracao_regras_mesa";
        $paramsFetchExisting = [];
        if ($id !== null) { // Se estamos atualizando, excluímos a própria regra da checagem de sobreposição
            $sqlFetchExisting .= " WHERE id != :current_id_exclude";
            $paramsFetchExisting[':current_id_exclude'] = $id;
        }

        $stmtFetchExisting = $pdo->prepare($sqlFetchExisting);
        $stmtFetchExisting->execute($paramsFetchExisting);
        $regrasExistentes = $stmtFetchExisting->fetchAll(PDO::FETCH_ASSOC);

        error_log("DEBUG (Regra Mesa): Verificando sobreposição com " . count($regrasExistentes) . " regras existentes (excluindo a regra sendo atualizada, se houver).");

        $newMaxAdjusted = $maxPessoas !== null ? $maxPessoas : PHP_INT_MAX; // Tratar NULL da nova regra como "infinito"

        foreach ($regrasExistentes as $regraExistente) {
            $existingMin = (int)$regraExistente['min_pessoas'];
            $existingMax = $regraExistente['max_pessoas'] !== null ? (int)$regraExistente['max_pessoas'] : PHP_INT_MAX; // Tratar NULL da regra existente como "infinito"

            error_log("DEBUG (Regra Mesa): Comparando com regra existente ID " . $regraExistente['id'] . ": Min: " . $existingMin . ", Max: " . ($regraExistente['max_pessoas'] !== null ? $regraExistente['max_pessoas'] : 'NULL/Infinity') . ".");

            // Condição de sobreposição: os intervalos [minPessoas, newMaxAdjusted] e [existingMin, existingMax] se cruzam.
            if ($minPessoas <= $existingMax && $newMaxAdjusted >= $existingMin) {
                // Formata a descrição da regra existente para a mensagem de erro
                $descricaoRegraExistente = "";
                if ($regraExistente['max_pessoas'] === null) {
                    $descricaoRegraExistente = "com " . $existingMin . " ou mais pessoas";
                } elseif ($existingMin === (int)$regraExistente['max_pessoas']) {
                    $descricaoRegraExistente = "com " . $existingMin . " " . ($existingMin === 1 ? "pessoa" : "pessoas");
                } else {
                    $descricaoRegraExistente = "com " . $existingMin . " a " . $regraExistente['max_pessoas'] . " pessoas";
                }

                // Formata a descrição da nova regra para a mensagem de erro
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
        
        // --- Lógica de INSERT/UPDATE (agora usando o $id passado como parâmetro) ---
        $stmt = null;
        if ($id) {
            // Se ID fornecido, é uma atualização
            $sql = "UPDATE configuracao_regras_mesa SET min_pessoas = :min_pessoas, max_pessoas = :max_pessoas, max_musicas_por_rodada = :max_musicas_por_rodada WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            error_log("DEBUG (Regra Mesa): Preparando UPDATE SQL para ID: " . $id);
        } else {
            // Se ID não fornecido, é uma inserção
            $sql = "INSERT INTO configuracao_regras_mesa (min_pessoas, max_pessoas, max_musicas_por_rodada) VALUES (:min_pessoas, :max_pessoas, :max_musicas_por_rodada)";
            $stmt = $pdo->prepare($sql);
            error_log("DEBUG (Regra Mesa): Preparando INSERT SQL.");
        }
        
        $stmt->bindValue(':min_pessoas', $minPessoas, PDO::PARAM_INT);
        $stmt->bindValue(':max_pessoas', $maxPessoas, PDO::PARAM_INT); // Pode ser NULL
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
    try {
        // 1. Truncate na tabela para remover todas as regras existentes
        // MOVIDO PARA FORA DA TRANSAÇÃO, POIS TRUNCATE FAZ COMMIT IMPLICITAMENTE
        $pdo->exec("TRUNCATE TABLE configuracao_regras_mesa");

        // Agora sim, inicia a transação para proteger os INSERTs
        $pdo->beginTransaction();

        // 2. Inserir as regras padrão
        $sql = "INSERT INTO `configuracao_regras_mesa` (`min_pessoas`, `max_pessoas`, `max_musicas_por_rodada`) VALUES 
                (1, 2, 1),
                (3, 4, 2),
                (5, NULL, 3)"; // NULL para o campo max_pessoas quando é 'ou mais'

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute();

        if ($result) {
            $pdo->commit();
            return true;
        } else {
            // Se o INSERT falhar, faz rollback da transação (que está ativa)
            $pdo->rollBack();
            return false;
        }

    } catch (PDOException $e) {
        // Verifica se há uma transação ativa antes de tentar um rollback
        // Isso é uma medida de segurança, pois se o erro ocorrer antes do beginTransaction, não haverá transação.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao definir regras padrão: " . $e->getMessage());
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
    try {
        $stmt = $pdo->query("SELECT min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa ORDER BY min_pessoas ASC");
        $regras = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($regras)) {
            return ["Nenhuma regra de mesa configurada."];
        }

        foreach ($regras as $regra) {
            $min = (int)$regra['min_pessoas'];
            $max = $regra['max_pessoas']; // Pode ser NULL
            $musicas = (int)$regra['max_musicas_por_rodada'];

            $descricaoPessoas = "";
            if ($max === null) {
                $descricaoPessoas = "com {$min} ou mais cantores";
            } elseif ($min === $max) {
                // Se min e max são iguais, trata como um número específico (singular ou plural)
                $descricaoPessoas = "com {$min} " . ($min === 1 ? "cantor" : "cantores");
            } else {
                // Caso geral: min e max são diferentes e não nulos. Use "a"
                $descricaoPessoas = "com {$min} a {$max} cantores";
            }
            
            $descricaoMusicas = "música";
            if ($musicas > 1) {
                $descricaoMusicas = "músicas";
            }

            $regrasFormatadas[] = "Mesas {$descricaoPessoas}, têm direito a {$musicas} {$descricaoMusicas} por rodada.";
        }

    } catch (PDOException $e) {
        error_log("Erro ao buscar regras de mesa: " . $e->getMessage());
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
    try {
        $stmt = $pdo->query("SELECT id, min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa ORDER BY min_pessoas ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("ERRO ao buscar todas as regras de mesa: " . $e->getMessage());
        return [];
    }
}
?>