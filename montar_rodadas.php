<?php
/**
 * Monta a próxima rodada na tabela fila_rodadas com base nas regras de prioridade por mesa (ou por cantor no modo "cantor")
 * e nas músicas pré-selecionadas pelos cantores.
 * Cantores com todas as músicas cantadas em sua lista pré-selecionada não são reiniciados.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $modoFila O modo de organização da fila ('mesa' ou 'cantor').
 * @return bool True se a rodada foi montada, false se não houver cantores elegíveis ou músicas.
 */
function montarProximaRodada(PDO $pdo, $modoFila) {
    // REMOVIDO: A variável estática para simular o tenant foi removida.
    // Agora dependemos da constante ID_TENANTS definida em init.php.

    error_log("DEBUG: Início da função montarProximaRodada.");
    error_log("DEBUG: Modo da fila recebido: " . $modoFila);
    // Alterado: Usa a constante real do tenant
    error_log("DEBUG: ID do tenant: " . ID_TENANTS);


    // PRIMEIRO: Verificar se a rodada atual está finalizada
    // Alterado: Usa a constante ID_TENANTS
    if (!isRodadaAtualFinalizada($pdo, ID_TENANTS)) {
        error_log("INFO: Não foi possível montar a próxima rodada. A rodada atual ainda não foi finalizada para o tenant " . ID_TENANTS);
        return false;
    }

    // Alterado: Usa a constante ID_TENANTS
    $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);
    $proximaRodada = $rodadaAtual + 1;
    error_log("DEBUG: Rodada atual: " . $rodadaAtual . ", Próxima rodada: " . $proximaRodada);

    try {
        $pdo->beginTransaction();
        error_log("DEBUG: Transação iniciada.");

        // --- NOVO: Carregar regras de configuração de mesa (filtrado por tenant) ---
        $regrasConfiguracaoMesa = [];
        $stmtRegras = $pdo->prepare("SELECT min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa WHERE id_tenants = ? ORDER BY min_pessoas ASC");
        // Alterado: Usa a constante ID_TENANTS
        $stmtRegras->execute([ID_TENANTS]);
        while ($row = $stmtRegras->fetch(PDO::FETCH_ASSOC)) {
            $regrasConfiguracaoMesa[] = $row;
        }
        if (empty($regrasConfiguracaoMesa)) {
            error_log("ERRO: Nenhuma regra de configuração de mesa encontrada para o tenant " . ID_TENANTS . ". Por favor, configure as regras.");
            $pdo->rollBack();
            return false;
        }
        error_log("DEBUG: Regras de configuração de mesa carregadas: " . json_encode($regrasConfiguracaoMesa));
        // --- FIM NOVO ---

        $filaParaRodada = [];
        $ordem = 1;

        $statusMesasNaRodada = [];
        $cantoresJaNaRodadaEmConstrucao = [];

        // Obter todos os cantores e suas informações relevantes (filtrado por tenant e evento)
        $sqlTodosCantoresInfo = "
            SELECT
                t.id_cantor,
                t.nome_cantor,
                t.id_mesa,
                t.nome_mesa,
                t.tamanho_mesa,
                t.proximo_ordem_musica,
                t.total_cantos_cantor,
                t.ultima_vez_cantou_cantor,
                t.musicas_elegiveis_cantor
            FROM (
                SELECT
                    c.id AS id_cantor,
                    c.nome_cantor,
                    m.id AS id_mesa,
                    m.nome_mesa,
                    m.tamanho_mesa,
                    c.proximo_ordem_musica,
                    (SELECT COUNT(fr.id) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou' AND fr.id_tenants = :id_tenants_sub1) AS total_cantos_cantor,
                    (SELECT MAX(fr.timestamp_fim_canto) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou' AND fr.id_tenants = :id_tenants_sub2) AS ultima_vez_cantou_cantor,
                    -- CORRIGIDO: Subquery agora filtra por id_eventos
                    (SELECT COUNT(*) FROM musicas_cantor mc WHERE mc.id_cantor = c.id AND mc.status IN ('aguardando', 'pulou') AND mc.ordem_na_lista >= c.proximo_ordem_musica AND mc.id_eventos = :id_eventos_sub) AS musicas_elegiveis_cantor
                FROM cantores c
                JOIN mesas m ON c.id_mesa = m.id
                WHERE c.id_tenants = :id_tenants_c AND m.id_tenants = :id_tenants_m
            ) AS t
            ORDER BY
                t.total_cantos_cantor ASC,
                CASE WHEN t.ultima_vez_cantou_cantor IS NULL THEN 0 ELSE 1 END,
                t.ultima_vez_cantou_cantor ASC,
                t.id_cantor ASC
        ";
        $stmtTodosCantores = $pdo->prepare($sqlTodosCantoresInfo);
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtTodosCantores->execute([
            ':id_tenants_sub1' => ID_TENANTS,
            ':id_tenants_sub2' => ID_TENANTS,
            ':id_eventos_sub' => ID_EVENTO_ATIVO,
            ':id_tenants_c' => ID_TENANTS,
            ':id_tenants_m' => ID_TENANTS
        ]);
        $cantoresDisponiveisGlobal = $stmtTodosCantores->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cantoresDisponiveisGlobal)) {
            $pdo->rollBack();
            error_log("INFO: Não há cantores cadastrados para montar a rodada para o tenant " . ID_TENANTS);
            return false;
        }

        $cantoresPorMesa = [];
        $cantoresMap = [];
        foreach ($cantoresDisponiveisGlobal as $cantor) {
            $mesaId = $cantor['id_mesa'];
            if (!isset($cantoresPorMesa[$mesaId])) {
                $cantoresPorMesa[$mesaId] = [];
            }
            $cantoresPorMesa[$mesaId][] = $cantor;
            $cantoresMap[$cantor['id_cantor']] = $cantor;

            if (!isset($statusMesasNaRodada[$mesaId])) {
                $statusMesasNaRodada[$mesaId] = [
                    'musicas_adicionadas_nesta_rodada' => 0,
                    'last_picked_timestamp' => 0,
                    'tamanho_mesa' => $cantor['tamanho_mesa']
                ];
            }
        }

        $maxLoopIterations = count($cantoresDisponiveisGlobal) * 3 + count(array_keys($statusMesasNaRodada)) * 3;
        $currentLoopIteration = 0;

        error_log("DEBUG: Iniciando loop de montagem da fila. Max Iterations: " . $maxLoopIterations);

        while (true) {
            $currentLoopIteration++;
            if ($currentLoopIteration > $maxLoopIterations) {
                error_log("AVISO: Limite de iterações do loop principal atingido. Pode haver músicas não adicionadas. Considere aumentar maxLoopIterations.");
                break;
            }

            $mesaMaisPrioritariaId = null;
            $eligibleMesasWithScores = [];

            foreach ($statusMesasNaRodada as $mesaId => $status) {
                $podeAddMesa = false;
                $maxMusicasMesa = 0;

                if ($modoFila === "mesa") {
                    foreach ($regrasConfiguracaoMesa as $regra) {
                        if ($status['tamanho_mesa'] >= $regra['min_pessoas'] &&
                            ($regra['max_pessoas'] === null || $status['tamanho_mesa'] <= $regra['max_pessoas'])) {
                            $maxMusicasMesa = $regra['max_musicas_por_rodada'];
                            break;
                        }
                    }
                    if ($maxMusicasMesa > 0) {
                        $podeAddMesa = ($status['musicas_adicionadas_nesta_rodada'] < $maxMusicasMesa);
                    } else {
                        error_log("AVISO: Nenhuma regra de configuração de mesa encontrada para o tamanho da mesa " . $status['tamanho_mesa'] . ". Mesa ID: " . $mesaId);
                        continue;
                    }
                } elseif ($modoFila === "cantor") {
                    $podeAddMesa = true;
                }

                $hasElegibleCantorInMesa = false;
                foreach ($cantoresPorMesa[$mesaId] ?? [] as $cantor) {
                    if ($cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao)) {
                        $hasElegibleCantorInMesa = true;
                        break;
                    }
                }

                if ($podeAddMesa && $hasElegibleCantorInMesa) {
                    $score = 0;
                    $score -= $status['last_picked_timestamp'];
                    if ($modoFila === "mesa") {
                        $score -= ($status['musicas_adicionadas_nesta_rodada'] * 1000);
                    }
                    $eligibleMesasWithScores[$mesaId] = $score;
                }
            }

            if (empty($eligibleMesasWithScores)) {
                error_log("DEBUG: Nenhuma mesa possui slots disponíveis (modo mesa) ou cantores elegíveis (modo cantor) que ainda não cantaram nesta rodada. Quebrando o loop de montagem da rodada.");
                break;
            }

            $mesaMaisPrioritariaId = array_keys($eligibleMesasWithScores, max($eligibleMesasWithScores))[0];
            $idMesaSelecionada = $mesaMaisPrioritariaId;
            error_log("DEBUG: Mesa selecionada para adicionar música: " . $idMesaSelecionada . " (Score: " . $eligibleMesasWithScores[$idMesaSelecionada] . ")");

            $cantoresDaMesa = $cantoresPorMesa[$idMesaSelecionada] ?? [];

            $cantoresElegiveisParaMesa = array_filter($cantoresDaMesa, function($cantor) use ($cantoresJaNaRodadaEmConstrucao) {
                return $cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao);
            });

            if (empty($cantoresElegiveisParaMesa)) {
                error_log("INFO: Mesa " . $idMesaSelecionada . " selecionada, mas nenhum cantor elegível encontrado nesta mesa que ainda não tenha sido selecionado para esta rodada. Pulando para a próxima iteração.");
                $statusMesasNaRodada[$idMesaSelecionada]['last_picked_timestamp'] = microtime(true);
                continue;
            }

            usort($cantoresElegiveisParaMesa, function($a, $b) {
                if ($a['total_cantos_cantor'] !== $b['total_cantos_cantor']) {
                    return $a['total_cantos_cantor'] - $b['total_cantos_cantor'];
                }
                if ($a['ultima_vez_cantou_cantor'] === null && $b['ultima_vez_cantou_cantor'] !== null) return -1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] === null) return 1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_cantor']) - strtotime($b['ultima_vez_cantou_cantor']);
                    if ($cmp !== 0) return $cmp;
                }
                return $a['id_cantor'] - $b['id_cantor'];
            });

            $cantorSelecionado = array_shift($cantoresElegiveisParaMesa);
            error_log("DEBUG: Cantor selecionado na mesa " . $idMesaSelecionada . ": " . $cantorSelecionado['nome_cantor'] . " (ID: " . $cantorSelecionado['id_cantor'] . ")");

            $idCantor = $cantorSelecionado['id_cantor'];
            $currentProximoOrdemMusica = $cantorSelecionado['proximo_ordem_musica'];

            $sqlProximaMusicaCantor = "
                SELECT
                    mc.id AS musica_cantor_id,
                    mc.id_musica,
                    mc.ordem_na_lista
                FROM musicas_cantor mc
                JOIN cantores c ON mc.id_cantor = c.id
                WHERE mc.id_cantor = :id_cantor
                -- CORRIGIDO: Adicionado filtro por id_eventos
                AND mc.id_eventos = :id_eventos
                AND mc.ordem_na_lista >= :proximo_ordem_musica
                AND mc.status IN ('aguardando', 'pulou')
                ORDER BY mc.ordem_na_lista ASC
                LIMIT 1
            ";
            $stmtProximaMusica = $pdo->prepare($sqlProximaMusicaCantor);
            // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
            $stmtProximaMusica->execute([
                ':id_cantor' => $idCantor,
                ':id_eventos' => ID_EVENTO_ATIVO,
                ':proximo_ordem_musica' => $currentProximoOrdemMusica
            ]);
            $musicaData = $stmtProximaMusica->fetch(PDO::FETCH_ASSOC);

            $musicaId = $musicaData ? $musicaData['id_musica'] : null;
            $musicaCantorId = $musicaData ? $musicaData['musica_cantor_id'] : null;
            $ordemMusicaSelecionada = $musicaData ? $musicaData['ordem_na_lista'] : null;

            if (!$musicaId || !$musicaCantorId) {
                error_log("INFO: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") não possui mais músicas disponíveis (status 'aguardando' ou 'pulou') em sua lista na ordem esperada. Isso não deveria acontecer se musicas_elegiveis_cantor > 0.");
                $cantoresMap[$idCantor]['musicas_elegiveis_cantor'] = 0;
                foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                    if ($mesaCantor['id_cantor'] === $idCantor) {
                        $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor'] = 0;
                        break;
                    }
                }
                $cantoresJaNaRodadaEmConstrucao[] = $idCantor;
                continue;
            }

            $filaParaRodada[] = [
                'id_cantor' => $idCantor,
                'id_musica' => $musicaId,
                'musica_cantor_id' => $musicaCantorId,
                'ordem_na_rodada' => $ordem++,
                'rodada' => $proximaRodada,
                'id_mesa' => $idMesaSelecionada,
                'id_tenants' => ID_TENANTS, // Adiciona a constante ao array
            ];

            // CORRIGIDO: Adicionado filtro por id_eventos
            $stmtUpdateMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $stmtUpdateMusicaCantorStatus->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Status da música_cantor_id " . $musicaCantorId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");

            $statusMesasNaRodada[$idMesaSelecionada]['musicas_adicionadas_nesta_rodada']++;
            $statusMesasNaRodada[$idMesaSelecionada]['last_picked_timestamp'] = microtime(true);
            $cantoresJaNaRodadaEmConstrucao[] = $idCantor;

            $novaProximaOrdem = $ordemMusicaSelecionada + 1;
            // CORRIGIDO: Adicionado filtro por id_tenants
            $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ? AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $stmtUpdateCantorOrder->execute([$novaProximaOrdem, $idCantor, ID_TENANTS]);
            error_log("DEBUG: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") próxima ordem atualizada no DB para: " . $novaProximaOrdem);

            $cantoresMap[$idCantor]['proximo_ordem_musica'] = $novaProximaOrdem;
            $cantoresMap[$idCantor]['musicas_elegiveis_cantor']--;
            foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                if ($mesaCantor['id_cantor'] === $idCantor) {
                    $cantoresPorMesa[$idMesaSelecionada][$key]['proximo_ordem_musica'] = $novaProximaOrdem;
                    $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor']--;
                    break;
                }
            }

            $canAddMoreSongsToAnyMesa = false;
            foreach ($statusMesasNaRodada as $mesaId => $status) {
                $mesaPodeAddPeloModo = false;
                $maxMusicasMesaCheck = 0;

                if ($modoFila === "mesa") {
                    foreach ($regrasConfiguracaoMesa as $regra) {
                        if ($status['tamanho_mesa'] >= $regra['min_pessoas'] &&
                            ($regra['max_pessoas'] === null || $status['tamanho_mesa'] <= $regra['max_pessoas'])) {
                            $maxMusicasMesaCheck = $regra['max_musicas_por_rodada'];
                            break;
                        }
                    }
                    if ($maxMusicasMesaCheck > 0) {
                        $mesaPodeAddPeloModo = ($status['musicas_adicionadas_nesta_rodada'] < $maxMusicasMesaCheck);
                    }
                } elseif ($modoFila === "cantor") {
                    $mesaPodeAddPeloModo = true;
                }

                if ($mesaPodeAddPeloModo) {
                    $cantoresComMusicasElegiveisENaoNaRodada = array_filter($cantoresPorMesa[$mesaId] ?? [], function($c) use ($cantoresJaNaRodadaEmConstrucao) {
                        return $c['musicas_elegiveis_cantor'] > 0 && !in_array($c['id_cantor'], $cantoresJaNaRodadaEmConstrucao);
                    });
                    if (!empty($cantoresComMusicasElegiveisENaoNaRodada)) {
                        $canAddMoreSongsToAnyMesa = true;
                        break;
                    }
                }
            }

            if (!$canAddMoreSongsToAnyMesa) {
                error_log("DEBUG: Nenhuma mesa tem mais slots (modo mesa) ou cantores elegíveis que ainda não cantaram nesta rodada (modo cantor). Quebrando o loop de montagem.");
                break;
            }

        }
        error_log("DEBUG: Fim do loop de montagem da fila. Itens na filaParaRodada: " . count($filaParaRodada));

        if (empty($filaParaRodada)) {
            $pdo->rollBack();
            error_log("DEBUG: filaParaRodada está vazia após o loop. Rollback e retorno false. Pode ser que não haja cantores com músicas disponíveis para cantar ou que já atingiram o limite.");
            return false;
        }

        // --- Limpa a fila antiga antes de inserir a nova rodada (filtrado por tenant) ---
        $stmtDeleteOldQueue = $pdo->prepare("DELETE FROM fila_rodadas WHERE rodada < ? AND status = 'aguardando' AND id_tenants = ?");
        // Alterado: Usa a constante ID_TENANTS
        $stmtDeleteOldQueue->execute([$proximaRodada, ID_TENANTS]);
        error_log("DEBUG: Fila_rodadas antigas (status 'aguardando') limpas para rodadas anteriores a " . $proximaRodada . " para o tenant " . ID_TENANTS);

        // Inserir todas as músicas geradas na tabela fila_rodadas
        $firstItem = true;
        foreach ($filaParaRodada as $item) {
            $status = 'aguardando';
            $timestamp_inicio_canto = 'NULL';

            if ($firstItem) {
                $status = 'em_execucao';
                $timestamp_inicio_canto = 'NOW()';
                $firstItem = false;

                // CORRIGIDO: Adicionado filtro por id_eventos
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ? AND id_eventos = ?");
                // Alterado: Usa a constante ID_EVENTO_ATIVO
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id'], ID_EVENTO_ATIVO]);
                error_log("DEBUG: Status da primeira música (musica_cantor_id: " . $item['musica_cantor_id'] . ") atualizado para 'em_execucao' na tabela musicas_cantor.");
            }

            // --- NOVO: Query INSERT agora inclui id_tenants ---
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_tenants, id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, id_mesa, status, timestamp_adicao, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), " . $timestamp_inicio_canto . ")");
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", Música " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem TEMP " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Mesa " . $item['id_mesa'] . ", Status " . $status . ", Tenant " . $item['id_tenants']);
            $stmtInsert->execute([$item['id_tenants'], $item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $item['id_mesa'], $status]);
        }
        error_log("DEBUG: Itens temporariamente inseridos na fila_rodadas.");

        // CHAMADA CORRETA DA FUNÇÃO DE REORDENAÇÃO AQUI
        error_log("DEBUG: Chamando reordenarFilaParaIntercalarMesas para a rodada: " . $proximaRodada);
        // A função reordenarFilaParaIntercalarMesas também precisa receber o id_tenants
        // Alterado: Usa a constante ID_TENANTS
        if (!reordenarFilaParaIntercalarMesas($pdo, $proximaRodada, ID_TENANTS)) {
            $pdo->rollBack();
            error_log("ERRO: Falha ao reordenar a fila da Rodada " . $proximaRodada . " para o tenant " . ID_TENANTS . ". Rollback da transação de montagem.");
            return false;
        }
        error_log("DEBUG: reordenarFilaParaIntercalarMesas concluída.");

        // Atualiza o controle de rodada
        $stmtCheckControl = $pdo->prepare("SELECT COUNT(*) FROM controle_rodada WHERE id = 1 AND id_tenants = ?");
        // Alterado: Usa a constante ID_TENANTS
        $stmtCheckControl->execute([ID_TENANTS]);
        if ($stmtCheckControl->fetchColumn() == 0) {
            $stmtInsertControl = $pdo->prepare("INSERT INTO controle_rodada (id, id_tenants, rodada_atual) VALUES (1, ?, ?)");
            // Alterado: Usa a constante ID_TENANTS
            $stmtInsertControl->execute([ID_TENANTS, $proximaRodada]);
            error_log("DEBUG: controle_rodada inserido com a rodada " . $proximaRodada . " para o tenant " . ID_TENANTS);
        } else {
            $stmtUpdateRodada = $pdo->prepare("UPDATE controle_rodada SET rodada_atual = ? WHERE id = 1 AND id_tenants = ?");
            // Alterado: Usa a constante ID_TENANTS
            $stmtUpdateRodada->execute([$proximaRodada, ID_TENANTS]);
            error_log("DEBUG: controle_rodada atualizado para a rodada " . $proximaRodada . " para o tenant " . ID_TENANTS);
        }

        $pdo->commit();
        error_log("DEBUG: Transação commitada. Retornando true.");
        return true;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: Erro ao montar próxima rodada (PDOException): " . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: Erro ao montar próxima rodada (Exception): " . $e->getMessage());
        return false;
    }
}