<?php
require_once 'conn.php';
require_once 'funcoes_fila.php';

// Define constantes necessárias
define('ID_TENANTS', 1);
define('ID_EVENTO_ATIVO', 1);

// Simula login como administrador
$_SESSION['id_usuario'] = 1;
$_SESSION['nivel_acesso'] = 'admin';

echo "=== TESTE DE ÍNDICES SEQUENCIAIS ===\n\n";

// Busca um cantor
$stmtCantor = $pdo->prepare("SELECT c.id, u.nome as nome_cantor FROM cantores c JOIN usuarios u ON c.id_usuario = u.id WHERE c.id_tenants = ? LIMIT 1");
$stmtCantor->execute([ID_TENANTS]);
$cantor = $stmtCantor->fetch(PDO::FETCH_ASSOC);

if (!$cantor) {
    echo "Nenhum cantor encontrado.\n";
    exit;
}

echo "Cantor selecionado: {$cantor['nome_cantor']} (ID: {$cantor['id']})\n\n";

// Adiciona mais músicas na lista do cantor se necessário para ter pelo menos 5
$stmtCountMusicas = $pdo->prepare("SELECT COUNT(*) FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ?");
$stmtCountMusicas->execute([$cantor['id'], ID_EVENTO_ATIVO]);
$totalMusicas = $stmtCountMusicas->fetchColumn();

if ($totalMusicas < 5) {
    echo "Adicionando mais músicas para ter pelo menos 5...\n";
    $stmtMusicas = $pdo->prepare("SELECT id, titulo, artista FROM musicas WHERE id NOT IN (SELECT id_musica FROM musicas_cantor WHERE id_cantor = ? AND id_eventos = ?) LIMIT ?");
    $stmtMusicas->execute([$cantor['id'], ID_EVENTO_ATIVO, 5 - $totalMusicas]);
    $musicas = $stmtMusicas->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($musicas as $musica) {
        $totalMusicas++;
        $stmtAdd = $pdo->prepare("INSERT INTO musicas_cantor (id_eventos, id_cantor, id_musica, ordem_na_lista, status) VALUES (?, ?, ?, ?, 'aguardando')");
        $stmtAdd->execute([ID_EVENTO_ATIVO, $cantor['id'], $musica['id'], $totalMusicas]);
        echo "  {$totalMusicas}. {$musica['titulo']} - {$musica['artista']}\n";
    }
}

echo "Total de músicas na lista: $totalMusicas\n";

// Função para mostrar a fila e verificar sequência
function mostrarFilaEVerificarSequencia($pdo, $idCantor) {
    $stmt = $pdo->prepare("
        SELECT mc.id, mc.ordem_na_lista, m.titulo, m.artista, mc.status 
        FROM musicas_cantor mc 
        JOIN musicas m ON mc.id_musica = m.id 
        WHERE mc.id_cantor = ? AND mc.id_eventos = ? 
        ORDER BY mc.ordem_na_lista ASC
    ");
    $stmt->execute([$idCantor, ID_EVENTO_ATIVO]);
    $musicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Fila atual do cantor:\n";
    $sequenciaCorreta = true;
    $ordemEsperada = 1;
    
    foreach ($musicas as $musica) {
        $statusIcon = $musica['status'] == 'selecionada_para_rodada' ? '🎤' : '⏳';
        echo "  {$musica['ordem_na_lista']}. {$musica['titulo']} - {$musica['artista']} {$statusIcon}\n";
        
        if ($musica['ordem_na_lista'] != $ordemEsperada) {
            $sequenciaCorreta = false;
        }
        $ordemEsperada++;
    }
    
    if ($sequenciaCorreta) {
        echo "✅ Sequência de índices está correta (1, 2, 3, 4, 5, 6)\n";
    } else {
        echo "❌ Sequência de índices está incorreta!\n";
    }
    
    echo "\n";
    return $musicas;
}

echo "\nANTES DA TROCA:\n";
$musicasAntes = mostrarFilaEVerificarSequencia($pdo, $cantor['id']);

// Seleciona a música da posição 5 para mover para primeira
$musicaPosicao5 = null;
foreach ($musicasAntes as $musica) {
    if ($musica['ordem_na_lista'] == 5) {
        $musicaPosicao5 = $musica;
        break;
    }
}

if (!$musicaPosicao5) {
    echo "Música na posição 5 não encontrada.\n";
    exit;
}

echo "Movendo música da posição 5 para primeira: {$musicaPosicao5['titulo']} - {$musicaPosicao5['artista']}\n\n";

// Cria um item na fila para fazer a troca
$stmtInsertFila = $pdo->prepare("INSERT INTO fila_rodadas (id_tenants, id_eventos, id_cantor, id_musica, ordem_na_rodada, rodada, status) VALUES (?, ?, ?, ?, 1, 1, 'aguardando')");
$stmtInsertFila->execute([ID_TENANTS, ID_EVENTO_ATIVO, $cantor['id'], $musicasAntes[0]['id']]);
$filaId = $pdo->lastInsertId();

// Busca o ID da música da posição 5
$stmtMusicaId = $pdo->prepare("SELECT id_musica FROM musicas_cantor WHERE id = ?");
$stmtMusicaId->execute([$musicaPosicao5['id']]);
$musicaId = $stmtMusicaId->fetchColumn();

// Executa a troca
$resultado = trocarMusicaNaFilaAtual($pdo, $filaId, $musicaId);

if ($resultado) {
    echo "✅ TROCA REALIZADA COM SUCESSO!\n\n";
    
    echo "DEPOIS DA TROCA:\n";
    $musicasDepois = mostrarFilaEVerificarSequencia($pdo, $cantor['id']);
    
    // Verifica se a música da posição 5 está agora na primeira
    if ($musicasDepois[0]['titulo'] == $musicaPosicao5['titulo']) {
        echo "✅ SUCESSO: A música da posição 5 foi movida para a primeira posição!\n";
    } else {
        echo "❌ ERRO: A música da posição 5 não está na primeira posição!\n";
    }
    
} else {
    echo "❌ ERRO: Falha ao realizar a troca!\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>