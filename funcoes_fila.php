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
    
    $lastMesaAdded = null; // ID da última mesa a ter uma música adicionada na fila atual.
    
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

        // Armazena o status de cada mesa para a rodada atual
        $statusMesasNaRodada = []; // ['id_mesa' => ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0]]
        // Armazena IDs dos cantores que já tiveram uma música adicionada nesta rodada para evitar repetições imediatas
        $cantoresQueJaCantaramNestaRodada = [];

        // Obter todos os cantores e suas informações relevantes UMA ÚNICA VEZ DO BANCO DE DADOS
        // Incluir uma contagem de músicas elegíveis para cada cantor para otimização
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
                    WHERE c_inner.id_mesa = m.id AND fr_inner.status = 'cantou') AS ultima_vez_cantou_mesa,
                (SELECT COUNT(*) FROM musicas_cantor mc WHERE mc.id_cantor = c.id AND mc.status IN ('aguardando', 'pulou') AND mc.ordem_na_lista >= c.proximo_ordem_musica) AS musicas_elegiveis_cantor
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

        // Conta quantos cantores realmente têm músicas elegíveis
        $totalCantoresComMusicasElegiveis = 0;
        foreach ($cantoresDisponiveisGlobal as $c) {
            if ($c['musicas_elegiveis_cantor'] > 0) {
                $totalCantoresComMusicasElegiveis++;
            }
        }
        
        // Estima o número máximo de iterações do loop principal.
        // Isso é uma medida de segurança para evitar loops infinitos caso a lógica de prioridade entre em um ciclo.
        // Garante que o loop não execute infinitamente.
        $maxLoopIterations = max($totalCantoresComMusicasElegiveis * 3, count($cantoresDisponiveisGlobal) * 5, 100);
        $currentLoopIteration = 0;
            
        error_log("DEBUG: Iniciando loop de montagem da fila. Max Iterations: " . $maxLoopIterations . ". Cantores globais: " . count($cantoresDisponiveisGlobal) . ". Total cantores com músicas elegíveis: " . $totalCantoresComMusicasElegiveis);

        // Loop principal para montar a fila
        while ($currentLoopIteration < $maxLoopIterations) {
			
			
            $currentLoopIteration++;
            $foundCantorThisIteration = false;

            // Filtra os cantores que ainda não cantaram nesta rodada e têm músicas elegíveis
            $cantoresElegiveisNestaPassagem = array_filter($cantoresDisponiveisGlobal, function($cantor) use ($cantoresQueJaCantaramNestaRodada) {
                return !in_array($cantor['id_cantor'], $cantoresQueJaCantaramNestaRodada) && $cantor['musicas_elegiveis_cantor'] > 0;
            });

            if (empty($cantoresElegiveisNestaPassagem)) {
                error_log("DEBUG: Todos os cantores elegíveis (que não cantaram nesta rodada ou sem músicas) foram processados. Quebrando o loop de montagem da rodada.");
                break;
            }
                
            // Ordena os cantores restantes baseado nas regras de prioridade
            usort($cantoresElegiveisNestaPassagem, function($a, $b) use (&$statusMesasNaRodada, $lastMesaAdded) { // O & aqui é crucial para atualizar $statusMesasNaRodada
                $idMesaA = $a['id_mesa'];
                $idMesaB = $b['id_mesa'];
                
                // Inicializa status se não existir (garantia)
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
                
                $maxMusicasMesaA = 1; // 1 ou 2 na mesa, 1 música
				if ($a['tamanho_mesa'] >= 3 && $a['tamanho_mesa'] <= 4) $maxMusicasMesaA = 2; // 3 ou 4 na mesa, 2 músicas
				elseif ($a['tamanho_mesa'] >= 5) $maxMusicasMesaA = 3; // 5 ou mais na mesa, 3 músicas


                $maxMusicasMesaB = 1; // 1 ou 2 na mesa, 1 música
				if ($b['tamanho_mesa'] >= 3 && $b['tamanho_mesa'] <= 4) $maxMusicasMesaB = 2; // 3 ou 4 na mesa, 2 músicas
				elseif ($b['tamanho_mesa'] >= 5) $maxMusicasMesaB = 3; // 5 ou mais na mesa, 3 músicas

                // 1. Regra principal para intercalação: Desfavorecer a mesa que adicionou a última música.
                // Se a mesa A foi a última adicionada e a mesa B não, priorize B.
                if ($lastMesaAdded !== null) {
                    if ($idMesaA == $lastMesaAdded && $idMesaB != $lastMesaAdded) return 1; // A vem DEPOIS de B
                    if ($idMesaA != $lastMesaAdded && $idMesaB == $lastMesaAdded) return -1; // A vem ANTES de B
                }

                // 2. Prioriza mesas que ainda podem adicionar músicas sobre as que não podem.
                // Isso garante que mesas com capacidade sejam preenchidas antes de considerar as esgotadas.
                $podeAddA = ($musicasA < $maxMusicasMesaA);
                $podeAddB = ($musicasB < $maxMusicasMesaB);

                if ($podeAddA && !$podeAddB) return -1; // A pode adicionar, B não. Prioriza A.
                if (!$podeAddA && $podeAddB) return 1;  // A não pode adicionar, B sim. Prioriza B.

                // Se ambos podem ou ambos não podem, continua para as próximas regras.

                // 3. Prioriza mesas com menos músicas adicionadas NESTA rodada.
                // Isso ajuda a distribuir as músicas entre as mesas.
                if ($musicasA !== $musicasB) return $musicasA - $musicasB;

                // 4. Prioriza mesas que tiveram a última adição há mais tempo.
                // Ajuda a garantir que nenhuma mesa fique "esquecida".
                if ($ultimaAddA !== $ultimaAddB) return $ultimaAddA - $ultimaAddB;
                
                // 5. Prioriza cantores com menos cantos totais.
                if ($a['total_cantos_cantor'] !== $b['total_cantos_cantor']) return $a['total_cantos_cantor'] - $b['total_cantos_cantor'];
                
                // 6. Prioriza mesas que cantaram há mais tempo (histórico geral da mesa).
                // Nulls (nunca cantou) vêm antes dos que já cantaram.
                if ($a['ultima_vez_cantou_mesa'] === null && $b['ultima_vez_cantou_mesa'] !== null) return -1;
                if ($a['ultima_vez_cantou_mesa'] !== null && $b['ultima_vez_cantou_mesa'] === null) return 1;
                if ($a['ultima_vez_cantou_mesa'] !== null && $b['ultima_vez_cantou_mesa'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_mesa']) - strtotime($b['ultima_vez_cantou_mesa']);
                    if ($cmp !== 0) return $cmp;
                }

                // 7. Prioriza cantores que cantaram há mais tempo (histórico general do cantor).
                // Nulls (nunca cantou) vêm antes dos que já cantaram.
                if ($a['ultima_vez_cantou_cantor'] === null && $b['ultima_vez_cantou_cantor'] !== null) return -1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] === null) return 1;
                if ($a['ultima_vez_cantou_cantor'] !== null && $b['ultima_vez_cantou_cantor'] !== null) {
                    $cmp = strtotime($a['ultima_vez_cantou_cantor']) - strtotime($b['ultima_vez_cantou_cantor']);
                    if ($cmp !== 0) return $cmp;
                }
                
                return 0; // Se tudo mais for igual, mantém a ordem relativa (ou um RAND() para desempate final)
            });

            foreach ($cantoresElegiveisNestaPassagem as $cantorParaSelecionar) {
                $idCantor = $cantorParaSelecionar['id_cantor'];
                $idMesa = $cantorParaSelecionar['id_mesa'];
                $tamanhoMesa = $cantorParaSelecionar['tamanho_mesa'];
                $currentProximoOrdemMusica = $cantorParaSelecionar['proximo_ordem_musica'];

                $maxMusicasMesa = 1; // Para mesas de 1 ou 2
				if ($tamanhoMesa >= 3 && $tamanhoMesa <= 4) { // Para mesas de 3 ou 4
					$maxMusicasMesa = 2;
				} elseif ($tamanhoMesa >= 5) { // Para mesas de 5 ou mais
					$maxMusicasMesa = 3;
				}

                if (!isset($statusMesasNaRodada[$idMesa])) {
                    $statusMesasNaRodada[$idMesa] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                }

                // Verifica se a mesa ainda pode ter músicas adicionadas
                if ($statusMesasNaRodada[$idMesa]['musicas_adicionadas'] < $maxMusicasMesa) {
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
                        error_log("INFO: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") não possui mais músicas disponíveis (status 'aguardando' ou 'pulou') em sua lista (proximo_ordem_musica: " . $currentProximoOrdemMusica . "). Pulando-o para esta rodada e marcando como já cantou para esta iteração.");
                        // Marca o cantor como "já processado" para esta rodada para evitar re-seleção desnecessária
                        $cantoresQueJaCantaramNestaRodada[] = $idCantor;
                        // Opcional: Atualizar musicas_elegiveis_cantor para 0 no array para não reprocessá-lo
                        foreach ($cantoresDisponiveisGlobal as $key => $globalCantor) {
                            if ($globalCantor['id_cantor'] === $idCantor) {
                                $cantoresDisponiveisGlobal[$key]['musicas_elegiveis_cantor'] = 0;
                                break;
                            }
                        }
                        continue; // Tenta o próximo cantor na lista ordenada
                    }

                    // Adiciona a música à fila da próxima rodada
                    $filaParaRodada[] = [
                        'id_cantor' => $idCantor,
                        'id_musica' => $musicaId,
                        'musica_cantor_id' => $musicaCantorId,
                        'ordem_na_rodada' => $ordem++,
                        'rodada' => $proximaRodada
                    ];
                        
                    // ATUALIZA o status da música_cantor para 'selecionada_para_rodada'
                    $stmtUpdateMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?");
                    $stmtUpdateMusicaCantorStatus->execute([$musicaCantorId]);
                    error_log("DEBUG: Status da música_cantor_id " . $musicaCantorId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");
                        
                    // Atualiza o controle de músicas adicionadas para a mesa e o timestamp da última adição
                    $statusMesasNaRodada[$idMesa]['musicas_adicionadas']++;
                    $statusMesasNaRodada[$idMesa]['ultima_adicao_timestamp'] = microtime(true);
                    
                    // ATUALIZA $lastMesaAdded
                    $lastMesaAdded = $idMesa; 

                    // Adiciona o cantor à lista de cantores que já cantaram nesta rodada
                    $cantoresQueJaCantaramNestaRodada[] = $idCantor;
                    $foundCantorThisIteration = true;

                    // Atualiza o 'proximo_ordem_musica' do cantor para a próxima música em sua lista
                    $novaProximaOrdem = $ordemMusicaSelecionada + 1;
                    $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
                    $stmtUpdateCantorOrder->execute([$novaProximaOrdem, $idCantor]);
                    error_log("DEBUG: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") próxima ordem atualizada no DB para: " . $novaProximaOrdem);
                        
                    // Atualiza a informação no array global para manter a consistência em memória
                    foreach ($cantoresDisponiveisGlobal as $key => $globalCantor) {
                        if ($globalCantor['id_cantor'] === $idCantor) {
                            $cantoresDisponiveisGlobal[$key]['proximo_ordem_musica'] = $novaProximaOrdem;
                            // Decrementa musicas_elegiveis_cantor para refletir que uma música foi usada
                            $cantoresDisponiveisGlobal[$key]['musicas_elegiveis_cantor']--; 
                            break;
                        }
                    }
                        
                    break; // Sai do foreach e volta para o while principal para reavaliar as prioridades
                } else {
                    error_log("INFO: Cantor " . $cantorParaSelecionar['nome_cantor'] . " (ID: " . $idCantor . ") pulado: Mesa " . $idMesa . " já atingiu o limite de " . $maxMusicasMesa . " músicas nesta rodada.");
                }
            }

            if (!$foundCantorThisIteration) {
                error_log("DEBUG: Nenhuma música foi adicionada nesta iteração do loop principal.");
                // Verifica se ainda há algum cantor com músicas elegíveis E slots de mesa disponíveis
                $canAnyCantorStillSing = false;
                foreach ($cantoresDisponiveisGlobal as $c) {
                    // Ignora cantores que já foram adicionados nesta iteração (para evitar looping desnecessário)
                    if (in_array($c['id_cantor'], $cantoresQueJaCantaramNestaRodada)) {
                        continue;
                    }

                    // Verifica se o cantor tem músicas elegíveis
                    if ($c['musicas_elegiveis_cantor'] > 0) {
                        $mesaId = $c['id_mesa'];
                        $tamanhoMesa = $c['tamanho_mesa'];
                        $maxMusicasMesa = 1;
                        if ($tamanhoMesa >= 3 && $tamanhoMesa < 4) $maxMusicasMesa = 2;
                        elseif ($tamanhoMesa >= 5) $maxMusicasMesa = 3;

                        // Inicializa o status da mesa se não existir (garantia)
                        if (!isset($statusMesasNaRodada[$mesaId])) {
                            $statusMesasNaRodada[$mesaId] = ['musicas_adicionadas' => 0, 'ultima_adicao_timestamp' => 0];
                        }

                        // Verifica se a mesa do cantor ainda tem slot
                        if ($statusMesasNaRodada[$mesaId]['musicas_adicionadas'] < $maxMusicasMesa) {
                            $canAnyCantorStillSing = true;
                            break; // Encontrou um cantor elegível, pode continuar o loop principal
                        }
                    }
                }

                if (!$canAnyCantorStillSing) {
                    error_log("DEBUG: Não há mais cantores elegíveis (com música e slot de mesa) para adicionar. Quebrando o loop.");
                    break; 
                }
            }
                
            // Condição de quebra de segurança adicional, caso o loop esteja adicionando muitas músicas
            // Pode ser ajustado ou removido dependendo do comportamento desejado
            if (count($filaParaRodada) >= $totalCantoresComMusicasElegiveis * 2 + 5 && $totalCantoresComMusicasElegiveis > 0) {
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
        // Manter apenas as entradas da rodada atual e, talvez, da rodada anterior (se necessário para histórico)
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
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'em_execucao', timestamp_ultima_execucao = NOW() WHERE id = ?");
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id']]);
                error_log("DEBUG: Status da primeira música (musica_cantor_id: " . $item['musica_cantor_id'] . ") atualizado para 'em_execucao' na tabela musicas_cantor.");
            } else {
                // Para as demais músicas, garanta que não estão em_execucao, mas sim selecionadas
                // (já foi feito no loop de montagem da fila, mas é bom ter certeza)
                $stmtUpdateMusicasCantor = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ? AND status != 'em_execucao'");
                $stmtUpdateMusicasCantor->execute([$item['musica_cantor_id']]);
            }
            
            // INSIRA O musica_cantor_id NA TABELA FILA_RODADAS
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, status, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, " . $timestamp_inicio_canto . ")");
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", Música " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Status " . $status);
            $stmtInsert->execute([$item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $status]);
        }
        error_log("DEBUG: Itens inseridos na fila_rodadas.");

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

        // 1. Obter informações do item da fila original
        $stmtGetOldMusicInfo = $pdo->prepare("SELECT id_cantor, id_musica, musica_cantor_id FROM fila_rodadas WHERE id = ? AND (status = 'aguardando' OR status = 'em_execucao')");
        $stmtGetOldMusicInfo->execute([$filaId]);
        $filaItem = $stmtGetOldMusicInfo->fetch(PDO::FETCH_ASSOC);

        if (!$filaItem) {
            error_log("Alerta: Tentativa de trocar música em item da fila inexistente ou já finalizado (ID: " . $filaId . ").");
            $pdo->rollBack();
            return false;
        }

        $idCantor = $filaItem['id_cantor'];
        $musicaOriginalId = $filaItem['id_musica'];
        $musicaCantorOriginalId = $filaItem['musica_cantor_id']; // ID da tabela musicas_cantor, se aplicável
        
        // --- Lógica para a MÚSICA ORIGINAL (saindo da fila) ---
        // APENAS se a música original veio de musicas_cantor, tentamos resetar seu status para 'aguardando'
        if ($musicaCantorOriginalId !== null) { 
            $stmtGetOriginalOrder = $pdo->prepare("SELECT ordem_na_lista FROM musicas_cantor WHERE id = ?");
            $stmtGetOriginalOrder->execute([$musicaCantorOriginalId]);
            $ordemMusicaOriginal = $stmtGetOriginalOrder->fetchColumn();

            if ($ordemMusicaOriginal !== false) {
                // Atualizar o proximo_ordem_musica do cantor para a ordem da música original
                $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
                $stmtUpdateCantorOrder->execute([$ordemMusicaOriginal, $idCantor]);
                error_log("DEBUG: Cantor " . $idCantor . " teve proximo_ordem_musica resetado para " . $ordemMusicaOriginal . " após troca de música (fila_id: " . $filaId . ").");
                
                // Atualiza o status da música ORIGINAL na tabela musicas_cantor de volta para 'aguardando'
                $stmtUpdateOriginalMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando' WHERE id = ?");
                $stmtUpdateOriginalMusicaCantorStatus->execute([$musicaCantorOriginalId]);
                error_log("DEBUG: Status da música original (musicas_cantor_id: " . $musicaCantorOriginalId . ") do cantor " . $idCantor . " resetado para 'aguardando' na tabela musicas_cantor.");
                
            } else {
                error_log("Alerta: ID de musica_cantor_id (" . $musicaCantorOriginalId . ") para o item da fila (ID: " . $filaId . ") não encontrado na tabela musicas_cantor. Não foi possível resetar o proximo_ordem_musica ou o status.");
            }
        } else {
            error_log("DEBUG: Música original (ID: " . $musicaOriginalId . ") do item da fila (ID: " . $filaId . ") não possui um musica_cantor_id associado, não há status para resetar em musicas_cantor.");
        }

        // --- Lógica para a NOVA MÚSICA (entrando na fila) ---
        // Antes de atualizar a fila_rodadas, precisamos decidir o musica_cantor_id da nova música.
        $novaMusicaCantorId = null;
        $novaMusicaStatusExistente = null;

        // Verificar se a nova música existe na lista musicas_cantor para este cantor
        $stmtCheckNewMusicInCantorList = $pdo->prepare("SELECT id, status FROM musicas_cantor WHERE id_cantor = ? AND id_musica = ? LIMIT 1");
        $stmtCheckNewMusicInCantorList->execute([$idCantor, $novaMusicaId]);
        $newMusicInCantorList = $stmtCheckNewMusicInCantorList->fetch(PDO::FETCH_ASSOC);

        if ($newMusicInCantorList) {
            $novaMusicaCantorId = $newMusicInCantorList['id'];
            $novaMusicaStatusExistente = $newMusicInCantorList['status'];
            
            // Atualizar o status da NOVA música na tabela musicas_cantor
            // SOMENTE se não for 'cantou' ou 'em_execucao' (se você quiser evitar sobrescrever esses)
            // Ou, para o seu caso, se o status existente for 'aguardando', 'selecionada_para_rodada'
            if ($novaMusicaStatusExistente == 'aguardando') { // ou outros status que podem ser sobrescritos
                 $stmtUpdateNewMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?");
                 $stmtUpdateNewMusicaCantorStatus->execute([$novaMusicaCantorId]);
                 error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ") do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");
            } else {
                 error_log("DEBUG: Status da nova música (musicas_cantor_id: " . $novaMusicaCantorId . ", status: " . $novaMusicaStatusExistente . ") do cantor " . $idCantor . " NÃO foi alterado em musicas_cantor, pois já tinha um status final ou não elegível para mudança.");
            }
        } else {
            error_log("DEBUG: Nova música (ID: " . $novaMusicaId . ") não encontrada na lista musicas_cantor para o cantor " . $idCantor . ". Não há status para atualizar em musicas_cantor.");
            // Se a música não está na lista do cantor, ela não tem um musica_cantor_id para ser atualizado.
            // Aqui você poderia, opcionalmente, inseri-la na musicas_cantor com status 'selecionada_para_rodada'
            // se o comportamento desejado for que qualquer música selecionada para a fila seja adicionada à lista do cantor.
            // Por enquanto, ela só existirá na fila_rodadas.
        }

        // 4. Atualiza o id_musica e musica_cantor_id na tabela fila_rodadas com a nova música
        $stmtUpdateFila = $pdo->prepare("UPDATE fila_rodadas SET id_musica = ?, musica_cantor_id = ? WHERE id = ?");
        $result = $stmtUpdateFila->execute([$novaMusicaId, $novaMusicaCantorId, $filaId]); // Passa o novaMusicaCantorId

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
 * e trunca as tabelas 'controle_rodada' e 'fila_rodadas'.
 * Isso efetivamente reinicia todo o estado da fila do karaokê.
 * @param PDO $pdo Objeto PDO de conexão com o banco de dados.
 * @return bool True se o reset completo foi bem-sucedido, false caso contrário.
 */
