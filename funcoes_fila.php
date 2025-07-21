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
    error_log("DEBUG: Início da fun\xc3\xa7\xc3\xa3o montarProximaRodada.");

    // PRIMEIRO: Verificar se a rodada atual está finalizada
    if (!isRodadaAtualFinalizada($pdo)) {
        error_log("INFO: N\xc3\xa3o foi poss\xc3\xadvel montar a pr\xc3\xb3xima rodada. A rodada atual ainda n\xc3\xa3o foi finalizada.");
        return false;
    }

    $rodadaAtual = getRodadaAtual($pdo);
    $proximaRodada = $rodadaAtual + 1;
    error_log("DEBUG: Rodada atual: " . $rodadaAtual . ", Pr\xc3\xb3xima rodada: " . $proximaRodada);

    try {
        $pdo->beginTransaction();
        error_log("DEBUG: Transa\xc3\xa7\xc3\xa3o iniciada.");

        $filaParaRodada = [];
        $ordem = 1;

        // Armazena o status de cada mesa para a rodada atual
        $statusMesasNaRodada = []; // ['id_mesa' => ['musicas_adicionadas' => 0, 'last_picked_timestamp' => 0, 'tamanho_mesa' => X]]
        
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
            error_log("INFO: N\xc3\xa3o h\xc3\xa1 cantores cadastrados para montar a rodada.");
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
                    'musicas_adicionadas' => 0,
                    'last_picked_timestamp' => 0, // Usar 0 para indicar que nunca foi pickada, microtime(true) para picks reais
                    'tamanho_mesa' => $cantor['tamanho_mesa']
                ];
            }
        }
        
        // Estima o número máximo de iterações do loop principal para segurança.
        // Um número razoável é o dobro do número total de cantores para garantir que todas as mesas tenham chance.
        $maxLoopIterations = count($cantoresDisponiveisGlobal) * 2 + count(array_keys($statusMesasNaRodada)) * 3;
        $currentLoopIteration = 0;
        
        error_log("DEBUG: Iniciando loop de montagem da fila. Max Iterations: " . $maxLoopIterations);

        // Loop principal para montar a fila
        // A lógica agora é: enquanto houver cantores com músicas elegíveis, continue adicionando.
        // O limite por mesa e o "uma música por cantor por rodada" são as restrições principais.
        while (true) {
            $currentLoopIteration++;
            if ($currentLoopIteration > $maxLoopIterations) {
                error_log("AVISO: Limite de itera\xc3\xa7\xc3\xb5es do loop principal atingido. Pode haver m\xc3\xbasicas n\xc3\xa3o adicionadas.");
                break;
            }

            $mesaMaisPrioritariaId = null;
            $melhorPrioridadeMesaScore = -PHP_INT_MAX; // Scores altos são melhores
            $eligibleMesasWithScores = [];

            // 1. Encontrar a mesa mais prioritária para adicionar uma música
            // Esta lógica é para garantir que mesas adicionem músicas de forma justa até seu limite.
            foreach ($statusMesasNaRodada as $mesaId => $status) {
                $maxMusicasMesa = 1;
                if ($status['tamanho_mesa'] >= 3 && $status['tamanho_mesa'] <= 4) $maxMusicasMesa = 2;
                elseif ($status['tamanho_mesa'] >= 5) $maxMusicasMesa = 3;

                $podeAddMesa = ($status['musicas_adicionadas'] < $maxMusicasMesa);

                // Verifica se há pelo menos um cantor elegível nesta mesa que ainda não está na fila da rodada
                $hasElegibleCantorInMesa = false;
                foreach ($cantoresPorMesa[$mesaId] ?? [] as $cantor) {
                    if ($cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao)) {
                        $hasElegibleCantorInMesa = true;
                        break;
                    }
                }

                if ($podeAddMesa && $hasElegibleCantorInMesa) {
                    $score = 0;
                    // Prioridade 1: Mesas com menos músicas adicionadas NESTA rodada (quanto menos músicas, maior a prioridade)
                    $score -= ($status['musicas_adicionadas'] * 1000); // Peso maior para garantir que todas as mesas preencham seus slots
                    
                    // Prioridade 2: Mesas que foram "pickadas" há mais tempo NESTA rodada (quanto menor o timestamp, maior a prioridade)
                    // Um timestamp de 0 (inicial) terá um score maior, sendo escolhido primeiro.
                    $score -= $status['last_picked_timestamp']; 
                    
                    $eligibleMesasWithScores[$mesaId] = $score;
                }
            }

            // Se não encontrou nenhuma mesa elegível, quebra o loop
            if (empty($eligibleMesasWithScores)) {
                error_log("DEBUG: Nenhuma mesa possui slots dispon\xc3\xadveis E cantores eleg\xc3\xadveis (que ainda n\xc3\xa3o cantaram nesta rodada). Quebrando o loop de montagem da rodada.");
                break; // Sai do loop principal
            }

            // Seleciona a mesa com o maior score
            // Usamos array_keys e max para pegar a primeira mesa com o score máximo se houver empate.
            $mesaMaisPrioritariaId = array_keys($eligibleMesasWithScores, max($eligibleMesasWithScores))[0];

            $idMesaSelecionada = $mesaMaisPrioritariaId;
            $tamanhoMesaSelecionada = $statusMesasNaRodada[$idMesaSelecionada]['tamanho_mesa'];
            $maxMusicasMesaSelecionada = 1;
            if ($tamanhoMesaSelecionada >= 3 && $tamanhoMesaSelecionada <= 4) $maxMusicasMesaSelecionada = 2;
            elseif ($tamanhoMesaSelecionada >= 5) $maxMusicasMesaSelecionada = 3;

            // 2. Encontrar o cantor mais prioritário dentro da mesa selecionada
            $cantoresDaMesa = $cantoresPorMesa[$idMesaSelecionada] ?? [];
            
            // Filtra e ordena os cantores desta mesa que AINDA NÃO FORAM ADICIONADOS NESTA RODADA
            $cantoresElegiveisParaMesa = array_filter($cantoresDaMesa, function($cantor) use ($cantoresJaNaRodadaEmConstrucao) {
                return $cantor['musicas_elegiveis_cantor'] > 0 && !in_array($cantor['id_cantor'], $cantoresJaNaRodadaEmConstrucao);
            });

            // Se não há cantores elegíveis nesta mesa para esta rodada, continue para a próxima iteração
            if (empty($cantoresElegiveisParaMesa)) {
                error_log("INFO: Mesa " . $idMesaSelecionada . " selecionada, mas nenhum cantor eleg\xc3\xadvel encontrado nesta mesa que ainda n\xc3\xa3o tenha sido selecionado para esta rodada. Marcando mesa como cheia e pulando para a pr\xc3\xb3xima itera\xc3\xa7\xc3\xa3o.");
                // Marca a mesa como "cheia" para esta rodada para não ser mais selecionada e evita loop infinito
                $statusMesasNaRodada[$idMesaSelecionada]['musicas_adicionadas'] = $maxMusicasMesaSelecionada;
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
            
            // Este caso já deveria ser tratado pelo empty($cantoresElegiveisParaMesa) acima, mas é um bom fallback.
            if ($cantorSelecionado === null) {
                error_log("INFO: Ap\xc3\xb3s filtragem e ordena\xc3\xa7\xc3\xa3o, nenhum cantor v\xc3\xa1lido na mesa " . $idMesaSelecionada . ". Isso n\xc3\xa3o deveria acontecer. Pulando.");
                $statusMesasNaRodada[$idMesaSelecionada]['musicas_adicionadas'] = $maxMusicasMesaSelecionada;
                continue;
            }

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
                error_log("INFO: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") n\xc3\xa3o possui mais m\xc3\xbasicas dispon\xc3\xadveis (status 'aguardando' ou 'pulou') em sua lista. Marcando como sem m\xc3\xbasicas eleg\xc3\xadveis e j\xc3\xa1 na rodada (para evitar reprocessamento).");
                // Marca o cantor como "já processado" para esta rodada e sem músicas elegíveis para evitar re-seleção desnecessária
                $cantoresJaNaRodadaEmConstrucao[] = $idCantor; // Marca como "já tentou e não tem música"
                // Atualiza a informação nos arrays de controle
                $cantoresMap[$idCantor]['musicas_elegiveis_cantor'] = 0; // Atualiza no mapa
                // Remove o cantor do $cantoresPorMesa se necessário, ou atualiza a elegibilidade
                foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                    if ($mesaCantor['id_cantor'] === $idCantor) {
                        $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor'] = 0;
                        break;
                    }
                }
                continue; // Tenta a próxima iteração do loop principal para encontrar outra mesa/cantor
            }

            // Adiciona a música à fila da próxima rodada (ainda em memória)
            $filaParaRodada[] = [
                'id_cantor' => $idCantor,
                'id_musica' => $musicaId,
                'musica_cantor_id' => $musicaCantorId,
                'ordem_na_rodada' => $ordem++, // Ordem temporária, será redefinida pela reordenação
                'rodada' => $proximaRodada,
                'id_mesa' => $idMesaSelecionada // Adicionando o id_mesa aqui
            ];

            // ATUALIZA o status da música_cantor para 'selecionada_para_rodada'
            $stmtUpdateMusicaCantorStatus = $pdo->prepare("UPDATE musicas_cantor SET status = 'selecionada_para_rodada' WHERE id = ?");
            $stmtUpdateMusicaCantorStatus->execute([$musicaCantorId]);
            error_log("DEBUG: Status da m\xc3\xbasica_cantor_id " . $musicaCantorId . " do cantor " . $idCantor . " atualizado para 'selecionada_para_rodada' na tabela musicas_cantor.");

            // Atualiza o controle de músicas adicionadas para a mesa e o timestamp da última adição
            $statusMesasNaRodada[$idMesaSelecionada]['musicas_adicionadas']++;
            $statusMesasNaRodada[$idMesaSelecionada]['last_picked_timestamp'] = microtime(true); // Atualiza o timestamp de quando a mesa foi "pickada"
            
            // Adiciona o cantor à lista de cantores que JÁ ESTÃO na fila em construção para esta rodada.
            // Isso evita que o mesmo cantor cante duas vezes na mesma rodada.
            $cantoresJaNaRodadaEmConstrucao[] = $idCantor;

            // Atualiza o 'proximo_ordem_musica' do cantor para a próxima música em sua lista no DB
            $novaProximaOrdem = $ordemMusicaSelecionada + 1;
            $stmtUpdateCantorOrder = $pdo->prepare("UPDATE cantores SET proximo_ordem_musica = ? WHERE id = ?");
            $stmtUpdateCantorOrder->execute([$novaProximaOrdem, $idCantor]);
            error_log("DEBUG: Cantor " . $cantorSelecionado['nome_cantor'] . " (ID: " . $idCantor . ") pr\xc3\xb3xima ordem atualizada no DB para: " . $novaProximaOrdem);
            
            // Atualiza a informação nos arrays de controle em memória
            $cantoresMap[$idCantor]['proximo_ordem_musica'] = $novaProximaOrdem;
            $cantoresMap[$idCantor]['musicas_elegiveis_cantor']--;
            // Também atualiza o cantor específico no array $cantoresPorMesa
            foreach ($cantoresPorMesa[$idMesaSelecionada] as $key => $mesaCantor) {
                if ($mesaCantor['id_cantor'] === $idCantor) {
                    $cantoresPorMesa[$idMesaSelecionada][$key]['proximo_ordem_musica'] = $novaProximaOrdem;
                    $cantoresPorMesa[$idMesaSelecionada][$key]['musicas_elegiveis_cantor']--;
                    break;
                }
            }
            
            // Verifica a condição de parada: se nenhuma música pode mais ser adicionada
            // Verifica se há alguma mesa que ainda pode adicionar músicas E tem cantores elegíveis (não na rodada)
            $canAddMoreSongsToAnyMesa = false;
            foreach ($statusMesasNaRodada as $mesaId => $status) {
                $maxMusicas = 1;
                if ($status['tamanho_mesa'] >= 3 && $status['tamanho_mesa'] <= 4) $maxMusicas = 2;
                elseif ($status['tamanho_mesa'] >= 5) $maxMusicas = 3;

                if ($status['musicas_adicionadas'] < $maxMusicas) {
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
                error_log("DEBUG: Nenhuma mesa tem mais slots ou cantores eleg\xc3\xadveis que ainda n\xc3\xa3o cantaram nesta rodada. Quebrando o loop de montagem.");
                break;
            }

        } // Fim do while de montagem da fila
        error_log("DEBUG: Fim do loop de montagem da fila. Itens na filaParaRodada: " . count($filaParaRodada));

        if (empty($filaParaRodada)) {
            $pdo->rollBack();
            error_log("DEBUG: filaParaRodada est\xc3\xa1 vazia ap\xc3\xb3s o loop. Rollback e retorno false. Pode ser que n\xc3\xa3o haja cantores com m\xc3\xbasicas dispon\xc3\xadveis para cantar ou que j\xc3\xa1 atingiram o limite.");
            return false;
        }

        // --- Limpa a fila antiga antes de inserir a nova rodada ---
        // Apenas limpa músicas de rodadas anteriores que ainda estão na fila (ex: se o sistema parou de forma anormal)
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
                error_log("DEBUG: Status da primeira m\xc3\xbasica (musica_cantor_id: " . $item['musica_cantor_id'] . ") atualizado para 'em_execucao' na tabela musicas_cantor.");
            }
            
            // INSIRA O musica_cantor_id E id_mesa NA TABELA FILA_RODADAS
            $stmtInsert = $pdo->prepare("INSERT INTO fila_rodadas (id_cantor, id_musica, musica_cantor_id, ordem_na_rodada, rodada, id_mesa, status, timestamp_adicao, timestamp_inicio_canto) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), " . $timestamp_inicio_canto . ")");
            error_log("DEBUG: Inserindo na fila_rodadas: Cantor " . $item['id_cantor'] . ", M\xc3\xbasica " . $item['id_musica'] . ", MC ID " . $item['musica_cantor_id'] . ", Ordem TEMP " . $item['ordem_na_rodada'] . ", Rodada " . $item['rodada'] . ", Mesa " . $item['id_mesa'] . ", Status " . $status);
            $stmtInsert->execute([$item['id_cantor'], $item['id_musica'], $item['musica_cantor_id'], $item['ordem_na_rodada'], $item['rodada'], $item['id_mesa'], $status]);
        }
        error_log("DEBUG: Itens temporariamente inseridos na fila_rodadas.");

        // CHAMADA CORRETA DA FUNÇÃO DE REORDENAÇÃO AQUI
        error_log("DEBUG: Chamando reordenarFilaParaIntercalarMesas para a rodada: " . $proximaRodada);
        if (!reordenarFilaParaIntercalarMesas($pdo, $proximaRodada)) {
            // Se a reordenação falhar, faz rollback e retorna false.
            $pdo->rollBack();
            error_log("ERRO: Falha ao reordenar a fila da Rodada " . $proximaRodada . ". Rollback da transa\xc3\xa7\xc3\xa3o de montagem.");
            return false;
        }
        error_log("DEBUG: reordenarFilaParaIntercalarMesas conclu\xc3\xadda.");

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
        error_log("DEBUG: Transa\xc3\xa7\xc3\xa3o commitada. Retornando true.");
        return true;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("DEBUG: Transa\xc3\xa7\xc3\xa3o rollback devido a erro (PDOException): " . $e->getMessage());
        }
        error_log("ERRO: Erro ao montar pr\xc3\xb3xima rodada (PDOException): " . $e->getMessage());
        return false;
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("DEBUG: Transa\xc3\xa7\xc3\xa3o rollback devido a erro (Exception): " . $e->getMessage());
        }
        error_log("ERRO: Erro ao montar pr\xc3\xb3xima rodada (Exception): " . $e->getMessage());
        return false;
    }
}

