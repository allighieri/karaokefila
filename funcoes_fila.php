<?php
require_once 'config.php'; // A variável $pdo estará disponível aqui

/**
 * Adiciona uma nova mesa ao sistema.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeMesa Nome/identificador da mesa.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMesa(PDO $pdo, $nomeMesa) { // O parâmetro $tamanhoMesa foi removido
    try {
        // A coluna 'tamanho_mesa' na tabela 'mesas' DEVE ter um DEFAULT de 0 no seu schema do banco de dados.
        // Se não tiver, o MySQL/SQLite vai inserir 0 por padrão para INT ou dar erro se for NOT NULL sem default.
        // Se preferir ser explícito, poderia ser:
        // $stmt = $pdo->prepare("INSERT INTO mesas (nome_mesa, tamanho_mesa) VALUES (?, 0)");
        $stmt = $pdo->prepare("INSERT INTO mesas (nome_mesa) VALUES (?)");
        return $stmt->execute([$nomeMesa]);
    } catch (\PDOException $e) {
        error_log("Erro ao adicionar mesa: " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona um novo cantor e o associa a uma mesa.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $nomeCantor Nome do cantor.
 * @param int $idMesa ID da mesa à qual o cantor pertence.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarCantor(PDO $pdo, $nomeCantor, $idMesa) {
    try {
        $pdo->beginTransaction(); // Inicia a transação para garantir atomicidade

        // 1. Insere o novo cantor
        $stmt = $pdo->prepare("INSERT INTO cantores (nome_cantor, id_mesa) VALUES (?, ?)");
        $success = $stmt->execute([$nomeCantor, $idMesa]);

        if ($success) {
            // 2. Incrementa o 'tamanho_mesa' da mesa associada
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = tamanho_mesa + 1 WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit(); // Confirma ambas as operações se tudo deu certo
                return true;
            } else {
                $pdo->rollBack(); // Reverte a inserção do cantor se a atualização da mesa falhar
                error_log("Erro ao incrementar tamanho_mesa para a mesa ID: " . $idMesa);
                return false;
            }
        } else {
            $pdo->rollBack(); // Reverte se a inserção do cantor falhar
            error_log("Erro ao inserir o cantor: " . $nomeCantor);
            return false;
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) { // Garante que, se a transação foi iniciada, ela seja revertida
            $pdo->rollBack();
        }
        error_log("Erro ao adicionar cantor (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Remove um cantor e decrementa o tamanho_mesa da mesa associada.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $idCantor ID do cantor a ser removido.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function removerCantor(PDO $pdo, $idCantor) {
    try {
        $pdo->beginTransaction();

        // 1. Obter o id_mesa do cantor antes de excluí-lo
        $stmtGetMesaId = $pdo->prepare("SELECT id_mesa FROM cantores WHERE id = ?");
        $stmtGetMesaId->execute([$idCantor]);
        $idMesa = $stmtGetMesaId->fetchColumn();

        if ($idMesa === false) { // Cantor não encontrado
            $pdo->rollBack();
            error_log("Erro: Cantor ID " . $idCantor . " não encontrado para remoção.");
            return false;
        }

        // 2. Remover o cantor
        $stmtDeleteCantor = $pdo->prepare("DELETE FROM cantores WHERE id = ?");
        $successDelete = $stmtDeleteCantor->execute([$idCantor]);

        if ($successDelete) {
            // 3. Decrementar o 'tamanho_mesa' da mesa associada (se for maior que zero)
            $stmtUpdateMesa = $pdo->prepare("UPDATE mesas SET tamanho_mesa = GREATEST(0, tamanho_mesa - 1) WHERE id = ?");
            $updateSuccess = $stmtUpdateMesa->execute([$idMesa]);

            if ($updateSuccess) {
                $pdo->commit();
                return true;
            } else {
                $pdo->rollBack();
                error_log("Erro ao decrementar tamanho_mesa para a mesa ID: " . $idMesa);
                return false;
            }
        } else {
            $pdo->rollBack();
            error_log("Erro ao remover o cantor ID: " . $idCantor);
            return false;
        }
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro ao remover cantor (PDOException): " . $e->getMessage());
        return false;
    }
}

/**
 * Adiciona uma nova música ao repertório.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param string $titulo Título da música.
 * @param string $artista Artista da música.
 * @param int|null $duracaoSegundos Duração da música em segundos (opcional).
 * @return bool True em caso de sucesso, false caso contrário.
 */
