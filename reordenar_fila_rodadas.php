<?php
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
                c.nome_cantor,
                mu.titulo AS nome_musica,
                mu.artista AS nome_artista
            FROM fila_rodadas fr
            JOIN cantores c ON fr.id_cantor = c.id
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