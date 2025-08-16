<?php
require_once 'conn.php';
require_once 'funcoes_fila.php';

// Define constantes necessárias
define('ID_TENANTS', 1);
define('ID_EVENTO_ATIVO', 1);

// Simula login como administrador
$_SESSION['id_usuario'] = 1;
$_SESSION['nivel_acesso'] = 'admin';

echo "=== TESTE DE REORGANIZAÇÃO COM MÚSICA EXISTENTE ===\n\n";

// Busca um cantor que tenha múltiplas músicas na lista
$stmtCantor = $pdo->prepare("
    SELECT c.id, u.nome as nome_cantor, COUNT(mc.id) as total_musicas
    FROM cantores c 
    JOIN usuarios u ON c.id_usuario = u.id 
    JOIN musicas_cantor mc ON c.id = mc.id_cantor AND mc.id_eventos = ?
    WHERE c.id_tenants = ? 
    GROUP BY c.id, u.nome 
    HAVING total_musicas >= 3
    LIMIT 1
");
$stmtCantor->execute([ID_EVENTO_ATIVO, ID_TENANTS]);
$cantor = $stmtCantor->fetch(PDO::FETCH_ASSOC);

if (!$cantor) {
    echo "Nenhum cantor com múltiplas músicas encontrado.\n";
    exit;
}

echo "Cantor selecionado: {$cantor['nome_cantor']} (ID: {$cantor['id']}) - {$cantor['total_musicas']} músicas\n\n";

// Função para mostrar a fila atual do cantor
function mostrarFilaCantor($pdo, $idCantor) {
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
    foreach ($musicas as $musica) {
        $statusIcon = $musica['status'] == 'selecionada_para_rodada' ? '🎤' : '⏳';
        echo "  {$musica['ordem_na_lista']}. {$musica['titulo']} - {$musica['artista']} {$statusIcon} {$musica['status']}\n";
    }
    echo "\n";
    
    return $musicas;
}

// Mostra fila inicial
echo "ANTES DA TROCA:\n";
$musicasAntes = mostrarFilaCantor($pdo, $cantor['id']);

// Seleciona uma música que está na terceira posição ou mais para mover para primeira
$musicaParaMover = null;
foreach ($musicasAntes as $musica) {
    if ($musica['ordem_na_lista'] >= 3) {
        $musicaParaMover = $musica;
        break;
    }
}

if (!$musicaParaMover) {
    echo "Não há música na terceira posição ou mais para testar.\n";
    exit;
}

echo "Música a ser movida para primeira posição: {$musicaParaMover['titulo']} - {$musicaParaMover['artista']} (posição atual: {$musicaParaMover['ordem_na_lista']})\n\n";

// Cria ou busca um item na fila para fazer a troca
$stmtFila = $pdo->prepare("
    SELECT fr.id, m.titulo as titulo_atual, m.artista as artista_atual
    FROM fila_rodadas fr 
    JOIN musicas m ON fr.id_musica = m.id 
    WHERE fr.id_cantor = ? AND fr.id_eventos = ? 
    LIMIT 1
");
$stmtFila->execute([$cantor['id'], ID_EVENTO_ATIVO]);
$itemFila = $stmtFila->fetch(PDO::FETCH_ASSOC);

if (!$itemFila) {
    // Cria um item na fila para teste
    $stmtInsertFila = $pdo->prepare("INSERT INTO fila_rodadas (id_tenants, id_eventos, id_cantor, id_musica, ordem_na_rodada, rodada, status) VALUES (?, ?, ?, ?, 1, 1, 'aguardando')");
    $stmtInsertFila->execute([ID_TENANTS, ID_EVENTO_ATIVO, $cantor['id'], $musicasAntes[0]['id']]);
    $filaId = $pdo->lastInsertId();
    
    $itemFila = [
        'id' => $filaId,
        'titulo_atual' => $musicasAntes[0]['titulo'],
        'artista_atual' => $musicasAntes[0]['artista']
    ];
    
    echo "Item criado na fila para teste.\n";
}

echo "Trocando música atual '{$itemFila['titulo_atual']} - {$itemFila['artista_atual']}' pela música da posição {$musicaParaMover['ordem_na_lista']}...\n\n";

// Busca o ID da música a ser movida
$stmtMusicaId = $pdo->prepare("SELECT id_musica FROM musicas_cantor WHERE id = ?");
$stmtMusicaId->execute([$musicaParaMover['id']]);
$musicaId = $stmtMusicaId->fetchColumn();

// Executa a troca
$resultado = trocarMusicaNaFilaAtual($pdo, $itemFila['id'], $musicaId);

if ($resultado) {
    echo "✅ TROCA REALIZADA COM SUCESSO!\n\n";
    
    echo "DEPOIS DA TROCA:\n";
    $musicasDepois = mostrarFilaCantor($pdo, $cantor['id']);
    
    // Verifica se a música movida está na primeira posição
    $musicaMovidaNaPrimeira = false;
    foreach ($musicasDepois as $musica) {
        if ($musica['ordem_na_lista'] == 1 && $musica['titulo'] == $musicaParaMover['titulo']) {
            $musicaMovidaNaPrimeira = true;
            break;
        }
    }
    
    if ($musicaMovidaNaPrimeira) {
        echo "✅ SUCESSO: A música '{$musicaParaMover['titulo']}' foi movida para a primeira posição!\n";
    } else {
        echo "❌ ERRO: A música '{$musicaParaMover['titulo']}' não está na primeira posição!\n";
    }
    
    // Verifica se as outras músicas foram reorganizadas corretamente
    $ordemCorreta = true;
    $ordemEsperada = 2;
    foreach ($musicasDepois as $musica) {
        if ($musica['ordem_na_lista'] != 1 && $musica['titulo'] != $musicaParaMover['titulo']) {
            if ($musica['ordem_na_lista'] != $ordemEsperada) {
                $ordemCorreta = false;
                break;
            }
            $ordemEsperada++;
        }
    }
    
    if ($ordemCorreta) {
        echo "✅ SUCESSO: As demais músicas foram reorganizadas corretamente!\n";
    } else {
        echo "❌ ERRO: Há problemas na reorganização das demais músicas!\n";
    }
    
} else {
    echo "❌ ERRO: Falha ao realizar a troca!\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>