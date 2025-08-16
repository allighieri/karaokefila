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

    // PRIMEIRO: Verificar se há um evento ativo
    if (ID_EVENTO_ATIVO === null) {
        $mensagemErro = "Você não tem nenhum evento ativo! <a href='#' data-bs-toggle='modal' data-bs-target='#modalEventos'>Ativar Evento</a>";
        error_log("ERRO: " . strip_tags($mensagemErro));
        return $mensagemErro;
    }

    // SEGUNDO: Verificar se a rodada atual está finalizada
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
            $mensagemErro = "Parece que você não definiu as regras para quantidade de música por mesa por rodada!";
            error_log("ERRO: " . $mensagemErro);
            $pdo->rollBack();
            return $mensagemErro; // Retorna a mensagem de erro
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
                    u.nome AS nome_cantor,
                    m.id AS id_mesa,
                    m.nome_mesa,
                    m.tamanho_mesa,
                    c.proximo_ordem_musica,
                    (SELECT COUNT(fr.id) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou' AND fr.id_tenants = :id_tenants_sub1 AND fr.id_eventos = :id_eventos_sub1) AS total_cantos_cantor,
                    (SELECT MAX(fr.timestamp_fim_canto) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou' AND fr.id_tenants = :id_tenants_sub2 AND fr.id_eventos = :id_eventos_sub2) AS ultima_vez_cantou_cantor,
                    -- CORRIGIDO: Subquery agora filtra por id_eventos
                    (SELECT COUNT(*) FROM musicas_cantor mc WHERE mc.id_cantor = c.id AND mc.status IN ('aguardando', 'pulou') AND mc.ordem_na_lista >= c.proximo_ordem_musica AND mc.id_eventos = :id_eventos_sub) AS musicas_elegiveis_cantor
                FROM cantores c
                JOIN usuarios u ON c.id_usuario = u.id
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
            ':id_eventos_sub1' => ID_EVENTO_ATIVO,
            ':id_eventos_sub2' => ID_EVENTO_ATIVO,
            ':id_eventos_sub' => ID_EVENTO_ATIVO,
            ':id_tenants_c' => ID_TENANTS,
            ':id_tenants_m' => ID_TENANTS
        ]);
        $cantoresDisponiveisGlobal = $stmtTodosCantores->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cantoresDisponiveisGlobal)) {


            $mensagemErro = "Não há cantores cadastrados para montar a rodada para o tenant " . ID_TENANTS;
            error_log("ERRO: " . $mensagemErro);
            $pdo->rollBack();
            return $mensagemErro; // Retorna a mensagem de erro

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
                // Verificar se há cantores sem nenhuma música cadastrada
                $cantoresSemMusicas = 0;
                $cantoresComMusicasCantadas = 0;
                
                foreach ($cantoresDisponiveisGlobal as $cantor) {
                    // Verificar se o cantor tem alguma música cadastrada
                    $stmtVerificarMusicas = $pdo->prepare("SELECT COUNT(*) as total FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ?");
                    $stmtVerificarMusicas->execute([$cantor['id_cantor'], ID_EVENTO_ATIVO]);
                    $totalMusicas = $stmtVerificarMusicas->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    if ($totalMusicas == 0) {
                        $cantoresSemMusicas++;
                    } else {
                        $cantoresComMusicasCantadas++;
                    }
                }
                
                // Definir mensagem baseada na situação
                if ($cantoresSemMusicas > 0 && $cantoresComMusicasCantadas == 0) {
                    $mensagemErro = "Adicione pelo menos uma música a um cantor para iniciar a rodada.";
                } else {
                    $mensagemErro = "Todas as músicas das filas de cada cantor já foram cantadas. Solicite que escolham novas músicas!";
                }
                
                error_log("ERRO: " . $mensagemErro);
                $pdo->rollBack();
                return $mensagemErro; // Retorna a mensagem de erro
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

        // --- Limpa a fila antiga antes de inserir a nova rodada (filtrado por tenant e evento) ---
        $stmtDeleteOldQueue = $pdo->prepare("DELETE FROM fila_rodadas WHERE rodada < ? AND status = 'aguardando' AND id_tenants = ? AND id_eventos = ?");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtDeleteOldQueue->execute([$proximaRodada, ID_TENANTS, ID_EVENTO_ATIVO]);
        error_log("DEBUG: Fila_rodadas antigas (status 'aguardando') limpas para rodadas anteriores a " . $proximaRodada . " para o tenant " . ID_TENANTS . " e evento " . ID_EVENTO_ATIVO);

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

            // --- ATUALIZADO: Query INSERT agora inclui id_eventos ---
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_tenants, id_eventos, id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, id_mesa, status, timestamp_adicao, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), " . $timestamp_inicio_canto . ")");
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", Música " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem TEMP " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Mesa " . $item['id_mesa'] . ", Status " . $status . ", Tenant " . $item['id_tenants'] . ", Evento " . ID_EVENTO_ATIVO);
            $stmtInsert->execute([$item['id_tenants'], ID_EVENTO_ATIVO, $item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $item['id_mesa'], $status]);
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

        // Atualiza o controle de rodada por MC específico
        $stmtCheckControl = $pdo->prepare("SELECT COUNT(*) FROM controle_rodada WHERE id_tenants = ? AND id_mc = ?");
        $stmtCheckControl->execute([ID_TENANTS, ID_USUARIO]);
        if ($stmtCheckControl->fetchColumn() == 0) {
            $stmtInsertControl = $pdo->prepare("INSERT INTO controle_rodada (id_tenants, id_mc, rodada_atual) VALUES (?, ?, ?)");
            $stmtInsertControl->execute([ID_TENANTS, ID_USUARIO, $proximaRodada]);
            error_log("DEBUG: controle_rodada inserido com a rodada " . $proximaRodada . " para o tenant " . ID_TENANTS . " e MC " . ID_USUARIO);
        } else {
            $stmtUpdateRodada = $pdo->prepare("UPDATE controle_rodada SET rodada_atual = ? WHERE id_tenants = ? AND id_mc = ?");
            $stmtUpdateRodada->execute([$proximaRodada, ID_TENANTS, ID_USUARIO]);
            error_log("DEBUG: controle_rodada atualizado para a rodada " . $proximaRodada . " para o tenant " . ID_TENANTS . " e MC " . ID_USUARIO);
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

// FIM DO MONTAR RODADAS

/**
* Reordena a fila de uma rodada específica para evitar que músicas da mesma mesa
* sejam executadas consecutivamente e para buscar uma intercalação mais justa
* entre todas as mesas ativas.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int $rodada O número da rodada a ser reordenada.
 * @return bool Retorna true se a reordenação for bem-sucedida, false caso contrário.
 */
function reordenarFilaParaIntercalarMesas(PDO $pdo, int $rodada): bool {
    error_log("DEBUG: Iniciando reordenação para a Rodada " . $rodada);

    try {
        $isInTransaction = $pdo->inTransaction();
        if (!$isInTransaction) {
            $pdo->beginTransaction();
            error_log("DEBUG: Transação iniciada dentro de reordenarFilaParaIntercalarMesas.");
        }

        // 1. Obter a fila atual da rodada
        $stmt = $pdo->prepare("
            SELECT
                fr.id,
                fr.id_cantor,
                fr.id_musica,
                fr.musica_cantor_id,
                fr.ordem_na_rodada,
                fr.rodada,
                fr.status,
                fr.id_mesa,
                fr.timestamp_adicao,
                m.nome_mesa,
                u.nome AS nome_cantor,
                mu.titulo AS nome_musica,
                mu.artista AS nome_artista
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN usuarios u ON c.id_usuario = u.id
            JOIN mesas m ON fr.id_mesa = m.id
            JOIN musicas mu ON fr.id_musica = mu.id
            WHERE fr.rodada = :rodada
            ORDER BY fr.ordem_na_rodada ASC, fr.timestamp_adicao ASC
        ");
        $stmt->execute([':rodada' => $rodada]);
        $filaAtual = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($filaAtual)) {
            error_log("INFO: Fila da Rodada " . $rodada . " está vazia, nada para reordenar.");
            if (!$isInTransaction) {
                $pdo->commit();
            }
            return true;
        }

        $fixedItems = [];
        $pendingItems = [];

        foreach ($filaAtual as $item) {
            if ($item['status'] === 'aguardando' || $item['status'] === 'selecionada_para_rodada') {
                $pendingItems[] = $item;
            } else {
                $fixedItems[] = $item;
            }
        }

        if (empty($pendingItems)) {
            error_log("INFO: Nenhuma música pendente para reordenar na Rodada " . $rodada . ".");
            if (!$isInTransaction) {
                $pdo->commit();
            }
            return true;
        }

        // Agrupa as músicas pendentes por mesa
        $pendingItemsByMesa = [];
        foreach ($pendingItems as $item) {
            $pendingItemsByMesa[$item['id_mesa']][] = $item;
        }

        $novaOrdemFila = [];
        $lastMesaAdded = null;
        // Mapeia o timestamp da última vez que cada mesa foi selecionada nesta rodada
        $lastSelectionTime = [];
        foreach(array_keys($pendingItemsByMesa) as $mesaId) {
            $lastSelectionTime[$mesaId] = 0; // Inicializa com 0
        }


        // Adiciona os itens fixos primeiro e define a última mesa adicionada
        if (!empty($fixedItems)) {
            $novaOrdemFila = $fixedItems;
            $lastFixedItem = end($fixedItems);
            $lastMesaAdded = $lastFixedItem['id_mesa'];
            // Atualiza o lastSelectionTime para a mesa do item fixo
            $lastSelectionTime[$lastMesaAdded] = microtime(true);
        }

        // --- Lógica de intercalação mais robusta ---
        $remainingPendingCount = count($pendingItems);
        // Aumentar o limite de iterações para garantir que todos os itens sejam processados
        // mesmo em cenários complexos de poucas mesas e muitas músicas
        $maxIterations = $remainingPendingCount * count($pendingItemsByMesa) * 2;

        error_log("DEBUG: Iniciando loop de intercalação. Total pendente: " . $remainingPendingCount);

        while ($remainingPendingCount > 0 && $maxIterations-- > 0) {
            $itemAddedInThisIteration = false;
            $selectedMesaId = null;

            // Obter as mesas que ainda têm músicas
            $mesasComMusicas = array_keys(array_filter($pendingItemsByMesa, 'count'));

            if (empty($mesasComMusicas)) {
                error_log("INFO: Nenhuma mesa com músicas restantes, mas remainingPendingCount > 0. Algo errado.");
                break; // Sai do loop se não houver mesas com músicas
            }

            // Filtra as mesas que não são a lastMesaAdded
            $eligibleMesas = array_filter($mesasComMusicas, function($mesaId) use ($lastMesaAdded) {
                return $mesaId !== $lastMesaAdded;
            });

            if (!empty($eligibleMesas)) {
                // Se houver mesas elegíveis (diferentes da última adicionada),
                // seleciona a que cantou há mais tempo (menor timestamp_adicao)
                $oldestMesaId = null;
                $oldestTime = PHP_INT_MAX;

                foreach ($eligibleMesas as $mesaId) {
                    if ($lastSelectionTime[$mesaId] < $oldestTime) {
                        $oldestTime = $lastSelectionTime[$mesaId];
                        $oldestMesaId = $mesaId;
                    }
                }
                $selectedMesaId = $oldestMesaId;
                error_log("DEBUG: Escolhida mesa " . $selectedMesaId . " por ser a que cantou há mais tempo (não consecutiva).");

            } else {
                // Caso todas as mesas restantes sejam a lastMesaAdded,
                // ou se só houver uma mesa com músicas restantes
                // Neste ponto, todas as mesas elegíveis foram usadas ou só resta a mesa anterior.
                // Se só há uma mesa com música e ela é a lastMesaAdded, somos forçados a repeti-la.
                if (count($mesasComMusicas) === 1 && $mesasComMusicas[0] === $lastMesaAdded) {
                    $selectedMesaId = $lastMesaAdded;
                    error_log("DEBUG: Fallback: Apenas a mesa " . $selectedMesaId . " tem músicas restantes. Será repetida.");
                } else {
                    // Se há mais de uma mesa, mas todas foram a 'lastMesaAdded' em algum momento,
                    // significa que todas as outras opções esgotaram no ciclo anterior.
                    // Agora, escolhe a que cantou há mais tempo, mesmo que possa ter sido a última,
                    // pois não há outra opção válida que não repita.
                    $oldestMesaId = null;
                    $oldestTime = PHP_INT_MAX;

                    foreach ($mesasComMusicas as $mesaId) { // Agora consideramos todas as mesas com músicas
                        if ($lastSelectionTime[$mesaId] < $oldestTime) {
                            $oldestTime = $lastSelectionTime[$mesaId];
                            $oldestMesaId = $mesaId;
                        }
                    }
                    $selectedMesaId = $oldestMesaId;
                    error_log("DEBUG: Fallback: Todas as mesas elegíveis foram usadas. Escolhendo a mesa " . $selectedMesaId . " que cantou há mais tempo (pode ser consecutiva, se não houver alternativa).");
                }
            }

            // Se uma mesa foi selecionada e ainda tem músicas, adicione sua música
            if ($selectedMesaId !== null && !empty($pendingItemsByMesa[$selectedMesaId])) {
                $item = array_shift($pendingItemsByMesa[$selectedMesaId]);
                $novaOrdemFila[] = $item;
                $lastMesaAdded = $item['id_mesa'];
                // Atualiza o timestamp da última seleção para esta mesa
                $lastSelectionTime[$lastMesaAdded] = microtime(true);
                $remainingPendingCount--;
                $itemAddedInThisIteration = true;
                error_log("DEBUG: Adicionada música MC ID " . $item['musica_cantor_id'] . " da Mesa " . $item['id_mesa'] . ". Restantes: " . $remainingPendingCount);
            } else {
                error_log("AVISO: Nenhuma música pôde ser adicionada nesta iteração, mas ainda há " . $remainingPendingCount . " músicas pendentes. Verificando as restantes.");
                // Este cenário deve ser raro com a lógica aprimorada, mas garante que não trave
                // Adiciona o restante dos itens pendentes sem reordenação para não perdê-los.
                foreach ($pendingItemsByMesa as $mesaQueue) {
                    $novaOrdemFila = array_merge($novaOrdemFila, $mesaQueue);
                }
                $remainingPendingCount = 0; // Força a saída do loop
                break;
            }
        } // Fim do while principal da reordenação

        // Verificação final caso algo tenha sobrado (não deveria com a lógica aprimorada)
        if ($remainingPendingCount > 0) {
            error_log("AVISO: Após o loop de reordenação, ainda restam " . $remainingPendingCount . " músicas pendentes. Adicionando-as ao final da fila.");
            foreach ($pendingItemsByMesa as $mesaId => $mesaQueue) {
                if (!empty($mesaQueue)) {
                    $novaOrdemFila = array_merge($novaOrdemFila, $mesaQueue);
                }
            }
        }


        // 3. Reatribuir as ordens e atualizar o banco de dados
        $stmtUpdate = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = :nova_ordem WHERE id = :id");

        $currentOrder = 1;
        foreach ($novaOrdemFila as $item) {
            // Apenas atualiza o banco de dados se a ordem_na_rodada realmente mudou
            if ((int)$item['ordem_na_rodada'] !== $currentOrder) { // Removi a condição de status para sempre atualizar a ordem
                $stmtUpdate->execute([
                    ':nova_ordem' => $currentOrder,
                    ':id' => $item['id']
                ]);
            }
            $currentOrder++;
        }
        error_log("DEBUG: Reordenação da Rodada " . $rodada . " concluída e banco de dados atualizado.");

        // Confirma a transação
        if (!$isInTransaction) {
            $pdo->commit();
        }
        error_log("DEBUG: reordenarFilaParaIntercalarMesas concluída.");
        return true;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: Falha na reordenação da fila da Rodada " . $rodada . ": " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: Erro inesperado na reordenação da fila da Rodada " . $rodada . ": " . $e->getMessage());
        return false;
    }
}

