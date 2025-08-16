<?php

/**
 * Registra ou atualiza o histórico de uma música cantada.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idTenants ID do tenant.
 * @param int $idEventos ID do evento.
 * @param string $codigoMusica Código da música.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function registrarHistoricoMusica(PDO $pdo, int $idTenants, int $idEventos, string $codigoMusica): bool {
    try {
        // Verifica se já existe um registro para esta combinação
        $stmtCheck = $pdo->prepare(
            "SELECT id, quantidade FROM music_history 
             WHERE id_tenants = ? AND id_eventos = ? AND codigo_musica = ?"
        );
        $stmtCheck->execute([$idTenants, $idEventos, $codigoMusica]);
        $existingRecord = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($existingRecord) {
            // Registro existe, incrementa a quantidade e atualiza updated_at
            $novaQuantidade = $existingRecord['quantidade'] + 1;
            $stmtUpdate = $pdo->prepare(
                "UPDATE music_history 
                 SET quantidade = ?, updated_at = CURRENT_TIMESTAMP 
                 WHERE id = ?"
            );
            $success = $stmtUpdate->execute([$novaQuantidade, $existingRecord['id']]);
            
            if ($success) {
                error_log("DEBUG: Histórico atualizado - Tenant: {$idTenants}, Evento: {$idEventos}, Código: {$codigoMusica}, Nova quantidade: {$novaQuantidade}");
            }
            
            return $success;
        } else {
            // Registro não existe, cria um novo
            $stmtInsert = $pdo->prepare(
                "INSERT INTO music_history (id_tenants, id_eventos, codigo_musica, quantidade, created_at, updated_at) 
                 VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $success = $stmtInsert->execute([$idTenants, $idEventos, $codigoMusica]);
            
            if ($success) {
                error_log("DEBUG: Novo histórico criado - Tenant: {$idTenants}, Evento: {$idEventos}, Código: {$codigoMusica}, Quantidade: 1");
            }
            
            return $success;
        }
    } catch (PDOException $e) {
        error_log("Erro ao registrar histórico de música: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o histórico de uma música específica.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idTenants ID do tenant.
 * @param int $idEventos ID do evento.
 * @param string $codigoMusica Código da música.
 * @return array|null Dados do histórico ou null se não encontrado.
 */
function obterHistoricoMusica(PDO $pdo, int $idTenants, int $idEventos, string $codigoMusica): ?array {
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM music_history 
             WHERE id_tenants = ? AND id_eventos = ? AND codigo_musica = ?"
        );
        $stmt->execute([$idTenants, $idEventos, $codigoMusica]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico de música: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtém o histórico completo de um evento.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idTenants ID do tenant.
 * @param int $idEventos ID do evento.
 * @param int $limite Limite de registros (padrão: 100).
 * @return array Lista do histórico ordenada por quantidade decrescente.
 */
function obterHistoricoEvento(PDO $pdo, int $idTenants, int $idEventos, int $limite = 100): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT mh.*, m.titulo, m.artista 
             FROM music_history mh
             LEFT JOIN musicas m ON mh.codigo_musica = m.codigo AND m.id_tenants = mh.id_tenants
             WHERE mh.id_tenants = ? AND mh.id_eventos = ? 
             ORDER BY mh.quantidade DESC, mh.updated_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$idTenants, $idEventos, $limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao obter histórico do evento: " . $e->getMessage());
        return [];
    }
}