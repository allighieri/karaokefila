<?php
require_once 'conn.php';
require_once 'funcoes_fila.php';

// Define constantes necessárias
define('ID_TENANTS', 1);
define('ID_EVENTO_ATIVO', 1);

// Simula login como administrador
$_SESSION['id_usuario'] = 1;
$_SESSION['nivel_acesso'] = 'admin';

echo "=== TESTE DE REORGANIZAÇÃO DA FILA DO CANTOR ===\n\n";

// Busca um cantor para teste (qualquer cantor disponível)
$stmtCantor = $pdo->prepare("SELECT c.id, u.nome as nome_cantor FROM cantores c JOIN usuarios u ON c.id_usuario = u.id WHERE c.id_tenants = ? LIMIT 1");
$stmtCantor->execute([ID_TENANTS]);
$cantor = $stmtCantor->fetch(PDO::FETCH_ASSOC);

if (!$cantor) {
    echo "Nenhum cantor encontrado na fila atual.\n";
    exit;
}

echo "Cantor selecionado: {$cantor['nome_cantor']} (ID: {$cantor['id']})\n\n";

// Mostra a fila atual do cantor
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
        echo "  {$musica['ordem_na_lista']}. {$musica['titulo']} - {$musica['artista']} (Status: {$musica['status']})\n";
    }
    echo "\n";
}

// Mostra fila inicial
echo "ANTES DA TROCA:\n";
mostrarFilaCantor($pdo, $cantor['id']);

// Busca uma música que NÃO está na lista do cantor
$stmtMusicaNova = $pdo->prepare("
    SELECT m.id, m.titulo, m.artista 
    FROM musicas m 
    WHERE m.id NOT IN (
        SELECT mc.id_musica 
        FROM musicas_cantor mc 
        WHERE mc.id_cantor = ? AND mc.id_eventos = ?
    ) 
    LIMIT 1
");
$stmtMusicaNova->execute([$cantor['id'], ID_EVENTO_ATIVO]);
$musicaNova = $stmtMusicaNova->fetch(PDO::FETCH_ASSOC);

if (!$musicaNova) {
    echo "Nenhuma música nova encontrada para teste.\n";
    exit;
}

echo "Música nova para inserir: {$musicaNova['titulo']} - {$musicaNova['artista']} (ID: {$musicaNova['id']})\n\n";

// Busca um item da fila para trocar (ou cria um se não existir)
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
    // Se não há item na fila, cria um para teste
    $stmtMusicaExistente = $pdo->prepare("SELECT id, titulo, artista FROM musicas LIMIT 1");
    $stmtMusicaExistente->execute();
    $musicaExistente = $stmtMusicaExistente->fetch(PDO::FETCH_ASSOC);
    
    if ($musicaExistente) {
        $stmtInsertFila = $pdo->prepare("INSERT INTO fila_rodadas (id_tenants, id_eventos, id_cantor, id_musica, ordem_na_rodada, rodada, status) VALUES (?, ?, ?, ?, 1, 1, 'aguardando')");
        $stmtInsertFila->execute([ID_TENANTS, ID_EVENTO_ATIVO, $cantor['id'], $musicaExistente['id']]);
        $filaId = $pdo->lastInsertId();
        
        $itemFila = [
            'id' => $filaId,
            'titulo_atual' => $musicaExistente['titulo'],
            'artista_atual' => $musicaExistente['artista']
        ];
        
        echo "Item criado na fila para teste.\n";
    } else {
        echo "Nenhuma música encontrada no sistema.\n";
        exit;
    }
}

echo "Trocando música atual '{$itemFila['titulo_atual']} - {$itemFila['artista_atual']}' pela nova música...\n\n";

// Executa a troca
$resultado = trocarMusicaNaFilaAtual($pdo, $itemFila['id'], $musicaNova['id']);

if ($resultado) {
    echo "✅ TROCA REALIZADA COM SUCESSO!\n\n";
    
    echo "DEPOIS DA TROCA:\n";
    mostrarFilaCantor($pdo, $cantor['id']);
    
    // Verifica se a nova música está na primeira posição
    $stmtVerifica = $pdo->prepare("
        SELECT mc.ordem_na_lista 
        FROM musicas_cantor mc 
        WHERE mc.id_cantor = ? AND mc.id_musica = ? AND mc.id_eventos = ?
    ");
    $stmtVerifica->execute([$cantor['id'], $musicaNova['id'], ID_EVENTO_ATIVO]);
    $ordemNova = $stmtVerifica->fetchColumn();
    
    if ($ordemNova == 1) {
        echo "✅ SUCESSO: A nova música está na primeira posição (ordem_na_lista = 1)!\n";
    } else {
        echo "❌ ERRO: A nova música não está na primeira posição (ordem_na_lista = $ordemNova)!\n";
    }
    
} else {
    echo "❌ ERRO: Falha ao realizar a troca!\n";
}

echo "\n=== FIM DO TESTE ===\n";
?>