function resetarTudoFila(PDO $pdo): bool {
    try {
        // Não usamos transação aqui porque TRUNCATE TABLE faz um COMMIT implícito.
        // Se uma falhar, as anteriores já foram commitadas.
        // Se precisasse ser tudo ou nada, teríamos que usar DELETE FROM e transação.
        // Para um reset, TRUNCATE é mais eficiente.

        // 1. Resetar 'proximo_ordem_musica' dos cantores
        $stmtCantores = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = 1");
        $stmtCantores->execute();
        error_log("DEBUG: Todos os 'proximo_ordem_musica' dos cantores foram resetados para 1.");

        // 2. Resetar 'status' de todas as músicas para 'aguardando' na tabela musicas_cantor
        $stmtMusicasCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'aguardando'");
        $stmtMusicasCantorStatus->execute();
        error_log("DEBUG: Todos os 'status' na tabela musicas_cantor foram resetados para 'aguardando'.");

        // 3. Resetar 'timestamp_ultima_execucao' para NULL na tabela musicas_cantor
        $stmtMusicasCantorTimestamp = $pdo->prepare("UPDATE musicas_cantor SET timestamp_ultima_execucao = NULL");
        $stmtMusicasCantorTimestamp->execute();
        error_log("DEBUG: Todos os 'timestamp_ultima_execucao' na tabela musicas_cantor foram resetados para NULL.");

        // 4. Truncar tabela 'fila_rodadas'
        $stmtFila = $pdo->prepare("TRUNCATE TABLE fila_rodadas");
        $stmtFila->execute();
        error_log("DEBUG: Tabela 'fila_rodadas' truncada.");

        // 5. Truncar tabela 'controle_rodada'
        $stmtControle = $pdo->prepare("TRUNCATE TABLE controle_rodada");
        $stmtControle->execute();
        error_log("DEBUG: Tabela 'controle_rodada' truncada.");
        
        // Reinicializa controle_rodada, pois TRUNCATE a esvazia.
        $stmtControleInsert = $pdo->prepare("INSERT IGNORE INTO controle_rodada (id, rodada_atual) VALUES (1, 1)");
        $stmtControleInsert->execute();
        error_log("DEBUG: Tabela 'controle_rodada' reinicializada com rodada 1.");

        error_log("DEBUG: Reset completo da fila (cantores, musicas_cantor, fila_rodadas, controle_rodada) realizado com sucesso.");
        return true;

    } catch (PDOException $e) {
        error_log("Erro ao realizar o reset completo da fila: " . $e->getMessage());
        return false;
    }
}