/**
 * Reordena a fila de uma rodada específica para evitar que músicas da mesma mesa
 * sejam executadas consecutivamente, mantendo a ordem das músicas já cantadas/em execução.
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

        // Adiciona os itens fixos primeiro e define a última mesa adicionada
        if (!empty($fixedItems)) {
            $novaOrdemFila = $fixedItems;
            $lastFixedItem = end($fixedItems);
            $lastMesaAdded = $lastFixedItem['id_mesa'];
        }

        // --- Lógica de intercalação mais robusta ---
        $remainingPendingCount = count($pendingItems);
        $maxIterations = $remainingPendingCount * count($pendingItemsByMesa) * 2; // Limite de segurança

        error_log("DEBUG: Iniciando loop de intercalação. Total pendente: " . $remainingPendingCount);

        while ($remainingPendingCount > 0 && $maxIterations-- > 0) {
            $itemAddedInThisIteration = false;
            $selectedMesaId = null;

            // Obter as mesas que ainda têm músicas
            $mesasComMusicas = array_keys(array_filter($pendingItemsByMesa, 'count'));
            sort($mesasComMusicas); // Garante ordem consistente

            if (empty($mesasComMusicas)) {
                error_log("INFO: Nenhuma mesa com músicas restantes, mas remainingPendingCount > 0. Algo errado.");
                break; // Sai do loop se não houver mesas com músicas
            }

            // Tenta encontrar uma mesa diferente da última
            if ($lastMesaAdded !== null) {
                foreach ($mesasComMusicas as $mesaId) {
                    if ($mesaId !== $lastMesaAdded) {
                        $selectedMesaId = $mesaId;
                        break;
                    }
                }
            }

            // Se não encontrou uma mesa diferente OU se for a primeira música a ser adicionada
            if ($selectedMesaId === null) {
                // Se só sobrou a mesa que foi a última adicionada, ou se todas as outras mesas estão vazias
                // ou se é a primeira vez que entra aqui e $lastMesaAdded é null,
                // apenas pega a primeira mesa disponível na lista (garantindo que seja do array $mesasComMusicas)
                if (in_array($lastMesaAdded, $mesasComMusicas) && count($mesasComMusicas) === 1 && $mesasComMusicas[0] === $lastMesaAdded) {
                    $selectedMesaId = $lastMesaAdded; // Pega a única mesa restante, mesmo que repita
                    error_log("DEBUG: Fallback: Apenas a mesa " . $selectedMesaId . " tem músicas restantes. Será repetida.");
                } else {
                    // Tenta encontrar a próxima mesa no ciclo que tenha músicas
                    // Percorre as mesas a partir da "próxima" que seria usada se o lastMesaAdded não importasse
                    $startIndex = ($lastMesaAdded !== null) ? array_search($lastMesaAdded, $mesasComMusicas) : 0;
                    if ($startIndex === false) $startIndex = 0; // Se lastMesaAdded não está mais na lista (esgotada), começa do 0

                    $attemptCount = 0;
                    do {
                        $currentCheckIndex = ($startIndex + $attemptCount) % count($mesasComMusicas);
                        $mesaCandidateId = $mesasComMusicas[$currentCheckIndex];

                        if (!empty($pendingItemsByMesa[$mesaCandidateId])) {
                            $selectedMesaId = $mesaCandidateId;
                            error_log("DEBUG: Escolhendo a mesa " . $selectedMesaId . " (próxima disponível no ciclo, possivelmente repetida).");
                            break;
                        }
                        $attemptCount++;
                    } while ($attemptCount < count($mesasComMusicas));

                    // Se por algum motivo, após tentar todo o ciclo, ainda não achou, pega a primeira disponível
                    if ($selectedMesaId === null && !empty($mesasComMusicas)) {
                         $selectedMesaId = $mesasComMusicas[0];
                         error_log("DEBUG: Fallback Final: Escolhendo a primeira mesa disponível " . $selectedMesaId . ".");
                    }
                }
            }

            // Se uma mesa foi selecionada, adicione sua música
            if ($selectedMesaId !== null && !empty($pendingItemsByMesa[$selectedMesaId])) {
                $item = array_shift($pendingItemsByMesa[$selectedMesaId]);
                $novaOrdemFila[] = $item;
                $lastMesaAdded = $item['id_mesa'];
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

        // 3. Reatribuir as ordens e atualizar o banco de dados
        $stmtUpdate = $pdo->prepare("UPDATE fila_rodadas SET ordem_na_rodada = :nova_ordem WHERE id = :id");

        $currentOrder = 1;
        foreach ($novaOrdemFila as $item) {
            // Apenas atualiza o banco de dados se a ordem_na_rodada realmente mudou
            // Ou se o item está em uma posição que precisa ser re-setada
            if ((int)$item['ordem_na_rodada'] !== $currentOrder || $item['status'] === 'selecionada_para_rodada') {
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