function adicionarMusica(PDO $pdo, $titulo, $artista, $duracaoSegundos = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO musicas (titulo, artista, duracao_segundos) VALUES (?, ?, ?)");
        return $stmt->execute([$titulo, $artista, $duracaoSegundos]);
    } catch (\PDOException $e) {
        error_log("Erro ao adicionar música: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o número da rodada atual.
 * Retorna 0 se o sistema estiver em um estado "limpo" (sem rodadas ativas ou no histórico)
 * para que a próxima rodada a ser montada seja a 1.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O número da rodada atual (ou 0 se for a primeira rodada a ser criada).
 */
function getRodadaAtual(PDO $pdo) {
    try {
        // 1. Tenta obter a rodada_atual da tabela de controle.
        $stmt = $pdo->query("SELECT rodada_atual FROM controle_rodada WHERE id = 1");
        $rodadaAtualFromDB = $stmt->fetchColumn();

        // Converte para int e trata o caso de não haver registro (null/false).
        $rodadaAtualFromDB = ($rodadaAtualFromDB === false || $rodadaAtualFromDB === null) ? 0 : (int)$rodadaAtualFromDB;

        // 2. Verifica se existe *alguma* música com status 'aguardando' em *qualquer* rodada.
        // Se houver, a rodada ativa é a rodada dela.
        $stmtCheckAnyActiveFila = $pdo->query("SELECT rodada FROM fila_rodadas WHERE status = 'aguardando' OR status = 'em_execucao' ORDER BY rodada DESC LIMIT 1");
        $rodadaComMusicasAguardando = $stmtCheckAnyActiveFila->fetchColumn();

        if ($rodadaComMusicasAguardando !== false && $rodadaComMusicasAguardando !== null) {
            // Se encontrou músicas aguardando, a rodada atual é a rodada dessas músicas.
            return (int)$rodadaComMusicasAguardando;
        }

        // 3. Se não há músicas 'aguardando', verifica se existe *alguma* rodada com 'cantou' ou 'pulou'.
        // Isso indica que já houve rodadas no passado.
        $stmtMaxRodadaFinalizada = $pdo->query("SELECT MAX(rodada) FROM fila_rodadas WHERE status IN ('cantou', 'pulou')");
        $maxRodadaFinalizada = $stmtMaxRodadaFinalizada->fetchColumn();

        if ($maxRodadaFinalizada !== false && $maxRodadaFinalizada !== null) {
            // Se existem rodadas finalizadas, a "rodada atual" para fins de numeração
            // deve ser a última rodada finalizada. A próxima a ser criada será essa + 1.
            return (int)$maxRodadaFinalizada;
        }

        // 4. Se não há músicas aguardando E não há rodadas finalizadas,
        // significa que o sistema está em um estado "limpo" ou foi resetado.
        // Retorna 0 para que a próxima rodada a ser montada (0 + 1) seja a 1.
        return 0;

    } catch (\PDOException $e) {
        error_log("Erro ao obter rodada atual: " . $e->getMessage());
        // Em caso de erro, retorna 0 para garantir que o sistema possa iniciar.
        return 0;
    }
}


/**
 * Monta a próxima rodada na tabela fila_rodadas com base nas regras de prioridade por mesa
 * e nas músicas pré-selecionadas pelos cantores.
 * Cantores com todas as músicas cantadas em sua lista pré-selecionada não são reiniciados.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool True se a rodada foi montada, false se não houver cantores elegíveis ou músicas.
 */
function montarProximaRodada(PDO $pdo) {
    error_log("DEBUG: Início da função montarProximaRodada.");

    // PRIMEIRO: Verificar se a rodada atual está finalizada
    if (!isRodadaAtualFinalizada($pdo)) {
        error_log("INFO: Não foi possível montar a próxima rodada. A rodada atual ainda não foi finalizada.");
        return false;
    }

    $rodadaAtual = getRodadaAtual($pdo);
    $proximaRodada = $rodadaAtual + 1;
    error_log("DEBUG: Rodada atual: " . $rodadaAtual . ", Próxima rodada: " . $proximaRodada);

    try {
        $pdo->beginTransaction();
        error_log("DEBUG: Transação iniciada.");

        $filaParaRodada = [];
        $ordem = 1;

        $statusMesasNaRodada = [];
        $cantoresQueJaCantaramNestaRodada = [];

        // Obter todos os cantores e suas informações relevantes UMA ÚNICA VEZ DO BANCO DE DADOS
        $sqlTodosCantoresInfo = "
            SELECT
                c.id AS id_cantor,
                c.nome_cantor,
                m.id AS id_mesa,
                m.nome_mesa,
                m.tamanho_mesa,
                c.proximo_ordem_musica,
                (SELECT COUNT(fr.id) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou') AS total_cantos_cantor,
                (SELECT MAX(fr.timestamp_fim_canto) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou') AS ultima_vez_cantou_cantor,
                (SELECT MAX(fr_inner.timestamp_fim_canto)
                   FROM fila_rodadas fr_inner
                   JOIN cantores c_inner ON fr_inner.id_cantor = c_inner.id
                   WHERE c_inner.id_mesa = m.id AND fr_inner.status = 'cantou') AS ultima_vez_cantou_mesa
            FROM cantores c
            JOIN mesas m ON c.id_mesa = m.id
            ORDER BY
                total_cantos_cantor ASC,
                ultima_vez_cantou_mesa IS NULL DESC,
                ultima_vez_cantou_mesa ASC,
                ultima_vez_cantou_cantor IS NULL DESC,
                ultima_vez_cantou_cantor ASC,
                RAND()
        ";
        $stmtTodosCantores = $pdo->query($sqlTodosCantoresInfo);
        $cantoresDisponiveisGlobal = $stmtTodosCantores->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cantoresDisponiveisGlobal)) {
            $pdo->rollBack();
            error_log("INFO: Não há cantores cadastrados para montar a rodada.");
            return false;
        }

        // Calcula uma estimativa de quantos slots a rodada pode ter para otimizar o loop
        $totalCantoresComMusicas = 0;
        foreach ($cantoresDisponiveisGlobal as $c) {
            $stmtCheckMusicas = $pdo->prepare("SELECT COUNT(*) FROM musicas_cantor WHERE id_cantor = ? AND status IN ('aguardando', 'pulou')");
            $stmtCheckMusicas->execute([$c['id_cantor']]);
            if ($stmtCheckMusicas->fetchColumn() > 0) {
                $totalCantoresComMusicas++;
            }
        }
        
        $totalSlotsEstimados = 0;
        foreach ($cantoresDisponiveisGlobal as $cantor) {
            $tamanhoMesa = $cantor['tamanho_mesa'];
            if ($tamanhoMesa >= 6) $totalSlotsEstimados += 3;
            elseif ($tamanhoMesa >= 3 && $tamanhoMesa < 6) $totalSlotsEstimados += 2;
            else $totalSlotsEstimados += 1;
        }

        $maxLoopIterations = max($totalCantoresComMusicas * 3, count($cantoresDisponiveisGlobal) * 5, 100);
        $currentLoopIteration = 0;
        
        error_log("DEBUG: Iniciando loop de montagem da fila. Max Iterations: " . $maxLoopIterations . ". Cantores globais: " . count($cantoresDisponiveisGlobal) . ". Total slots estimados: " . $totalSlotsEstimados);

        // Loop principal para montar a fila
        while ($currentLoopIteration < $maxLoopIterations) {
            $currentLoopIteration++;
            $foundCantorThisIteration = false;

            $cantoresElegiveisNestaPassagem = array_filter($cantoresDisponiveisGlobal, function($cantor) use ($cantoresQueJaCantaramNestaRodada) {
                return !in_array($cantor['id_cantor'], $cantoresQueJaCantaramNestaRodada);
            });

            if (empty($cantoresElegiveisNestaPassagem)) {
                error_log("DEBUG: Todos os cantores elegíveis (que não cantaram nesta rodada) foram processados. Quebrando o loop de montagem da rodada.");
                break;
            }
            
            // Ordena os cantores restantes baseado na prioridade E no histórico da mesa nesta rodada
            usort($cantoresElegiveisNestaPassagem, function($a, $b) use ($statusMesasNaRodada) {
                $idMesaA = $a['id_mesa'];
                $idMesaB = $b['id_mesa'];

                if (!isset($statusMesasNaRodada[$idMesaA])) {
                    $statusMesasNaRodada[$idMesaA] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                }
                if (!isset($statusMesasNaRodada[$idMesaB])) {
                    $statusMesasNaRodada[$idMesaB] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                }

                $musicasA = $statusMesasNaRodada[$idMesaA]['musicas_adicionadas'];
                $musicasB = $statusMesasNaRodada[$idMesaB]['musicas_adicionadas'];

                $ultimaAddA = $statusMesasNaRodada[$idMesaA]['ultima_adicao_timestamp'];
                $ultimaAddB = $statusMesasNaRodada[$idMesaB]['ultima_adicao_timestamp'];
                
                $maxMusicasMesaA = 1;
                if ($a['tamanho_mesa'] >= 3 && $a['tamanho_mesa'] < 6) $maxMusicasMesaA = 2;
                elseif ($a['tamanho_mesa'] >= 6) $maxMusicasMesaA = 3;

                $maxMusicasMesaB = 1;
                if ($b['tamanho_mesa'] >= 3 && $b['tamanho_mesa'] < 6) $maxMusicasMesaB = 2;
                elseif ($b['tamanho_mesa'] >= 6) $maxMusicasMesaB = 3;

                if ($musicasA < $maxMusicasMesaA && $musicasB >= $maxMusicasMesaB) return -1;
                if ($musicasA >= $maxMusicasMesaA && $musicasB < $maxMusicasMesaB) return 1;

                if ($musicasA !== $musicasB) return $musicasA - $musicasB;

                if ($ultimaAddA !== $ultimaAddB) return $ultimaAddA - $ultimaAddB;

                if ($a['total_cantos_cantor'] !== $b['total_cantos_cantor']) return $a['total_cantos_cantor'] - $b['total_cantos_cantor'];
                
                if ($a['ultima_vez_cantou_mesa'] === null && $b['ultima_vez_cantou_mesa'] !== null) return -1;
                if ($a['ultima_vez_cantou_mesa'] !== null && $b['ultima_vez_cantou_mesa'] === null) return 1;
                if ($a['ultima_vez_cantou_mesa'] !== null && $b['ultima_vez_cantou_mesa'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_mesa']) - strtotime($b['ultima_vez_cantou_mesa']);
                    if ($cmp !== 0) return $cmp;
                }

                if ($a['ultima_vez_cantou_cantor'] === null && $b['ultima_vez_cantou_cantor'] !== null) return -1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] === null) return 1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_cantor']) - strtotime($b['ultima_vez_cantou_cantor']);
                    if ($cmp !== 0) return $cmp;
                }
                
                return 0;
            });

            foreach ($cantoresElegiveisNestaPassagem as $cantorParaSelecionar) {
                $idCantor = $cantorParaSelecionar['id_cantor'];
                $idMesa = $cantorParaSelecionar['id_mesa'];
                $tamanhoMesa = $cantorParaSelecionar['tamanho_mesa'];
                $currentProximoOrdemMusica = $cantorParaSelecionar['proximo_ordem_musica'];

                $maxMusicasMesa = 1;
                if ($tamanhoMesa >= 3 && $tamanhoMesa < 6) {
                    $maxMusicasMesa = 2;
                } elseif ($tamanhoMesa >= 6) {
                    $maxMusicasMesa = 3;
                }

                if (!isset($statusMesasNaRodada[$idMesa])) {
                    $statusMesasNaRodada[$idMesa] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                }

                if ($statusMesasNaRodada[$idMesa]['musicas_adicionadas'] < $maxMusicasMesa) {
                    $sqlProximaMusicaCantor = "
                        SELECT
                            mc.id AS musica_cantor_id, -- <<< ADICIONADO AQUI
                            mc.id_musica,
                            mc.ordem_na_lista
                        FROM musicas_cantor mc
                        WHERE mc.id_cantor = :id_cantor
                        AND mc.ordem_na_lista >= :proximo_ordem_musica
                        AND mc.status IN ('aguardando', 'pulou')
                        ORDER BY mc.ordem_na_lista ASC
                        LIMIT 1
                    ";
                    $stmtProximaMusica = $pdo->prepare($sqlProximaMusicaCantor);
                    $stmtProximaMusica->execute([
                        ':id_cantor' => $idCantor,
                        ':proximo_ordem_musica' => $currentProximoOrdemMusica
                    ]);
                    $musicaData = $stmtProximaMusica->fetch(PDO::FETCH_ASSOC);

                    $musicaId = $musicaData ? $musicaData['id_musica'] : null;
                    $musicaCantorId = $musicaData ? $musicaData['musica_cantor_id'] : null; // <<< CAPTURADO AQUI
                    $ordemMusicaSelecionada = $musicaData ? $musicaData['ordem_na_lista'] : null;

                    if (!$musicaId || !$musicaCantorId) { // Verifique também o musicaCantorId
                        error_log("INFO: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") não possui mais músicas disponíveis (status 'aguardando' ou 'pulou') em sua lista (proximo_ordem_musica: " . $currentProximoOrdemMusica . "). Pulando-o para esta rodada.");
                        $cantoresQueJaCantaramNestaRodada[] = $idCantor;
                        continue;
                    }

                    $filaParaRodada[] = [
                        'id_cantor' => $idCantor,
                        'id_musica' => $musicaId,
                        'musica_cantor_id' => $musicaCantorId, // <<< ADICIONADO AQUI NO ARRAY
                        'ordem_na_rodada' => $ordem++,
                        'rodada' => $proximaRodada
                    ];
                    
                    // IMPORTANTE: Aqui, o status da musicas_cantor é atualizado para 'selecionada_para_rodada'
                    // Lembre-se que o status na musicas_cantor é o status *desejado* ou atual de seleção.
                    // O status da fila é o status *real* da rodada.
                    $stmtUpdateMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?"); // Use 'id' para atualizar pelo ID único
                    $stmtUpdateMusicaCantorStatus->execute([$musicaCantorId]); // <<< USE musicaCantorId
                    error_log("DEBUG: Status da música_cantor_id " . $musicaCantorId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");
                    
                    $statusMesasNaRodada[$idMesa]['musicas_adicionadas']++;
                    $statusMesasNaRodada[$idMesa]['ultima_adicao_timestamp'] = microtime(true);

                    $cantoresQueJaCantaramNestaRodada[] = $idCantor;
                    $foundCantorThisIteration = true;

                    $novaProximaOrdem = $ordemMusicaSelecionada + 1;
                    $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
                    $stmtUpdateCantorOrder->execute([$novaProximaOrdem, $idCantor]);
                    error_log("DEBUG: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") próxima ordem atualizada no DB para: " . $novaProximaOrdem);
                    
                    foreach ($cantoresDisponiveisGlobal as $key => $globalCantor) {
                        if ($globalCantor['id_cantor'] === $idCantor) {
                            $cantoresDisponiveisGlobal[$key]['proximo_ordem_musica'] = $novaProximaOrdem;
                            break;
                        }
                    }
                    
                    break;
                } else {
                    error_log("INFO: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") pulado: Mesa " . $idMesa . " já atingiu o limite de " . $maxMusicasMesa . " músicas nesta rodada.");
                }
            }

            if (!$foundCantorThisIteration) {
                error_log("DEBUG: Nenhuma música foi adicionada nesta iteração do loop principal.");
                if (empty($filaParaRodada) && empty($cantoresElegiveisNestaPassagem)) {
                    error_log("DEBUG: Fila vazia e não há mais cantores elegíveis que possam cantar. Quebrando.");
                    break;
                }
                
                $canAnyCantorStillSing = false;
                foreach ($cantoresDisponiveisGlobal as $c) {
                    if (in_array($c['id_cantor'], $cantoresQueJaCantaramNestaRodada)) {
                        continue;
                    }

                    $hasMusic = $pdo->prepare("SELECT COUNT(*) FROM musicas_cantor WHERE id_cantor = ? AND ordem_na_lista >= ? AND status IN ('aguardando', 'pulou')");
                    $hasMusic->execute([$c['id_cantor'], $c['proximo_ordem_musica']]);
                    if ($hasMusic->fetchColumn() > 0) {
                        $mesaId = $c['id_mesa'];
                        $tamanhoMesa = $c['tamanho_mesa'];
                        $maxMusicasMesa = 1;
                        if ($tamanhoMesa >= 3 && $tamanhoMesa < 6) $maxMusicasMesa = 2;
                        elseif ($tamanhoMesa >= 6) $maxMusicasMesa = 3;

                        if (!isset($statusMesasNaRodada[$mesaId])) {
                            $statusMesasNaRodada[$mesaId] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                        }

                        if ($statusMesasNaRodada[$mesaId]['musicas_adicionadas'] < $maxMusicasMesa) {
                            $canAnyCantorStillSing = true;
                            break;
                        }
                    }
                }

                if (!$canAnyCantorStillSing && !empty($filaParaRodada)) {
                    error_log("DEBUG: Não há mais cantores elegíveis (com música e slot de mesa) para adicionar. Fila contém itens. Quebrando o loop.");
                    break;
                } elseif (!$canAnyCantorStillSing && empty($filaParaRodada)) {
                    error_log("DEBUG: Nenhuma música foi adicionada e não há cantores elegíveis. Quebrando o loop.");
                    break;
                }
            }
            
            if (count($filaParaRodada) >= $totalCantoresComMusicas * 2 + 5 && $totalCantoresComMusicas > 0) {
                error_log("DEBUG: Fila atingiu tamanho máximo razoável. Quebrando o loop.");
                break;
            }

        } // Fim do while de montagem da fila
        error_log("DEBUG: Fim do loop de montagem da fila. Itens na filaParaRodada: " . count($filaParaRodada));

        if (empty($filaParaRodada)) {
            $pdo->rollBack();
            error_log("DEBUG: filaParaRodada está vazia após o loop. Rollback e retorno false. Pode ser que não haja cantores com músicas disponíveis para cantar ou que já atingiram o limite.");
            return false;
        }

        // --- Limpa a fila antiga antes de inserir a nova rodada ---
        $stmtDeleteOldQueue = $pdo->prepare("DELETE FROM fila_rodadas WHERE rodada < ?");
        $stmtDeleteOldQueue->execute([$proximaRodada]);
        error_log("DEBUG: Fila_rodadas antigas limpas para rodadas anteriores a " . $proximaRodada);

        // MODIFICAÇÃO AQUI: Inserir a primeira música como 'em_execucao' e as demais como 'aguardando'
        $firstItem = true;
        foreach ($filaParaRodada as $item) {
            $status = 'aguardando';
            $timestamp_inicio_canto = 'NULL';

            if ($firstItem) {
                $status = 'em_execucao';
                $timestamp_inicio_canto = 'NOW()'; // Define o tempo de início para a primeira música
                $firstItem = false;

                // Também atualiza o status na tabela musicas_cantor para 'em_execucao'
                // Use musica_cantor_id para garantir a atualização da entrada correta
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ?"); // <<< USE 'id'
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id']]); // <<< USE musica_cantor_id
                error_log("DEBUG: Status da primeira música (musica_cantor_id: " . $item['musica_cantor_id'] . ") atualizado para 'em_execucao' na tabela musicas_cantor.");
            } else {
                // Para as demais músicas, garanta que não estão em_execucao, mas sim selecionadas
                // (já foi feito no loop de montagem da fila, mas é bom ter certeza)
                // Use musica_cantor_id para garantir a atualização da entrada correta
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ? AND status != 'em_execucao'"); // <<< USE 'id'
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id']]); // <<< USE musica_cantor_id
            }
            
            // INSIRA O musica_cantor_id NA TABELA FILA_RODADAS
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, status, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, " . $timestamp_inicio_canto . ")"); // <<< ADICIONADO musica_cantor_id
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", Música " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Status " . $status);
            $stmtInsert->execute([$item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $status]); // <<< PASSADO O VALOR
        }
        error_log("DEBUG: Itens inseridos na fila_rodadas.");

        // Atualiza o controle de rodada
        // Garante que a rodada atual do sistema é a nova rodada.
        // Se for a primeira rodada (controle_rodada vazio), insere. Caso contrário, atualiza.
        $stmtCheckControl = $pdo->query("SELECT COUNT(*) FROM controle_rodada WHERE id = 1")->fetchColumn();
        if ($stmtCheckControl == 0) {
            $pdo->exec("INSERT INTO controle_rodada (id, rodada_atual) VALUES (1, " . $proximaRodada . ")");
            error_log("DEBUG: controle_rodada inserido com a rodada " . $proximaRodada);
        } else {
            $stmtUpdateRodada = $pdo->prepare("UPDATE controle_rodada SET rodada_atual = ? WHERE id = 1");
            $stmtUpdateRodada->execute([$proximaRodada]);
            error_log("DEBUG: controle_rodada atualizado para a rodada " . $proximaRodada);
        }
        
        $pdo->commit();
        error_log("DEBUG: Transação commitada. Retornando true.");
        return true;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("DEBUG: Transação rollback devido a erro.");
        }
        error_log("Erro ao montar próxima rodada (PDOException): " . $e->getMessage());
        return false;
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
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
				fr.musica_cantor_id, -- Adicionar este campo também
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'aguardando'
            ORDER BY fr.ordem_na_rodada ASC
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
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
    $rodadaAtual = getRodadaAtual($pdo); // Assume que getRodadaAtual existe e funciona
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                fr.id_cantor,         -- Adicionar para consistência
                fr.id_musica,         -- Adicionar para consistência
                fr.musica_cantor_id,  -- <<< ADICIONAR ESTA LINHA AQUI
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                m.codigo AS codigo_musica, -- Adicionar código da música se precisar
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ? AND fr.status = 'em_execucao'
            LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter música em execução: " . $e->getMessage());
        return null;
    }
}