// FIM REORDENAR FILA RODADAS

/**
 * Marca uma música na fila como 'em_execucao', 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas.
 * @param string $status Novo status ('em_execucao', 'cantou', 'pulou').
 * @return bool True em caso de sucesso, false caso contrário.
 */
function atualizarStatusMusicaFila(PDO $pdo, $filaId, $status) {
    // Removido: A constante ID_TENANTS é global e não precisa de 'global'
    // Removido: A constante ID_EVENTO_ATIVO também será usada diretamente

    error_log("DEBUG: Chamada atualizarStatusMusicaFila com filaId: " . $filaId . ", status: " . $status);
    try {
        // Primeiro, obtenha o musica_cantor_id, id_cantor e id_musica associados a este filaId
        $stmtGetInfo = $pdo->prepare("SELECT musica_cantor_id, id_cantor, id_musica FROM fila_rodadas WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtGetInfo->execute([$filaId, ID_TENANTS, ID_EVENTO_ATIVO]);
        $filaItem = $stmtGetInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Item da fila (ID: " . $filaId . ") não encontrado ou não pertence ao tenant " . ID_TENANTS . " para atualizar status.");
            return false;
        }

        $musicaCantorId = $filaItem['musica_cantor_id'];
        $idCantor = $filaItem['id_cantor'];
        $idMusica = $filaItem['id_musica'];

        $pdo->beginTransaction();

        $successFilaUpdate = false;
        $successMusicasCantorUpdate = true;

        if ($status === 'em_execucao') {
            // CORRIGIDO: Agora a função getRodadaAtual é chamada com os 2 argumentos esperados
            // Alterado: Usa a constante ID_TENANTS
            $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);

            // 1. Resetar QUALQUER música que ESTAVA 'em_execucao' na fila_rodadas da rodada atual
            $stmtResetPreviousExecution = $pdo->prepare("UPDATE fila_rodadas SET status = 'aguardando', timestamp_inicio_canto = NULL, timestamp_fim_canto = NULL WHERE rodada = ? AND status = 'em_execucao' AND id_tenants = ? AND id_eventos = ?");
            // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
            $stmtResetPreviousExecution->execute([$rodadaAtual, ID_TENANTS, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Músicas anteriormente 'em_execucao' na fila_rodadas resetadas para 'aguardando' na rodada " . $rodadaAtual . " para o tenant " . ID_TENANTS . " e evento " . ID_EVENTO_ATIVO . ".");

            // 2. Definir a nova música como 'em_execucao' na fila_rodadas.
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_inicio_canto = NOW(), timestamp_fim_canto = NULL WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (em_execucao): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // 3. Atualizar o status NA TABELA musicas_cantor. Lembre-se que musicas_cantor usa id_eventos
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ? AND id_eventos = ?");
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (em_execucao): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'cantou') {
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (cantou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'cantou' WHERE id = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (cantou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());
            
            // Move a música cantada para o final da fila do cantor
            if ($successMusicasCantorUpdate) {
                // Commit da transação atual antes de chamar a função que cria sua própria transação
                $pdo->commit();
                
                // Chama a função para mover a música para o final
                $successMoverParaFinal = moverMusicaCantouParaFinal($pdo, $musicaCantorId, $idCantor);
                
                if ($successMoverParaFinal) {
                    error_log("DEBUG: Música cantada movida para o final da fila com sucesso.");
                    return $successFilaUpdate && $successMusicasCantorUpdate;
                } else {
                    error_log("Alerta: Falha ao mover música cantada para o final da fila, mas status foi atualizado com sucesso.");
                    return $successFilaUpdate && $successMusicasCantorUpdate;
                }
            }

        } elseif ($status === 'pulou') {
            // A sua lógica aqui depende da relação entre cantores e eventos. Se um cantor
            // pertence a um tenant, mas as músicas dele pertencem a um evento, a lógica
            // para encontrar o cantor certo e atualizar a ordem pode ser complexa.
            // A solução mais simples é filtrar o UPDATE por id_tenants na tabela cantores.
            $stmtGetOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_eventos = ?");
            $stmtGetOrder->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            $ordemMusicaPulada = $stmtGetOrder->fetchColumn();

            if ($ordemMusicaPulada !== false) {
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = GREATEST(1, ?) WHERE id = ? AND id_tenants = ?");
                // Alterado: Usa a constante ID_TENANTS
                $stmtUpdateCantorOrder->execute([$ordemMusicaPulada, $idCantor, ID_TENANTS]);
                error_log("DEBUG: Cantor " . $idCantor . " do tenant " . ID_TENANTS . " teve proximo_ordem_musica resetado para " . $ordemMusicaPulada . " após música pulada (fila_id: " . $filaId . ").");
            } else {
                error_log("Alerta: Música pulada (musica_cantor_id: " . $musicaCantorId . ") não encontrada na lista musicas_cantor. Não foi possível resetar o proximo_ordem_musica.");
                $successMusicasCantorUpdate = false;
            }

            // Atualiza o status na tabela fila_rodadas para 'pulou'
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ? AND id_tenants = ? AND id_eventos = ?");
            // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
            $successFilaUpdate = $stmt->execute([$status, $filaId, ID_TENANTS, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (pulou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ? AND id_eventos = ?");
            // Alterado: Usa a constante ID_EVENTO_ATIVO
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId, ID_EVENTO_ATIVO]);
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (pulou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());
        } else {
            error_log("Erro: Status inválido na função atualizarStatusMusicaFila: " . $status);
            $pdo->rollBack();
            return false;
        }

        // Para o status 'cantou', a transação já foi commitada dentro do bloco específico
        if ($status === 'cantou') {
            // A lógica de commit já foi tratada no bloco 'cantou'
            return $successFilaUpdate && $successMusicasCantorUpdate;
        }
        
        if ($successFilaUpdate && $successMusicasCantorUpdate) {
            $pdo->commit();
            error_log("DEBUG: Transação commitada para fila_id: " . $filaId . ", status: " . $status);
            return true;
        } else {
            $pdo->rollBack();
            error_log("DEBUG: Transação revertida para fila_id: " . $filaId . ", status: " . $status . ". Fila success: " . ($successFilaUpdate ? 'true' : 'false') . ", MC success: " . ($successMusicasCantorUpdate ? 'true' : 'false'));
            return false;
        }

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao atualizar status da música na fila: " . $e->getMessage());
        return false;
    }
}

// FIM ATUALIZAR STATUS MUSICA

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

// FIM CONFIG REGRAS MESAS

// Função getAllCantores removida - usar getAllCantoresComUsuario() do funcoes_cantores_novo.php

function getTodasMesas(PDO $pdo) {
    // Removido: Não é mais necessário usar 'global' para a constante ID_TENANTS
    try {
        $stmt = $pdo->prepare("SELECT id, nome_mesa, tamanho_mesa FROM mesas WHERE id_tenants = ? ORDER BY nome_mesa");
        // Alterado: Usa a constante ID_TENANTS
        $stmt->execute([ID_TENANTS]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao buscar mesas: " . $e->getMessage());
        return [];
    }
}

/**
 * Exclui uma mesa do banco de dados, impedindo a exclusão se a mesa tiver
 * alguma música associada em status 'em_execucao' na fila_rodadas.
 *
 * @param PDO $pdo Objeto PDO da conexão com o banco de dados.
 * @param int $mesaId O ID da mesa a ser excluída.
 * @return array Um array associativo com 'success' (bool) e 'message' (string).
 */
function excluirMesa(PDO $pdo, int $mesaId): array {
    // Removido: Não é mais necessário usar 'global' para a constante ID_TENANTS
    try {
        $pdo->beginTransaction();

        // 1. Verificar se a mesa possui alguma música em status 'em_execucao' na fila_rodadas
        $stmtCheckFila = $pdo->prepare("
            SELECT COUNT(fr.id)
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            WHERE c.id_mesa = :mesaId
            AND fr.status = 'em_execucao'
            AND fr.id_tenants = :id_tenants
        ");
        // Alterado: Usa a constante ID_TENANTS
        $stmtCheckFila->execute([':mesaId' => $mesaId, ':id_tenants' => ID_TENANTS]);
        $isMesaInExecution = $stmtCheckFila->fetchColumn();

        if ($isMesaInExecution > 0) {
            $pdo->rollBack();
            error_log("Alerta: Tentativa de excluir mesa (ID: " . $mesaId . ") do tenant " . ID_TENANTS . " que tem música(s) em 'em_execucao' na fila. Exclusão não permitida.");
            return ['success' => false, 'message' => "Não é possível remover a mesa. Há uma música desta mesa atualmente em execução."];
        }

        // 2. Se a verificação passou, obtenha o nome da mesa para a mensagem de sucesso/erro
        $stmtGetMesaNome = $pdo->prepare("SELECT nome_mesa FROM mesas WHERE id = :mesaId AND id_tenants = :id_tenants");
        // Alterado: Usa a constante ID_TENANTS
        $stmtGetMesaNome->execute([':mesaId' => $mesaId, ':id_tenants' => ID_TENANTS]);
        $mesaInfo = $stmtGetMesaNome->fetch(PDO::FETCH_ASSOC);
        $nomeMesa = $mesaInfo['nome_mesa'] ?? 'Mesa Desconhecida';

        // 3. Exclua a mesa
        $stmtDeleteMesa = $pdo->prepare("DELETE FROM mesas WHERE id = :id AND id_tenants = :id_tenants");
        // Alterado: Usa a constante ID_TENANTS
        $stmtDeleteMesa->execute([':id' => $mesaId, ':id_tenants' => ID_TENANTS]);

        if ($stmtDeleteMesa->rowCount() > 0) {
            $pdo->commit();
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> excluída!"];
        } else {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Mesa não encontrada, não pertence ao seu tenant ou já excluída.'];
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao excluir mesa: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro interno do servidor ao excluir mesa: ' . $e->getMessage()];
    }
}

/**
 * Adiciona uma nova mesa ao sistema.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeMesa Nome/identificador da mesa.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMesa(PDO $pdo, $nomeMesa) {
    // Removido: Não é mais necessário usar 'global' para a constante ID_TENANTS
    try {
        // 1. Verificar se a mesa já existe para ESTE tenant
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM mesas WHERE nome_mesa = ? AND id_tenants = ?");
        // Alterado: Usa a constante ID_TENANTS
        $stmtCheck->execute([$nomeMesa, ID_TENANTS]);
        $count = $stmtCheck->fetchColumn();

        if ($count > 0) {
            return ['success' => false, 'message' => "Já existe uma mesa com esse nome para este tenant!"];
        }
    } catch (\PDOException $e) {
        error_log("Erro ao verificar existência da mesa: " . $e->getMessage());
        return ['success' => false, 'message' => "Erro ao verificar existência da mesa."];
    }

    // 2. Se não existe, inserir a nova mesa com o id_tenants
    try {
        $stmtInsert = $pdo->prepare("INSERT INTO mesas (id_tenants, nome_mesa) VALUES (?, ?)");
        // Alterado: Usa a constante ID_TENANTS
        if ($stmtInsert->execute([ID_TENANTS, $nomeMesa])) {
            return ['success' => true, 'message' => "Mesa <strong>{$nomeMesa}</strong> adicionada!"];
        } else {
            return ['success' => false, 'message' => "Não foi possível adicionar a mesa <strong>{$nomeMesa}</strong> por um motivo desconhecido."];
        }
    } catch (\PDOException $e) {
        error_log("Erro ao adicionar mesa: " . $e->getMessage());
        return ['success' => false, 'message' => "Erro no banco de dados ao adicionar mesa."];
    }
}

// Função adicionarCantor removida - usar adicionarCantorPorUsuario() do funcoes_cantores_novo.php

// Função removerCantor removida - usar removerCantorPorId() do funcoes_cantores_novo.php

/**
 * Obtém o número da rodada atual.
 * Retorna 0 se o sistema estiver em um estado "limpo" (sem rodadas ativas ou no histórico)
 * para que a próxima rodada a ser montada seja a 1.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O número da rodada atual (ou 0 se for a primeira rodada a ser criada).
 */
function getRodadaAtual(PDO $pdo) {
    // Removido o parâmetro $id_tenants_logado e o global, usamos a constante ID_TENANTS diretamente
    try {
        // 1. Tenta obter a rodada_atual da tabela de controle.
        $stmt = $pdo->prepare("SELECT rodada_atual FROM controle_rodada WHERE id_tenants = ? AND id_mc = ?");
        $stmt->execute([ID_TENANTS, ID_USUARIO]);
        $rodadaAtualFromDB = $stmt->fetchColumn();

        $rodadaAtualFromDB = ($rodadaAtualFromDB === false || $rodadaAtualFromDB === null) ? 0 : (int)$rodadaAtualFromDB;

        // 2. Verifica se existe *alguma* música com status 'aguardando' em *qualquer* rodada.
        $stmtCheckAnyActiveFila = $pdo->prepare("SELECT rodada FROM fila_rodadas WHERE id_tenants = ? AND id_eventos = ? AND (status = 'aguardando' OR status = 'em_execucao') ORDER BY rodada DESC LIMIT 1");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtCheckAnyActiveFila->execute([ID_TENANTS, ID_EVENTO_ATIVO]);
        $rodadaComMusicasAguardando = $stmtCheckAnyActiveFila->fetchColumn();

        if ($rodadaComMusicasAguardando !== false && $rodadaComMusicasAguardando !== null) {
            return (int)$rodadaComMusicasAguardando;
        }

        // 3. Se não há músicas 'aguardando', verifica se existe *alguma* rodada com 'cantou' ou 'pulou'.
        $stmtMaxRodadaFinalizada = $pdo->prepare("SELECT MAX(rodada) FROM fila_rodadas WHERE id_tenants = ? AND id_eventos = ? AND status IN ('cantou', 'pulou')");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtMaxRodadaFinalizada->execute([ID_TENANTS, ID_EVENTO_ATIVO]);
        $maxRodadaFinalizada = $stmtMaxRodadaFinalizada->fetchColumn();

        if ($maxRodadaFinalizada !== false && $maxRodadaFinalizada !== null) {
            return (int)$maxRodadaFinalizada;
        }

        // 4. Se não há nada, retorna 0.
        return 0;

    } catch (\PDOException $e) {
        error_log("Erro ao obter rodada atual: " . $e->getMessage());
        return 0;
    }
}


// Função de formatação do status
function formatarStatus($status)
{
    return match ($status) {
        'em_execucao' => '🎤 Em execução',
        'selecionada_para_rodada' => '⏳ Selecionada para a rodada',
        'cantou' => '✅ Já cantou',
        'pulou' => '⏭️ Pulou a vez',
        default => '🕒 Aguardando',
    };
}

/**
 * Obtém a próxima música a ser cantada na rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array|null Dados da próxima música e cantor, ou null se não houver.
 */
function getProximaMusicaFila(PDO $pdo) {
    // Removido o global
    // Alterado: Usa a constante ID_TENANTS no lugar da variável
    $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.musica_cantor_id,
                u.nome AS nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN usuarios u ON c.id_usuario = u.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'aguardando' AND fr.id_tenants = ? AND fr.id_eventos = ?
            ORDER BY fr.ordem_na_rodada ASC
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmt->execute([$rodadaAtual, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetch();
    } catch (\PDOException $e) {
        error_log("Erro ao obter próxima música da fila: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtém a música que está atualmente 'em_execucao' na rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array|null Dados da música em execução, ou null se não houver.
 */
function getMusicaEmExecucao(PDO $pdo) {
    // Removido o global
    // Alterado: Usa a constante ID_TENANTS no lugar da variável
    $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.id_cantor,
                fr.id_musica,
                fr.musica_cantor_id,
                u.nome AS nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                m.codigo AS codigo_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN usuarios u ON c.id_usuario = u.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'em_execucao' AND fr.id_tenants = ? AND fr.id_eventos = ?
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmt->execute([$rodadaAtual, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter música em execução: " . $e->getMessage());
        return null;
    }
}


/**
 * Troca a música de um item na fila de rodadas.
 * Se a nova música não estiver na lista do cantor para o evento, ela será adicionada.
 *
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas a ser atualizado.
 * @param int $novaMusicaId ID da nova música a ser definida para o item da fila.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function trocarMusicaNaFilaAtual(PDO $pdo, $filaId, $novaMusicaId) {
    try {
        $pdo->beginTransaction();

        // 1. Obter informações do item da fila original, JOIN com cantores para filtrar por tenant E evento
        $stmtGetOldMusicInfo = $pdo->prepare("
            SELECT fr.id_cantor, fr.id_musica, fr.musica_cantor_id, c.id_tenants
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            WHERE fr.id = ? AND fr.id_eventos = ? AND c.id_tenants = ? AND (fr.status = 'aguardando' OR fr.status = 'em_execucao')
        ");
        $stmtGetOldMusicInfo->execute([$filaId, ID_EVENTO_ATIVO, ID_TENANTS]);
        $filaItem = $stmtGetOldMusicInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Tentativa de trocar música em item da fila inexistente ou já finalizado (ID: " . $filaId . ") para o tenant " . ID_TENANTS . " e evento " . ID_EVENTO_ATIVO . ".");
            $pdo->rollBack();
            return false;
        }

        $idCantor = $filaItem['id_cantor'];
        $musicaOriginalId = $filaItem['id_musica'];
        $musicaCantorOriginalId = $filaItem['musica_cantor_id'];

        // --- Lógica para a MÚSICA ORIGINAL (saindo da fila) ---
        if ($musicaCantorOriginalId !== null) {
            $stmtGetOriginalOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
            $stmtGetOriginalOrder->execute([$musicaCantorOriginalId, $idCantor, ID_EVENTO_ATIVO]);
            $ordemMusicaOriginal = $stmtGetOriginalOrder->fetchColumn();

            if ($ordemMusicaOriginal !== false) {
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ? AND id_tenants = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaOriginal, $idCantor, ID_TENANTS]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaOriginal . " após troca de música (fila_id: " . $filaId . ").");

                $stmtUpdateOriginalMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
                $stmtUpdateOriginalMusicaCantorStatus->execute([$musicaCantorOriginalId, $idCantor, ID_EVENTO_ATIVO]);
                error_log("DEBUG: Status da música original (musicas_cantor_id: " . $musicaCantorOriginalId . ") do cantor " . $idCantor . " resetado para 'aguardando' na tabela musicas_cantor.");
            } else {
                error_log("Alerta: ID de musica_cantor_id (" . $musicaCantorOriginalId . ") para o item da fila (ID: " . $filaId . ") não encontrado na tabela musicas_cantor para o evento " . ID_EVENTO_ATIVO . ". Não foi possível resetar o proximo_ordem_musica ou o status.");
            }
        } else {
            error_log("DEBUG: Música original (ID: " . $musicaOriginalId . ") do item da fila (ID: " . $filaId . ") não possui um musica_cantor_id associado, não há status para resetar em musicas_cantor.");
        }

        // --- Lógica para a NOVA MÚSICA (entrando na fila) ---
        $novaMusicaCantorId = null;

        // Tenta encontrar a nova música na lista do cantor para o evento
        $stmtCheckNewMusicInCantorList = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id_cantor = ? AND id_musica = ? AND id_eventos = ? LIMIT 1");
        $stmtCheckNewMusicInCantorList->execute([$idCantor, $novaMusicaId, ID_EVENTO_ATIVO]);
        $newMusicInCantorList = $stmtCheckNewMusicInCantorList->fetch(PDO::FETCH_ASSOC);

        if ($newMusicInCantorList) {
            // Se a música já existe na lista, move ela para a primeira posição
            $novaMusicaCantorId = $newMusicInCantorList['id'];
            
            // Move a música selecionada para a primeira posição e atualiza o status
            $stmtMoveToFirst = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = 1, status = 'selecionada_para_rodada' WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
            $stmtMoveToFirst->execute([$novaMusicaCantorId, $idCantor, ID_EVENTO_ATIVO]);
            
            // Reorganiza todos os índices sequencialmente
            $stmtGetAllMusics = $pdo->prepare("SELECT id FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ? AND id != ? ORDER BY ordem_na_lista ASC");
            $stmtGetAllMusics->execute([$idCantor, ID_EVENTO_ATIVO, $novaMusicaCantorId]);
            $otherMusics = $stmtGetAllMusics->fetchAll(PDO::FETCH_COLUMN);
            
            // Atualiza a ordem das outras músicas sequencialmente (2, 3, 4, ...)
            $ordem = 2;
            foreach ($otherMusics as $musicId) {
                $stmtUpdateOrder = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ?");
                $stmtUpdateOrder->execute([$ordem, $musicId]);
                $ordem++;
            }
            
            error_log("DEBUG: Música existente (musicas_cantor_id: " . $novaMusicaCantorId . ") do cantor " . $idCantor . " movida para primeira posição e fila reorganizada sequencialmente.");
        } else {
            // Se a música NÃO existe na lista, reorganiza a fila para colocá-la na primeira posição
            
            // Primeiro, incrementa a ordem_na_lista de todas as músicas existentes do cantor
            $stmtIncrementOrder = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ordem_na_lista + 1 WHERE id_cantor = ? AND id_eventos = ?");
            $stmtIncrementOrder->execute([$idCantor, ID_EVENTO_ATIVO]);
            
            // Depois, insere a nova música na primeira posição (ordem_na_lista = 1)
            $stmtInsertNewMusic = $pdo->prepare("INSERT INTO musicas_cantor (id_eventos, id_cantor, id_musica, ordem_na_lista, status) VALUES (?, ?, ?, 1, 'selecionada_para_rodada')");
            $stmtInsertNewMusic->execute([ID_EVENTO_ATIVO, $idCantor, $novaMusicaId]);
            $novaMusicaCantorId = $pdo->lastInsertId();
            error_log("DEBUG: Nova música (ID: " . $novaMusicaId . ") inserida na primeira posição da lista do cantor " . $idCantor . " no evento " . ID_EVENTO_ATIVO . ". Novo musica_cantor_id: " . $novaMusicaCantorId . ".");
        }

        // 4. Atualiza o id_musica e musica_cantor_id na tabela fila_rodadas com a nova música
        $stmtUpdateFila = $pdo->prepare("
            UPDATE fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            SET fr.id_musica = ?, fr.musica_cantor_id = ?
            WHERE fr.id = ? AND fr.id_eventos = ? AND c.id_tenants = ?
        ");
        $result = $stmtUpdateFila->execute([$novaMusicaId, $novaMusicaCantorId, $filaId, ID_EVENTO_ATIVO, ID_TENANTS]);

        if ($result) {
            $pdo->commit();
            return true;
        } else {
            $pdo->rollBack();
            return false;
        }

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao trocar música na fila atual: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualiza a ordem dos itens na fila de uma rodada específica.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $rodada O número da rodada a ser atualizada.
 * @param array $novaOrdemFila Um array onde a chave é o ID do item da fila (fila_rodadas.id)
 * e o valor é a nova posição (ordem_na_rodada).
 * @return bool True se a atualização for bem-sucedida, false caso contrário.
 */
function atualizarOrdemFila(PDO $pdo, int $rodada, array $novaOrdemFila): bool {
    // Removido o global
    if (empty($novaOrdemFila)) {
        error_log("DEBUG: Array de nova ordem da fila vazio. Nenhuma atualização realizada.");
        return true;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = ? WHERE id = ? AND rodada = ? AND id_tenants = ? AND id_eventos = ?");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        foreach ($novaOrdemFila as $filaItemId => $novaPosicao) {
            $novaPosicaoInt = (int)$novaPosicao;
            if (!$stmt->execute([$novaPosicaoInt, $filaItemId, $rodada, ID_TENANTS, ID_EVENTO_ATIVO])) {
                error_log("ERRO: Falha ao atualizar ordem do item " . $filaItemId . " para posição " . $novaPosicaoInt);
                $pdo->rollBack();
                return false;
            }
        }

        $pdo->commit();
        error_log("DEBUG: Ordem da fila da rodada " . $rodada . " (usando ordem_na_rodada) para o tenant " . ID_TENANTS . " atualizada com sucesso.");
        return true;

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERRO: PDOException ao atualizar ordem da fila: " . $e->getMessage());
        return false;
    }
}
/**
 * Atualiza a coluna 'ordem_na_lista' na tabela 'musicas_cantor'
 * para um cantor específico com base em uma nova ordem.
 *
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @param int $idCantor O ID do cantor cujas músicas serão reordenadas.
 * @param array $novaOrdemMusicas Um array associativo onde a chave é o 'id'
 * da tabela 'musicas_cantor' e o valor é a nova 'ordem_na_lista'.
 * @return bool True em caso de sucesso, false em caso de falha.
 */
function atualizarOrdemMusicasCantor(PDO $pdo, int $idCantor, array $novaOrdemMusicas): bool {
    // Removido: A constante ID_TENANTS é global
    if (empty($novaOrdemMusicas)) {
        return true;
    }

    try {
        $pdo->beginTransaction();

        // VALIDAÇÃO DE SEGURANÇA MULTI-TENANT:
        // Verifica se o ID do cantor realmente pertence ao tenant logado.
        $stmtCheckCantorTenant = $pdo->prepare("SELECT COUNT(*) FROM cantores WHERE id = ? AND id_tenants = ?");
        $stmtCheckCantorTenant->execute([$idCantor, ID_TENANTS]);
        if ($stmtCheckCantorTenant->fetchColumn() == 0) {
            error_log("Alerta de Segurança: Tentativa de reordenar músicas de um cantor que não pertence ao tenant logado. Cantor ID: $idCantor, Tenant ID: " . ID_TENANTS);
            $pdo->rollBack();
            return false;
        }

        $restricted_statuses = ['cantou', 'em_execucao', 'selecionada_para_rodada'];

        // 1. Obter os status atuais das músicas, filtrando APENAS por id_cantor
        $ids_musicas_cantor = array_keys($novaOrdemMusicas);
        $placeholders = implode(',', array_fill(0, count($ids_musicas_cantor), '?'));

        // CORREÇÃO AQUI: Removemos o filtro 'id_tenants' da tabela 'musicas_cantor'.
        $stmtCheckStatus = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id IN ($placeholders) AND id_cantor = ?");
        // Combina os IDs das músicas e o ID do cantor
        $stmtCheckStatus->execute(array_merge($ids_musicas_cantor, [$idCantor]));
        $currentStatuses = $stmtCheckStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        // Prepara a query para atualizar a ordem
        // CORREÇÃO AQUI: Removemos o filtro 'id_tenants' da tabela 'musicas_cantor'.
        $stmtUpdate = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ? AND id_cantor = ?");

        // 2. Iterar sobre a nova ordem e aplicar as atualizações APENAS se o status permitir
        foreach ($novaOrdemMusicas as $musicaCantorId => $novaPosicao) {
            $musicaCantorId = (int) $musicaCantorId;
            $novaPosicao = (int) $novaPosicao;

            if (isset($currentStatuses[$musicaCantorId]) && !in_array($currentStatuses[$musicaCantorId], $restricted_statuses)) {
                // Se o status permitir, executa a atualização
                // CORREÇÃO: Removemos o parâmetro do tenant daqui também
                if (!$stmtUpdate->execute([$novaPosicao, $musicaCantorId, $idCantor])) {
                    $pdo->rollBack();
                    error_log("Erro ao executar UPDATE para musicas_cantor ID: $musicaCantorId, nova_posicao: $novaPosicao, cantor ID: $idCantor");
                    return false;
                }
            } else {
                error_log("Tentativa de reordenar música com status restrito ou ID inválido para o cantor $idCantor: musica_cantor_id=$musicaCantorId, status=" . ($currentStatuses[$musicaCantorId] ?? 'N/A'));
            }
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro no banco de dados ao atualizar ordem das músicas do cantor: " . $e->getMessage());
        return false;
    }
}


/**
 * Obtém todas as músicas cadastradas no sistema.
 * Útil para popular um dropdown de seleção de músicas.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de músicas (id, titulo, artista).
 */
function getAllMusicas(PDO $pdo) {
    // Removido: A constante ID_TENANTS é global
    try {
        // Adiciona a cláusula WHERE para filtrar por tenant
        $stmt = $pdo->prepare("SELECT id, titulo, artista FROM musicas WHERE id_tenants = ? ORDER BY titulo ASC");
        // Alterado: Usa a constante ID_TENANTS
        $stmt->execute([ID_TENANTS]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter todas as músicas para o tenant " . ID_TENANTS . ": " . $e->getMessage());
        return [];
    }
}


/**
 * Obtém a lista completa da fila para a rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de itens da fila.
 */
function getFilaCompleta(PDO $pdo) {
    // Removido: A constante ID_TENANTS é global
    // Agora a função getRodadaAtual usa a constante diretamente
    $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);
    try {
        $sql = "SELECT
                    fr.id AS fila_id,
                    u.nome AS nome_cantor,
                    m.titulo AS titulo_musica,
                    m.artista AS artista_musica,
                    m.codigo as codigo_musica,
                    me.nome_mesa,
                    me.tamanho_mesa,
                    fr.status,
                    fr.ordem_na_rodada
                FROM fila_rodadas fr
                JOIN cantores c ON fr.id_cantor = c.id
                JOIN usuarios u ON c.id_usuario = u.id
                JOIN musicas m ON fr.id_musica = m.id
                JOIN mesas me ON c.id_mesa = me.id
                WHERE fr.rodada = ? AND fr.id_tenants = ? AND fr.id_eventos = ?
                ORDER BY
                    CASE
                        WHEN fr.status = 'em_execucao' THEN 0
                        WHEN fr.status = 'aguardando' THEN 1
                        WHEN fr.status = 'selecionada_para_rodada' THEN 2
                        WHEN fr.status = 'pulou' THEN 3
                        WHEN fr.status = 'cantou' THEN 4
                        ELSE 5
                    END,
                    fr.ordem_na_rodada ASC";

        $stmt = $pdo->prepare($sql);
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmt->execute([$rodadaAtual, ID_TENANTS, ID_EVENTO_ATIVO]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Erro ao obter fila completa para o tenant " . ID_TENANTS . " e evento " . ID_EVENTO_ATIVO . ": " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se todas as músicas da rodada atual foram marcadas como 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool True se a rodada atual estiver finalizada, false caso contrário.
 */
function isRodadaAtualFinalizada(PDO $pdo) {
    // Removido: A constante ID_TENANTS é global
    // Agora a função getRodadaAtual usa a constante diretamente
    $rodadaAtual = getRodadaAtual($pdo, ID_TENANTS);
    try {
        $sql = "SELECT COUNT(*) FROM fila_rodadas WHERE rodada = ? AND id_tenants = ? AND id_eventos = ? AND (status = 'aguardando' OR status = 'em_execucao')";
        $stmt = $pdo->prepare($sql);
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmt->execute([$rodadaAtual, ID_TENANTS, ID_EVENTO_ATIVO]);
        $musicasPendentes = $stmt->fetchColumn();

        return $musicasPendentes === 0;
    } catch (\PDOException $e) {
        error_log("Erro ao verificar status da rodada atual para o tenant " . ID_TENANTS . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Reseta o 'proximo_ordem_musica' de todos os cantores para 1,
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas' APENAS para o tenant logado.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarSistema(PDO $pdo): bool {
    // Removido: As variáveis globais $id_tenants_logado e $id_evento_ativo foram substituídas por constantes
    try {
        $pdo->beginTransaction();

        // 1. Resetar 'proximo_ordem_musica' dos cantores (somente do tenant logado)
        $stmtCantores = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = 1 WHERE id_tenants = ?");
        // Alterado: Usa a constante ID_TENANTS
        $stmtCantores->execute([ID_TENANTS]);
        error_log("DEBUG: Todos os 'proximo_ordem_musica' dos cantores do tenant " . ID_TENANTS . " foram resetados para 1.");

        // 2. Resetar 'status' de todas as músicas para 'aguardando' na tabela musicas_cantor (somente do evento logado)
        $stmtMusicasCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id_eventos = ?");
        // Alterado: Usa a nova constante ID_EVENTO_ATIVO
        $stmtMusicasCantorStatus->execute([ID_EVENTO_ATIVO]);
        error_log("DEBUG: Todos os 'status' na tabela musicas_cantor do evento " . ID_EVENTO_ATIVO . " foram resetados para 'aguardando'.");

        // 3. Resetar 'timestamp_ultima_execucao' para NULL na tabela musicas_cantor (somente do evento logado)
        $stmtMusicasCantorTimestamp = $pdo->prepare("UPDATE musicas_cantor SET timestamp_ultima_execucao = NULL WHERE id_eventos = ?");
        // Alterado: Usa a nova constante ID_EVENTO_ATIVO
        $stmtMusicasCantorTimestamp->execute([ID_EVENTO_ATIVO]);
        error_log("DEBUG: Todos os 'timestamp_ultima_execucao' na tabela musicas_cantor do evento " . ID_EVENTO_ATIVO . " foram resetados para NULL.");

        // 4. Remover registros da fila de rodadas (somente do tenant e evento logado)
        $stmtFila = $pdo->prepare("DELETE FROM fila_rodadas WHERE id_tenants = ? AND id_eventos = ?");
        // Alterado: Usa as constantes ID_TENANTS e ID_EVENTO_ATIVO
        $stmtFila->execute([ID_TENANTS, ID_EVENTO_ATIVO]);
        error_log("DEBUG: Tabela 'fila_rodadas' do tenant " . ID_TENANTS . " e evento " . ID_EVENTO_ATIVO . " limpa.");

        // 5. Resetar controle_rodada (somente do tenant e MC logados)
        $stmtControle = $pdo->prepare("UPDATE controle_rodada SET rodada_atual = 1 WHERE id_tenants = ? AND id_mc = ?");
        $stmtControle->execute([ID_TENANTS, ID_USUARIO]);
        error_log("DEBUG: Tabela 'controle_rodada' do tenant " . ID_TENANTS . " e MC " . ID_USUARIO . " resetada com rodada 1.");

        $pdo->commit();
        error_log("DEBUG: Reset completo da fila (cantores, musicas_cantor, fila_rodadas, controle_rodada) realizado com sucesso para o tenant " . ID_TENANTS . ".");
        return true;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao realizar o reset completo da fila para o tenant " . ID_TENANTS . ": " . $e->getMessage());
        return false;
    }
}

/**
 * Move uma música com status 'cantou' para o final da fila do cantor e reordena as demais.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $musicaCantorId ID da música na tabela musicas_cantor.
 * @param int $idCantor ID do cantor.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function moverMusicaCantouParaFinal(PDO $pdo, int $musicaCantorId, int $idCantor): bool {
    try {
        $pdo->beginTransaction();
        
        // 1. Obter a ordem atual da música que foi cantada
        $stmtOrdemAtual = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
        $stmtOrdemAtual->execute([$musicaCantorId, $idCantor, ID_EVENTO_ATIVO]);
        $ordemAtual = $stmtOrdemAtual->fetchColumn();
        
        if ($ordemAtual === false || $ordemAtual === null) {
            error_log("Erro: Não foi possível obter a ordem atual da música " . $musicaCantorId . " do cantor " . $idCantor);
            $pdo->rollBack();
            return false;
        }
        
        // 2. Obter o total de músicas do cantor
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ?");
        $stmtCount->execute([$idCantor, ID_EVENTO_ATIVO]);
        $totalMusicas = $stmtCount->fetchColumn();
        
        if ($totalMusicas === false || $totalMusicas === 0) {
            error_log("Erro: Não foi possível obter o total de músicas para o cantor " . $idCantor);
            $pdo->rollBack();
            return false;
        }
        
        // 3. Mover a música cantada para o final (última posição)
        $stmtMoverParaFinal = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ? AND id_cantor = ? AND id_eventos = ?");
        $success = $stmtMoverParaFinal->execute([$totalMusicas, $musicaCantorId, $idCantor, ID_EVENTO_ATIVO]);
        
        if (!$success || $stmtMoverParaFinal->rowCount() === 0) {
            error_log("Erro: Falha ao mover música cantada (ID: " . $musicaCantorId . ") para o final da fila do cantor " . $idCantor);
            $pdo->rollBack();
            return false;
        }
        
        // 4. Reordenar as músicas que estavam após a música cantada (decrementar em 1)
        $stmtReordenar = $pdo->prepare(
            "UPDATE musicas_cantor 
             SET ordem_na_lista = ordem_na_lista - 1 
             WHERE id_cantor = ? AND id_eventos = ? AND ordem_na_lista > ? AND id != ?"
        );
        $stmtReordenar->execute([$idCantor, ID_EVENTO_ATIVO, $ordemAtual, $musicaCantorId]);
        
        // 5. Atualizar o proximo_ordem_musica do cantor para a menor ordem disponível
        $stmtMinOrdem = $pdo->prepare(
            "SELECT MIN(ordem_na_lista) 
             FROM musicas_cantor 
             WHERE id_cantor = ? AND id_eventos = ? AND status IN ('aguardando', 'pulou')"
        );
        $stmtMinOrdem->execute([$idCantor, ID_EVENTO_ATIVO]);
        $minOrdemDisponivel = $stmtMinOrdem->fetchColumn();
        
        if ($minOrdemDisponivel !== false && $minOrdemDisponivel !== null) {
            $stmtUpdateProximaOrdem = $pdo->prepare(
                "UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ? AND id_tenants = ?"
            );
            $stmtUpdateProximaOrdem->execute([$minOrdemDisponivel, $idCantor, ID_TENANTS]);
            error_log("DEBUG: proximo_ordem_musica do cantor " . $idCantor . " atualizado para " . $minOrdemDisponivel);
        } else {
            // Se não há músicas disponíveis, manter o proximo_ordem_musica como 1
            $stmtUpdateProximaOrdem = $pdo->prepare(
                "UPDATE cantores SET proximo_ordem_musica = 1 WHERE id = ? AND id_tenants = ?"
            );
            $stmtUpdateProximaOrdem->execute([$idCantor, ID_TENANTS]);
            error_log("DEBUG: proximo_ordem_musica do cantor " . $idCantor . " resetado para 1 (sem músicas disponíveis)");
        }
        
        $pdo->commit();
        error_log("DEBUG: Música cantada (ID: " . $musicaCantorId . ") movida para o final da fila do cantor " . $idCantor . " (posição " . $totalMusicas . ") e demais músicas reordenadas.");
        return true;
        
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao mover música cantada para o final da fila: " . $e->getMessage());
        return false;
    }
}