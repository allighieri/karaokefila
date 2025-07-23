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
    error_log("DEBUG: Início da função montarProximaRodada.");
    error_log("DEBUG: Modo da fila recebido: " . $modoFila);

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

        // --- NOVO: Carregar regras de configuração de mesa ---
        $regrasConfiguracaoMesa = [];
        $stmtRegras = $pdo->query("SELECT min_pessoas, max_pessoas, max_musicas_por_rodada FROM configuracao_regras_mesa ORDER BY min_pessoas ASC");
        while ($row = $stmtRegras->fetch(PDO::FETCH_ASSOC)) {
            $regrasConfiguracaoMesa[] = $row;
        }
        if (empty($regrasConfiguracaoMesa)) {
            error_log("ERRO: Nenhuma regra de configuração de mesa encontrada na tabela 'configuracao_regras_mesa'. Por favor, configure as regras.");
            $pdo->rollBack();
            return false;
        }
        error_log("DEBUG: Regras de configuração de mesa carregadas: " . json_encode($regrasConfiguracaoMesa));
        // --- FIM NOVO ---

        $filaParaRodada = [];
        $ordem = 1;

        // Armazena o status de cada mesa para a rodada atual
        $statusMesasNaRodada = []; // ['id_mesa' => ['musicas_adicionadas_nesta_rodada' => 0, 'last_picked_timestamp' => 0, 'tamanho_mesa' => X]]

        // Armazena IDs dos cantores que JÁ TÊM uma música na fila em construção para esta rodada.
        // Isso garante que um cantor só cante uma vez por rodada.
        $cantoresJaNaRodadaEmConstrucao = [];

        // Obter todos os cantores e suas informações relevantes UMA ÚNICA VEZ DO BANCO DE DADOS
        // Agora usando uma subquery no FROM para calcular os campos antes de ordenar
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
                    (SELECT COUNT(fr.id) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou') AS total_cantos_cantor,
                    (SELECT MAX(fr.timestamp_fim_canto) FROM fila_rodadas fr WHERE fr.id_cantor = c.id AND fr.status = 'cantou') AS ultima_vez_cantou_cantor,
                    (SELECT COUNT(*) FROM musicas_cantor mc WHERE mc.id_cantor = c.id AND mc.status IN ('aguardando', 'pulou') AND mc.ordem_na_lista >= c.proximo_ordem_musica) AS musicas_elegiveis_cantor
                FROM cantores c
                JOIN mesas m ON c.id_mesa = m.id
            ) AS t
            ORDER BY
                t.total_cantos_cantor ASC,
                CASE WHEN t.ultima_vez_cantou_cantor IS NULL THEN 0 ELSE 1 END, -- Prioriza quem nunca cantou
                t.ultima_vez_cantou_cantor ASC, -- Quem cantou há mais tempo
                t.id_cantor ASC -- Desempate final por ID do cantor
        ";
        $stmtTodosCantores = $pdo->query($sqlTodosCantoresInfo);
        $cantoresDisponiveisGlobal = $stmtTodosCantores->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cantoresDisponiveisGlobal)) {
            $pdo->rollBack();
            error_log("INFO: Não há cantores cadastrados para montar a rodada.");
            return false;
        }

        // Pré-popular statusMesasNaRodada e cantores por mesa
        $cantoresPorMesa = [];
        // Mapeia cantores por ID para fácil acesso
        $cantoresMap = [];
        foreach ($cantoresDisponiveisGlobal as $cantor) {
            $mesaId = $cantor['id_mesa'];
            if (!isset($cantoresPorMesa[$mesaId])) {
                $cantoresPorMesa[$mesaId] = [];
            }
            $cantoresPorMesa[$mesaId][] = $cantor;
            $cantoresMap[$cantor['id_cantor']] = $cantor;

            // Inicializa o status da mesa se ainda não existir
            if (!isset($statusMesasNaRodada[$mesaId])) {
                $statusMesasNaRodada[$mesaId] = [
                    'musicas_adicionadas_nesta_rodada' => 0,
                    'last_picked_timestamp' => 0, // Usar 0 para indicar que nunca foi pickada, microtime(true) para picks reais
                    'tamanho_mesa' => $cantor['tamanho_mesa']
                ];
            }
        }

        // Estima o número máximo de iterações do loop principal para segurança.
        // Ajustado para o caso de "cantor" ser mais intensivo.
        $maxLoopIterations = count($cantoresDisponiveisGlobal) * 3 + count(array_keys($statusMesasNaRodada)) * 3;
        $currentLoopIteration = 0;

        error_log("DEBUG: Iniciando loop de montagem da fila. Max Iterations: " . $maxLoopIterations);

        // --- LÓGICA PRINCIPAL DE MONTAGEM DA FILA ---
        while (true) {
            $currentLoopIteration++;
            if ($currentLoopIteration > $maxLoopIterations) {
                error_log("AVISO: Limite de iterações do loop principal atingido. Pode haver músicas não adicionadas. Considere aumentar maxLoopIterations.");
                break;
            }

            $mesaMaisPrioritariaId = null;
            $eligibleMesasWithScores = [];

            // 1. Encontrar a mesa mais prioritária para adicionar uma música
            foreach ($statusMesasNaRodada as $mesaId => $status) {
                // Determine o limite de músicas por mesa com base no modo E NAS NOVAS REGRAS DINÂMICAS
                $podeAddMesa = false;
                $maxMusicasMesa = 0; // Valor padrão, será sobrescrito pelas regras

                if ($modoFila === "mesa") {
                    // --- NOVO: Lógica para determinar maxMusicasMesa dinamicamente ---
                    foreach ($regrasConfiguracaoMesa as $regra) {
                        if ($status['tamanho_mesa'] >= $regra['min_pessoas'] &&
                            ($regra['max_pessoas'] === null || $status['tamanho_mesa'] <= $regra['max_pessoas'])) {
                            $maxMusicasMesa = $regra['max_musicas_por_rodada'];
                            break; // Encontrou a regra aplicável, sai do loop
                        }
                    }
                    if ($maxMusicasMesa > 0) { // Se uma regra foi encontrada
                        $podeAddMesa = ($status['musicas_adicionadas_nesta_rodada'] < $maxMusicasMesa);
                    } else {
                        error_log("AVISO: Nenhuma regra de configuração de mesa encontrada para o tamanho da mesa " . $status['tamanho_mesa'] . ". Mesa ID: " . $mesaId);
                        continue; // Pula esta mesa se não houver regra definida
                    }
                    // --- FIM NOVO ---
                } elseif ($modoFila === "cantor") {
                    // No modo "cantor", o limite por mesa é essencialmente a quantidade de cantores elegíveis daquela mesa
                    // que ainda não cantaram nesta rodada. Se houver pelo menos um, a mesa é elegível.
                    $podeAddMesa = true; // Assume que pode adicionar a menos que não haja cantores elegíveis
                }

                // Verifica se há pelo menos um cantor elegível nesta mesa que ainda não está na fila em construção para esta rodada.
                $hasElegibleCantorInMesa = false;
                foreach ($cantoresPorMesa[$mesaId] ?? [] as $cantor) {
                    if ($cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao)) {
                        $hasElegibleCantorInMesa = true;
                        break;
                    }
                }

                // A mesa só é elegível se puder adicionar músicas (pelo modo) E tiver cantores elegíveis
                if ($podeAddMesa && $hasElegibleCantorInMesa) {
                    $score = 0;
                    // Prioridade: Mesas que foram "pickadas" há mais tempo NESTA rodada.
                    // Quanto menor o timestamp (mais antigo), maior a prioridade (score maior).
                    $score -= $status['last_picked_timestamp']; // Subtrair para que timestamps menores resultem em scores maiores (menos negativo)

                    // NO MODO MESA, ADICIONA PRIORIDADE POR MENOS MÚSICAS ADICIONADAS
                    if ($modoFila === "mesa") {
                        // Prioridade adicional para mesas com menos músicas adicionadas NESTA rodada (quanto menos músicas, maior a prioridade)
                        $score -= ($status['musicas_adicionadas_nesta_rodada'] * 1000); // Peso maior para garantir que todas as mesas preencham seus slots
                    }
                    // No modo "cantor", esta prioridade é menos relevante, pois queremos esvaziar a mesa de cantores um por um.

                    $eligibleMesasWithScores[$mesaId] = $score;
                }
            }

            // Se não encontrou nenhuma mesa elegível, quebra o loop
            if (empty($eligibleMesasWithScores)) {
                error_log("DEBUG: Nenhuma mesa possui slots disponíveis (modo mesa) ou cantores elegíveis (modo cantor) que ainda não cantaram nesta rodada. Quebrando o loop de montagem da rodada.");
                break; // Sai do loop principal
            }

            // Seleciona a mesa com o maior score
            $mesaMaisPrioritariaId = array_keys($eligibleMesasWithScores, max($eligibleMesasWithScores))[0];
            $idMesaSelecionada = $mesaMaisPrioritariaId;
            error_log("DEBUG: Mesa selecionada para adicionar música: " . $idMesaSelecionada . " (Score: " . $eligibleMesasWithScores[$idMesaSelecionada] . ")");

            // 2. Encontrar o cantor mais prioritário dentro da mesa selecionada
            $cantoresDaMesa = $cantoresPorMesa[$idMesaSelecionada] ?? [];

            // Filtra e ordena os cantores desta mesa que AINDA NÃO FORAM ADICIONADOS NESTA RODADA E TÊM MÚSICAS ELEGÍVEIS
            $cantoresElegiveisParaMesa = array_filter($cantoresDaMesa, function($cantor) use ($cantoresJaNaRodadaEmConstrucao) {
                return $cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao);
            });

            if (empty($cantoresElegiveisParaMesa)) {
                error_log("INFO: Mesa " . $idMesaSelecionada . " selecionada, mas nenhum cantor elegível encontrado nesta mesa que ainda não tenha sido selecionado para esta rodada. Pulando para a próxima iteração.");
                // Marca a mesa como "esgotada" para esta rodada (temporariamente) para não ser selecionada novamente imediatamente
                $statusMesasNaRodada[$idMesaSelecionada]['last_picked_timestamp'] = microtime(true);
                continue;
            }

            // Ordenar os cantores restantes da mesa selecionada baseado nas regras de prioridade do cantor
            // Quem cantou menos vezes no geral, e quem cantou há mais tempo.
            usort($cantoresElegiveisParaMesa, function($a, $b) {
                // Cantores com menos cantos totais (Prioridade 1)
                if ($a['total_cantos_cantor'] !== $b['total_cantos_cantor']) {
                    return $a['total_cantos_cantor'] - $b['total_cantos_cantor'];
                }

                // Cantores que cantaram há mais tempo (histórico geral do cantor) (Prioridade 2)
                // Nulls (nunca cantou) vêm antes dos que já cantaram.
                if ($a['ultima_vez_cantou_cantor'] === null && $b['ultima_vez_cantou_cantor'] !== null) return -1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] === null) return 1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_cantor']) - strtotime($b['ultima_vez_cantou_cantor']);
                    if ($cmp !== 0) return $cmp;
                }

                // Desempate final: ordem original (implícita no fetch) ou por ID do cantor para estabilidade
                return $a['id_cantor'] - $b['id_cantor'];
            });

            $cantorSelecionado = array_shift($cantoresElegiveisParaMesa); // Pega o cantor mais prioritário
            error_log("DEBUG: Cantor selecionado na mesa " . $idMesaSelecionada . ": " . $cantorSelecionado['nome_cantor'] . " (ID: " . $cantorSelecionado['id_cantor'] . ")");

            $idCantor = $cantorSelecionado['id_cantor'];
            $currentProximoOrdemMusica = $cantorSelecionado['proximo_ordem_musica'];

            // Busca a próxima música elegível para o cantor
            $sqlProximaMusicaCantor = "
                SELECT
                    mc.id AS musica_cantor_id,
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
            $musicaCantorId = $musicaData ? $musicaData['musica_cantor_id'] : null;
            $ordemMusicaSelecionada = $musicaData ? $musicaData['ordem_na_lista'] : null;

            if (!$musicaId || !$musicaCantorId) {
                error_log("INFO: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") não possui mais músicas disponíveis (status 'aguardando' ou 'pulou') em sua lista na ordem esperada. Isso não deveria acontecer se musicas_elegiveis_cantor > 0.");
                // Força o contador para 0 e marca o cantor como "já na rodada" para evitar re-seleção desnecessária
                $cantoresMap[$idCantor]['musicas_elegiveis_cantor'] = 0;
                foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                    if ($mesaCantor['id_cantor'] === $idCantor) {
                        $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor'] = 0;
                        break;
                    }
                }
                $cantoresJaNaRodadaEmConstrucao[] = $idCantor; // Marca como processado
                continue; // Tenta a próxima iteração do loop principal para encontrar outra mesa/cantor
            }

            // Adiciona a música à fila da próxima rodada (ainda em memória)
            $filaParaRodada[] = [
                'id_cantor' => $idCantor,
                'id_musica' => $musicaId,
                'musica_cantor_id' => $musicaCantorId,
                'ordem_na_rodada' => $ordem++, // Ordem temporária, será redefinida pela reordenação
                'rodada' => $proximaRodada,
                'id_mesa' => $idMesaSelecionada
            ];

            // ATUALIZA o status da música_cantor para 'selecionada_para_rodada'
            $stmtUpdateMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?");
            $stmtUpdateMusicaCantorStatus->execute([$musicaCantorId]);
            error_log("DEBUG: Status da música_cantor_id " . $musicaCantorId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");

            // Atualiza o controle de músicas adicionadas para a mesa E o timestamp da última adição
            $statusMesasNaRodada[$idMesaSelecionada]['musicas_adicionadas_nesta_rodada']++;
            $statusMesasNaRodada[$idMesaSelecionada]['last_picked_timestamp'] = microtime(true);

            // Adiciona o cantor à lista de cantores que JÁ ESTÃO na fila em construção para esta rodada.
            // Isso evita que o mesmo cantor cante duas vezes na mesma rodada.
            $cantoresJaNaRodadaEmConstrucao[] = $idCantor;

            // Atualiza o 'proximo_ordem_musica' do cantor para a próxima música em sua lista no DB
            $novaProximaOrdem = $ordemMusicaSelecionada + 1;
            $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
            $stmtUpdateCantorOrder->execute([$novaProximaOrdem, $idCantor]);
            error_log("DEBUG: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") próxima ordem atualizada no DB para: " . $novaProximaOrdem);

            // Atualiza a informação nos arrays de controle em memória
            $cantoresMap[$idCantor]['proximo_ordem_musica'] = $novaProximaOrdem;
            $cantoresMap[$idCantor]['musicas_elegiveis_cantor']--;
            // Também atualiza o cantor específico no array $cantoresPorMesa
            foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                if ($mesaCantor['id_cantor'] === $idCantor) {
                    $cantoresPorMesa[$idMesaSelecionada][$key]['proxim o_ordem_musica'] = $novaProximaOrdem;
                    $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor']--;
                    break;
                }
            }

            // Verifica a condição de parada: se nenhuma música pode mais ser adicionada em NENHUM DOS MODOS.
            $canAddMoreSongsToAnyMesa = false;
            foreach ($statusMesasNaRodada as $mesaId => $status) {
                $mesaPodeAddPeloModo = false;
                $maxMusicasMesaCheck = 0; // Para a verificação da condição de parada

                if ($modoFila === "mesa") {
                    // --- NOVO: Lógica para determinar maxMusicasMesaCheck dinamicamente ---
                    foreach ($regrasConfiguracaoMesa as $regra) {
                        if ($status['tamanho_mesa'] >= $regra['min_pessoas'] &&
                            ($regra['max_pessoas'] === null || $status['tamanho_mesa'] <= $regra['max_pessoas'])) {
                            $maxMusicasMesaCheck = $regra['max_musicas_por_rodada'];
                            break;
                        }
                    }
                    if ($maxMusicasMesaCheck > 0) { // Se uma regra foi encontrada
                        $mesaPodeAddPeloModo = ($status['musicas_adicionadas_nesta_rodada'] < $maxMusicasMesaCheck);
                    }
                    // --- FIM NOVO ---
                } elseif ($modoFila === "cantor") {
                    // No modo cantor, a mesa pode adicionar se houver qualquer cantor elegível nela.
                    $mesaPodeAddPeloModo = true;
                }

                if ($mesaPodeAddPeloModo) {
                    // Verifica se há cantores elegíveis nesta mesa que AINDA NÃO FORAM SELECIONADOS PARA ESTA RODADA
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

        } // Fim do while de montagem da fila
        error_log("DEBUG: Fim do loop de montagem da fila. Itens na filaParaRodada: " . count($filaParaRodada));

        if (empty($filaParaRodada)) {
            $pdo->rollBack();
            error_log("DEBUG: filaParaRodada está vazia após o loop. Rollback e retorno false. Pode ser que não haja cantores com músicas disponíveis para cantar ou que já atingiram o limite.");
            return false;
        }

        // --- Limpa a fila antiga antes de inserir a nova rodada ---
        $stmtDeleteOldQueue = $pdo->prepare("DELETE FROM fila_rodadas WHERE rodada < ? AND status = 'aguardando'");
        $stmtDeleteOldQueue->execute([$proximaRodada]);
        error_log("DEBUG: Fila_rodadas antigas (status 'aguardando') limpas para rodadas anteriores a " . $proximaRodada);

        // Inserir todas as músicas geradas na tabela fila_rodadas
        $firstItem = true;
        foreach ($filaParaRodada as $item) {
            $status = 'aguardando';
            $timestamp_inicio_canto = 'NULL';

            // Marca a primeira música da rodada como 'em_execucao'
            if ($firstItem) {
                $status = 'em_execucao';
                $timestamp_inicio_canto = 'NOW()';
                $firstItem = false;

                // Também atualiza o status na tabela musicas_cantor para 'em_execucao'
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ?");
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id']]);
                error_log("DEBUG: Status da primeira música (musica_cantor_id: " . $item['musica_cantor_id'] . ") atualizado para 'em_execucao' na tabela musicas_cantor.");
            }

            // INSIRA O musica_cantor_id E id_mesa NA TABELA FILA_RODADAS
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, id_mesa, status, timestamp_adicao, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), " . $timestamp_inicio_canto . ")");
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", Música " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem TEMP " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Mesa " . $item['id_mesa'] . ", Status " . $status);
            $stmtInsert->execute([$item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $item['id_mesa'], $status]);
        }
        error_log("DEBUG: Itens temporariamente inseridos na fila_rodadas.");

        // CHAMADA CORRETA DA FUNÇÃO DE REORDENAÇÃO AQUI
        error_log("DEBUG: Chamando reordenarFilaParaIntercalarMesas para a rodada: " . $proximaRodada);
        if (!reordenarFilaParaIntercalarMesas($pdo, $proximaRodada)) {
            // Se a reordenação falhar, faz rollback e retorna false.
            $pdo->rollBack();
            error_log("ERRO: Falha ao reordenar a fila da Rodada " . $proximaRodada . ". Rollback da transação de montagem.");
            return false;
        }
        error_log("DEBUG: reordenarFilaParaIntercalarMesas concluída.");

        // Atualiza o controle de rodada
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