/**
 * Troca a música de um item na fila de rodadas.
 * A música original não é removida da lista pré-selecionada do cantor.
 * O proximo_ordem_musica do cantor é decrementado para que a música original possa ser considerada novamente.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas a ser atualizado.
 * @param int $novaMusicaId ID da nova música a ser definida para o item da fila.
 * @return bool True em caso de sucesso, false caso contrário.
 */
function trocarMusicaNaFilaAtual(PDO $pdo, $filaId, $novaMusicaId) {
    try {
        $pdo->beginTransaction(); // Inicia transação para garantir atomicidade

        // 1. Verificar se a filaId existe e o status é 'aguardando' ou 'em_execucao'.
        // Alterado de 'cantando' para 'em_execucao' para padronizar
        $stmtGetOldMusicInfo = $pdo->prepare("SELECT id_cantor, id_musica FROM fila_rodadas WHERE id = ? AND (status = 'aguardando' OR status = 'em_execucao')");
        $stmtGetOldMusicInfo->execute([$filaId]);
        $filaItem = $stmtGetOldMusicInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Tentativa de trocar música em item da fila inexistente ou já finalizado (ID: " . $filaId . ").");
            $pdo->rollBack();
            return false;
        }

        $idCantor = $filaItem['id_cantor'];
        $musicaOriginalId = $filaItem['id_musica'];

        // 2. Encontrar a ordem_na_lista da música original que estava na fila
        $stmtGetOriginalOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id_cantor = ? AND id_musica = ? ORDER BY ordem_na_lista ASC LIMIT 1");
        $stmtGetOriginalOrder->execute([$idCantor, $musicaOriginalId]);
        $ordemMusicaOriginal = $stmtGetOriginalOrder->fetchColumn();

        if ($ordemMusicaOriginal !== false) {
            // 3. Atualizar o proximo_ordem_musica do cantor para a ordem da música original
            // Isso efetivamente "devolve" a música original para a posição de próxima a ser selecionada,
            // ou pelo menos para a posição dela na lista do cantor.
            $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
            $stmtUpdateCantorOrder->execute([$ordemMusicaOriginal, $idCantor]);
            error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaOriginal . " após troca de música (fila_id: " . $filaId . ").");
           
            // Atualiza o status da música ORIGINAL na tabela musicas_cantor de volta para 'aguardando'
            $stmtUpdateOriginalMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id_cantor = ? AND id_musica = ?");
            $stmtUpdateOriginalMusicaCantorStatus->execute([$idCantor, $musicaOriginalId]);
            error_log("DEBUG: Status da música original " . $musicaOriginalId . " do cantor " . $idCantor . " resetado para 'aguardando' na tabela musicas_cantor.");
           
        } else {
            error_log("Alerta: Música original (ID: " . $musicaOriginalId . ") do item da fila (ID: " . $filaId . ") não encontrada na lista musicas_cantor para o cantor (ID: " . $idCantor . "). Não foi possível resetar o proximo_ordem_musica.");
        }

        // 4. Atualiza o id_musica na tabela fila_rodadas com a nova música
        $stmtUpdateFila = $pdo->prepare("UPDATE fila_rodadas SET id_musica = ? WHERE id = ?");
        $result = $stmtUpdateFila->execute([$novaMusicaId, $filaId]);

        if ($result) {
           
            // Atualiza o status da NOVA música na tabela musicas_cantor para 'selecionada_para_rodada'
            $stmtUpdateNewMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id_cantor = ? AND id_musica = ?");
            $stmtUpdateNewMusicaCantorStatus->execute([$idCantor, $novaMusicaId]);
            error_log("DEBUG: Status da nova música " . $novaMusicaId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");

           
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
 * Utiliza a coluna 'ordem_na_rodada' da tabela 'fila_rodadas'.
 *
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $rodada O número da rodada a ser atualizada.
 * @param array $novaOrdemFila Um array onde a chave é o ID do item da fila (fila_rodadas.id)
 * e o valor é a nova posição (ordem_na_rodada).
 * Ex: [101 => 1, 105 => 2, 103 => 3] onde 101, 105, 103 são IDs da tabela fila_rodadas.
 * @return bool True se a atualização for bem-sucedida, false caso contrário.
 */
function atualizarOrdemFila(PDO $pdo, int $rodada, array $novaOrdemFila): bool {
    if (empty($novaOrdemFila)) {
        error_log("DEBUG: Array de nova ordem da fila vazio. Nenhuma atualização realizada.");
        return true; // Nada para atualizar, considera sucesso
    }

    try {
        $pdo->beginTransaction();

        // Altera a coluna para 'ordem_na_rodada'
        $stmt = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = ? WHERE id = ? AND rodada = ?");

        foreach ($novaOrdemFila as $filaItemId => $novaPosicao) {
            // Garante que a novaPosicao seja um inteiro
            $novaPosicaoInt = (int)$novaPosicao;
            if (!$stmt->execute([$novaPosicaoInt, $filaItemId, $rodada])) {
                error_log("ERRO: Falha ao atualizar ordem do item " . $filaItemId . " para posição " . $novaPosicaoInt);
                $pdo->rollBack();
                return false;
            }
        }

        $pdo->commit();
        error_log("DEBUG: Ordem da fila da rodada " . $rodada . " (usando ordem_na_rodada) atualizada com sucesso.");
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
    // Se não há músicas para reordenar, retorna sucesso.
    if (empty($novaOrdemMusicas)) {
        return true;
    }

    $pdo->beginTransaction();
    try {
        // Define os status que impedem a reordenação
        $restricted_statuses = ['cantou', 'em_execucao', 'selecionada_para_rodada'];

        // 1. Obter os status atuais de todas as músicas que estão sendo potencialmente reordenadas
        $ids_musicas_cantor = array_keys($novaOrdemMusicas);
        // Cria placeholders para a query IN clause (?, ?, ?, ...)
        $placeholders = implode(',', array_fill(0, count($ids_musicas_cantor), '?'));

        $stmtCheckStatus = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id IN ($placeholders) AND id_cantor = ?");
        // Combina os IDs das músicas e o ID do cantor para a execução da query
        $stmtCheckStatus->execute(array_merge($ids_musicas_cantor, [$idCantor]));
        // Busca os resultados como um array associativo [id => status] para fácil lookup
        $currentStatuses = $stmtCheckStatus->fetchAll(PDO::FETCH_KEY_PAIR);

        // Prepara a query para atualizar a ordem de um item específico
        $stmtUpdate = $pdo->prepare("UPDATE musicas_cantor SET ordem_na_lista = ? WHERE id = ? AND id_cantor = ?");

        // 2. Iterar sobre a nova ordem e aplicar as atualizações APENAS se o status permitir
        foreach ($novaOrdemMusicas as $musicaCantorId => $novaPosicao) {
            // Garante que os IDs e posições são inteiros válidos.
            $musicaCantorId = (int) $musicaCantorId;
            $novaPosicao = (int) $novaPosicao;

            // Verifica se a música existe e se seu status NÃO é um dos restritos
            if (isset($currentStatuses[$musicaCantorId]) && !in_array($currentStatuses[$musicaCantorId], $restricted_statuses)) {
                // Se o status permitir, executa a atualização
                if (!$stmtUpdate->execute([$novaPosicao, $musicaCantorId, $idCantor])) {
                    $pdo->rollBack(); // Se uma atualização falhar, reverte todas
                    error_log("Erro ao executar UPDATE para musicas_cantor ID: $musicaCantorId, nova_posicao: $novaPosicao, cantor ID: $idCantor");
                    return false;
                }
            } else {
                // Se a música tem um status restrito ou não foi encontrada para este cantor,
                // vamos logar isso e continuar, ou você pode optar por reverter tudo aqui.
                // Como o frontend já impede o arrasto, isso serve mais como uma camada de segurança.
                error_log("Tentativa de reordenar música com status restrito ou ID inválido para o cantor $idCantor: musica_cantor_id=$musicaCantorId, status=" . ($currentStatuses[$musicaCantorId] ?? 'N/A'));
                // Se você quiser que a transação inteira falhe se qualquer item restrito for enviado:
                // $pdo->rollBack();
                // return false;
            }
        }

        $pdo->commit(); // Confirma todas as atualizações se tudo correu bem
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack(); // Em caso de exceção, reverte a transação
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
    try {
        $stmt = $pdo->query("SELECT id, titulo, artista FROM musicas ORDER BY titulo ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Erro ao obter todas as músicas: " . $e->getMessage());
        return [];
    }
}


/**
 * Obtém a lista completa da fila para a rodada atual.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return array Lista de itens da fila.
 */
function getFilaCompleta(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        $sql = "
            SELECT
                fr.id AS fila_id,
                c.nome_cantor,
                m.titulo AS titulo_musica,
                m.artista AS artista_musica,
                me.nome_mesa,
                me.tamanho_mesa,
                fr.status,
                fr.ordem_na_rodada
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
            JOIN musicas m ON fr.id_musica = m.id
            JOIN mesas me ON c.id_mesa = me.id
            WHERE fr.rodada = ?
            ORDER BY
                CASE
                    WHEN fr.status = 'em_execucao' THEN 0 -- A música em execução deve vir primeiro
                    WHEN fr.status = 'aguardando' THEN 1
                    WHEN fr.status = 'selecionada_para_rodada' THEN 2
                    WHEN fr.status = 'pulou' THEN 3
                    WHEN fr.status = 'cantou' THEN 4
                    ELSE 5 -- Para qualquer outro status futuro
                END,
                fr.ordem_na_rodada ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        return $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("Erro ao obter fila completa: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica se todas as músicas da rodada atual foram marcadas como 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return bool True se a rodada atual estiver finalizada, false caso contrário.
 */
function isRodadaAtualFinalizada(PDO $pdo) {
    $rodadaAtual = getRodadaAtual($pdo);
    try {
        // Verifica se existe alguma música com status 'aguardando' ou 'em_execucao'
        $sql = "SELECT COUNT(*) FROM fila_rodadas WHERE rodada = ? AND (status = 'aguardando' OR status = 'em_execucao')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$rodadaAtual]);
        $musicasPendentes = $stmt->fetchColumn();

        return $musicasPendentes === 0; // Se não houver músicas 'aguardando' ou 'em_execucao', a rodada está finalizada
    } catch (\PDOException $e) {
        error_log("Erro ao verificar status da rodada atual: " . $e->getMessage());
        return false; // Em caso de erro, consideramos que não está finalizada para evitar problemas
    }
}

/**
 * Marca uma música na fila como 'em_execucao', 'cantou' ou 'pulou'.
 * @param PDO $pdo Objeto de conexão PDO.
 * @param int $filaId ID do item na fila_rodadas.
 * @param string $status Novo status ('em_execucao', 'cantou', 'pulou').
 * @return bool True em caso de sucesso, false caso contrário.
 */
function atualizarStatusMusicaFila(PDO $pdo, $filaId, $status) {
    error_log("DEBUG: Chamada atualizarStatusMusicaFila com filaId: " . $filaId . ", status: " . $status); // ADICIONADO
    try {
        // Primeiro, obtenha o musica_cantor_id, id_cantor e id_musica associados a este filaId
        $stmtGetInfo = $pdo->prepare("SELECT musica_cantor_id, id_cantor, id_musica FROM fila_rodadas WHERE id = ?");
        $stmtGetInfo->execute([$filaId]);
        $filaItem = $stmtGetInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Item da fila (ID: " . $filaId . ") não encontrado para atualizar status.");
            return false;
        }

        $musicaCantorId = $filaItem['musica_cantor_id']; // <-- NOVO: Obter o ID da tabela musicas_cantor
        $idCantor = $filaItem['id_cantor']; // Manter, pode ser útil para outras lógicas, mas não para o UPDATE final
        $idMusica = $filaItem['id_musica']; // Manter, pode ser útil

        $pdo->beginTransaction();

        $successFilaUpdate = false;
        $successMusicasCantorUpdate = true; // Assume true a menos que falhe

        if ($status === 'em_execucao') {
            $rodadaAtual = getRodadaAtual($pdo);

            // 1. Resetar QUALQUER música que ESTAVA 'em_execucao' na fila_rodadas da rodada atual
            // para 'aguardando'. Isso garante que apenas uma música esteja 'em_execucao' por vez.
            $stmtResetPreviousExecution = $pdo->prepare("UPDATE fila_rodadas SET status = 'aguardando', timestamp_inicio_canto = NULL, timestamp_fim_canto = NULL WHERE rodada = ? AND status = 'em_execucao'");
            $stmtResetPreviousExecution->execute([$rodadaAtual]);
            error_log("DEBUG: Músicas anteriormente 'em_execucao' na fila_rodadas resetadas para 'aguardando' na rodada " . $rodadaAtual . ".");

            // 2. Definir a nova música como 'em_execucao' na fila_rodadas.
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_inicio_canto = NOW(), timestamp_fim_canto = NULL WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (em_execucao): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // 3. Atualizar o status NA TABELA musicas_cantor para 'em_execucao' SOMENTE para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (em_execucao): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'cantou') {
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (cantou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // Atualiza o status na tabela musicas_cantor para 'cantou' para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'cantou' WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (cantou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } elseif ($status === 'pulou') {
            // Reobter a ordem da música pulada para a atualização do proximo_ordem_musica
            // ATENÇÃO: Se houver duplicatas em musicas_cantor, esta lógica de ordem pode precisar de mais refinamento.
            // O ideal seria que musica_cantor_id fosse o suficiente para identificar a ordem.
            // Para manter a consistência com o ID específico, podemos buscar a ordem pela musica_cantor_id
            $stmtGetOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ?"); // <-- ALTERADO AQUI!
            $stmtGetOrder->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            $ordemMusicaPulada = $stmtGetOrder->fetchColumn();

            if ($ordemMusicaPulada !== false) {
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = GREATEST(1, ?) WHERE id = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaPulada, $idCantor]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaPulada . " após música pulada (fila_id: " . $filaId . ").");
            } else {
                error_log("Alerta: Música pulada (musica_cantor_id: " . $musicaCantorId . ") não encontrada na lista musicas_cantor. Não foi possível resetar o proximo_ordem_musica.");
                $successMusicasCantorUpdate = false; // Sinaliza falha na parte de resetar a ordem
            }

            // Atualiza o status na tabela fila_rodadas para 'pulou'
            $stmt = $pdo->prepare("UPDATE fila_rodadas SET status = ?, timestamp_fim_canto = NOW() WHERE id = ?");
            $successFilaUpdate = $stmt->execute([$status, $filaId]);
            error_log("DEBUG: Resultado do UPDATE fila_rodadas (pulou): " . ($successFilaUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmt->rowCount());

            // Atualiza o status na tabela musicas_cantor para 'aguardando' para o registro ESPECÍFICO (musica_cantor_id).
            $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ?"); // <-- ALTERADO AQUI!
            $successMusicasCantorUpdate = $stmtUpdateMusicasCantor->execute([$musicaCantorId]); // <-- ALTERADO AQUI!
            error_log("DEBUG: Resultado do UPDATE musicas_cantor (pulou): " . ($successMusicasCantorUpdate ? 'true' : 'false') . ", linhas afetadas: " . $stmtUpdateMusicasCantor->rowCount());

        } else {
            error_log("Erro: Status inválido na função atualizarStatusMusicaFila: " . $status);
            $pdo->rollBack();
            return false;
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
/**
 * Função auxiliar para obter um ID de música aleatória.
 * Em um sistema real, o MC escolheria a música.
 * @param PDO $pdo Objeto de conexão PDO.
 * @return int O ID de uma música aleatória, ou 0 se não houver músicas.
 */
function getRandomMusicaId(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM musicas ORDER BY RAND() LIMIT 1");
        $row = $stmt->fetch();
        return $row ? $row['id'] : 0;
    } catch (\PDOException $e) {
        error_log("Erro ao obter música aleatória: " . $e->getMessage());
        return 0;
    }
}

// Inicializa a tabela controle_rodada com ID 1 e rodada 1, se não existir
try {
    $pdo->exec("INSERT IGNORE INTO controle_rodada (id, rodada_atual) VALUES (1, 1)");
} catch (\PDOException $e) {
    error_log("Erro ao inicializar controle_rodada na inicialização do script: " . $e->getMessage());
}

// Popula algumas músicas de exemplo se o banco estiver vazio
try {
    $stmtMusicas = $pdo->query("SELECT COUNT(*) FROM musicas");
    $countMusicas = $stmtMusicas->fetchColumn();
    if ($countMusicas == 0) {
        adicionarMusica($pdo, "Bohemian Rhapsody", "Queen", 354);
        adicionarMusica($pdo, "Evidências", "Chitãozinho & Xororó", 270);
        adicionarMusica($pdo, "Billie Jean", "Michael Jackson", 294);
        adicionarMusica($pdo, "Garota de Ipanema", "Tom Jobim & Vinicius de Moraes", 180);
        adicionarMusica($pdo, "Anunciação", "Alceu Valença", 190);
        adicionarMusica($pdo, "Música teste 1", "Cantor Teste 1", 100);
        adicionarMusica($pdo, "Música teste 2", "Cantor Teste 2", 100);
        adicionarMusica($pdo, "Música teste 3", "Cantor Teste 3", 100);
        adicionarMusica($pdo, "Música teste 4", "Cantor Teste 4", 100);
        adicionarMusica($pdo, "Música teste 5", "Cantor Teste 5", 100);
    }
} catch (\PDOException $e) {
    error_log("Erro ao popular músicas de exemplo na inicialização do script: " . $e->getMessage());
}

/**
 * Reseta o 'proximo_ordem_musica' de todos os cantores para 1,
 * o status de todas as músicas para 'aguardando' e o 'timestamp_ultima_execucao' para NULL,
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas'.
 * Isso efetivamente reinicia todo o estado da fila do karaokê.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarTudoFila(PDO $pdo): bool { // Adicionei o tipo de retorno bool
    try {
        // Não usamos transação aqui porque TRUNCATE TABLE faz um COMMIT implícito.
        // Se uma falhar, as anteriores já foram commitadas.
        // Se precisasse ser tudo ou nada, teríamos que usar DELETE FROM e transação.
        // Para um reset, TRUNCATE é mais eficiente.

        // 1. Resetar 'proximo_ordem_musica' dos cantores
        $stmtCantores = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = 1");
        $stmtCantores->execute();
        error_log("DEBUG: Todos os 'proximo_ordem_musica' dos cantores foram resetados para 1.");

        // 2. Resetar 'status' e 'timestamp_ultima_execucao' de todas as músicas na tabela musicas_cantor
        $stmtMusicasCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando', timestamp_ultima_execucao = NULL");
        $stmtMusicasCantorStatus->execute();
        error_log("DEBUG: Todos os 'status' na tabela musicas_cantor foram resetados para 'aguardando' e 'timestamp_ultima_execucao' para NULL.");

        // 3. Truncar tabela 'fila_rodadas'
        // TRUNCATE TABLE faz um commit implícito, então as operações acima já serão salvas.
        $stmtFila = $pdo->prepare("TRUNCATE TABLE fila_rodadas");
        $stmtFila->execute();
        error_log("DEBUG: Tabela 'fila_rodadas' truncada.");

        // 4. Truncar tabela 'controle_rodada'
        $stmtControle = $pdo->prepare("TRUNCATE TABLE controle_rodada");
        $stmtControle->execute();
        error_log("DEBUG: Tabela 'controle_rodada' truncada.");
        
        // Reinicializa controle_rodada, pois TRUNCATE a esvazia.
        // A lógica do script principal já faz um INSERT IGNORE, mas é bom garantir aqui também.
        $stmtControleInsert = $pdo->prepare("INSERT IGNORE INTO controle_rodada (id, rodada_atual) VALUES (1, 1)");
        $stmtControleInsert->execute();
        error_log("DEBUG: Tabela 'controle_rodada' reinicializada com rodada 1.");


        error_log("DEBUG: Reset completo da fila (cantores, musicas_cantor, fila_rodadas, controle_rodada) realizado com sucesso.");
        return true;

    } catch (PDOException $e) {
        // Não há transação para rolar de volta devido ao TRUNCATE
        error_log("Erro ao realizar o reset completo da fila: " . $e->getMessage());
        return false;
    }
}