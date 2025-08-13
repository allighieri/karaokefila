<?php
/**
 * Funções para gerenciamento de repertório
 * 
 * Este arquivo contém todas as funções relacionadas à importação e manipulação
 * de repertórios musicais no sistema de karaokê.
 */

/**
 * Importa um repertório de músicas para o banco de dados.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param array $musicas Array com os dados das músicas a serem importadas.
 * @param int $idTenants ID do tenant para o qual as músicas serão importadas.
 * @return array Um array com 'success' (bool), 'message' (string), 'estatisticas' (array) e opcionalmente 'erros' (array).
 */
function importarRepertorio(PDO $pdo, array $musicas, int $idTenants): array {
    try {
        $pdo->beginTransaction();
        
        $musicasInseridas = 0;
        $musicasIgnoradas = 0;
        $erros = [];
        
        // Preparar consultas
        $stmtCheck = $pdo->prepare("SELECT id FROM musicas WHERE id_tenants = ? AND codigo = ?");
        $stmtInsert = $pdo->prepare("
            INSERT INTO musicas (id_tenants, codigo, titulo, artista, trecho, idioma) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($musicas as $index => $musica) {
            try {
                // Validar dados obrigatórios
                if (empty($musica['codigo']) || empty($musica['titulo'])) {
                    $erros[] = "Linha " . ($index + 1) . ": código ou título em branco";
                    continue;
                }
                
                // Limitar tamanhos dos campos
                $codigo = (int)$musica['codigo'];
                $titulo = substr(trim($musica['titulo']), 0, 255);
                $artista = isset($musica['artista']) ? substr(trim($musica['artista']), 0, 255) : null;
                $trecho = isset($musica['trecho']) ? trim($musica['trecho']) : '';
                $idioma = isset($musica['idioma']) ? substr(trim($musica['idioma']), 0, 50) : 'Português';
                
                // Verificar se a música já existe
                $stmtCheck->execute([$idTenants, $codigo]);
                $musicaExistente = $stmtCheck->fetch();
                
                if ($musicaExistente) {
                    // Pular música duplicada
                    $musicasIgnoradas++;
                } else {
                    // Inserir nova música
                    $stmtInsert->execute([
                        $idTenants,
                        $codigo,
                        $titulo,
                        $artista,
                        $trecho,
                        $idioma
                    ]);
                    $musicasInseridas++;
                }
                
            } catch (PDOException $e) {
                $erros[] = "Linha " . ($index + 1) . ": " . $e->getMessage();
            }
        }
        
        $pdo->commit();
        
        // Montar mensagem de resultado
        $mensagens = [];
        
        if ($musicasInseridas > 0) {
            $mensagens[] = "$musicasInseridas música(s) inserida(s)";
        }
        
        if ($musicasIgnoradas > 0) {
            $mensagens[] = "$musicasIgnoradas música(s) ignorada(s) (códigos já existentes)";
        }
        
        if (!empty($erros)) {
            $mensagens[] = count($erros) . " erro(s) encontrado(s)";
        }
        
        $response = [
            'success' => true,
            'message' => 'Importação concluída: ' . implode(', ', $mensagens) . '.',
            'estatisticas' => [
                'inseridas' => $musicasInseridas,
                'ignoradas' => $musicasIgnoradas,
                'erros' => count($erros)
            ]
        ];
        
        if (!empty($erros)) {
            $response['erros'] = $erros;
        }
        
        return $response;
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao importar repertório: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor ao importar repertório.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro geral ao importar repertório: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro ao processar importação: ' . $e->getMessage()];
    }
}

/**
 * Valida os dados de uma música antes da importação.
 *
 * @param array $musica Array com os dados da música.
 * @return array Um array com 'valid' (bool) e 'errors' (array).
 */
function validarDadosMusica(array $musica): array {
    $errors = [];
    
    // Verificar campos obrigatórios
    if (empty($musica['codigo'])) {
        $errors[] = 'Código é obrigatório';
    }
    
    if (empty($musica['titulo'])) {
        $errors[] = 'Título é obrigatório';
    }
    
    // Verificar tipos de dados
    if (!empty($musica['codigo']) && !is_numeric($musica['codigo'])) {
        $errors[] = 'Código deve ser numérico';
    }
    
    // Verificar tamanhos máximos
    if (!empty($musica['titulo']) && strlen($musica['titulo']) > 255) {
        $errors[] = 'Título muito longo (máximo 255 caracteres)';
    }
    
    if (!empty($musica['artista']) && strlen($musica['artista']) > 255) {
        $errors[] = 'Artista muito longo (máximo 255 caracteres)';
    }
    
    if (!empty($musica['idioma']) && strlen($musica['idioma']) > 50) {
        $errors[] = 'Idioma muito longo (máximo 50 caracteres)';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Verifica se um código de música já existe para um tenant.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $idTenants ID do tenant.
 * @param int $codigo Código da música.
 * @return bool True se o código já existe, false caso contrário.
 */
function codigoMusicaExiste(PDO $pdo, int $idTenants, int $codigo): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM musicas WHERE id_tenants = ? AND codigo = ?");
        $stmt->execute([$idTenants, $codigo]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erro ao verificar código da música: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém estatísticas do repertório de um tenant.
 *
 * @param PDO $pdo Objeto de conexão com o banco de dados.
 * @param int $idTenants ID do tenant.
 * @return array Array com as estatísticas do repertório.
 */
function obterEstatisticasRepertorio(PDO $pdo, int $idTenants): array {
    try {
        $stats = [];
        
        // Total de músicas
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM musicas WHERE id_tenants = ?");
        $stmt->execute([$idTenants]);
        $stats['total_musicas'] = $stmt->fetchColumn();
        
        // Músicas por idioma
        $stmt = $pdo->prepare("
            SELECT idioma, COUNT(*) as quantidade 
            FROM musicas 
            WHERE id_tenants = ? 
            GROUP BY idioma 
            ORDER BY quantidade DESC
        ");
        $stmt->execute([$idTenants]);
        $stats['por_idioma'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Artistas mais frequentes
        $stmt = $pdo->prepare("
            SELECT artista, COUNT(*) as quantidade 
            FROM musicas 
            WHERE id_tenants = ? AND artista IS NOT NULL AND artista != '' 
            GROUP BY artista 
            ORDER BY quantidade DESC 
            LIMIT 10
        ");
        $stmt->execute([$idTenants]);
        $stats['artistas_frequentes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
        
    } catch (PDOException $e) {
        error_log("Erro ao obter estatísticas do repertório: " . $e->getMessage());
        return [];
    }
}
?>