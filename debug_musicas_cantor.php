<?php
// Script de debug para verificar o problema das músicas do cantor
require_once 'init.php';
require_once 'funcoes_fila.php';

// Simular os parâmetros que estão sendo passados
$cantor_selecionado_id = filter_input(INPUT_GET, 'cantor_id', FILTER_VALIDATE_INT) ?: 1;
$evento_selecionado_id = filter_input(INPUT_GET, 'evento_id', FILTER_VALIDATE_INT) ?: 1;

echo "<h2>Debug - Músicas do Cantor</h2>";
echo "<p><strong>Cantor ID:</strong> $cantor_selecionado_id</p>";
echo "<p><strong>Evento ID:</strong> $evento_selecionado_id</p>";
echo "<p><strong>Tenant ID (constante):</strong> " . ID_TENANTS . "</p>";

try {
    // 1. Verificar se o cantor existe e a qual tenant pertence
    echo "<h3>1. Verificando Cantor</h3>";
    $stmtCantor = $pdo->prepare("SELECT c.id, c.id_tenants, u.nome FROM cantores c JOIN usuarios u ON c.id_usuario = u.id WHERE c.id = ?");
    $stmtCantor->execute([$cantor_selecionado_id]);
    $cantor = $stmtCantor->fetch(PDO::FETCH_ASSOC);
    
    if ($cantor) {
        echo "<p>✓ Cantor encontrado: {$cantor['nome']} (ID: {$cantor['id']}, Tenant: {$cantor['id_tenants']})</p>";
    } else {
        echo "<p>❌ Cantor não encontrado!</p>";
    }
    
    // 2. Verificar se o evento existe e a qual tenant pertence
    echo "<h3>2. Verificando Evento</h3>";
    $stmtEvento = $pdo->prepare("SELECT e.id, e.nome, e.id_tenants, u.nome as nome_mc FROM eventos e JOIN usuarios u ON e.id_usuario_mc = u.id WHERE e.id = ?");
    $stmtEvento->execute([$evento_selecionado_id]);
    $evento = $stmtEvento->fetch(PDO::FETCH_ASSOC);
    
    if ($evento) {
        echo "<p>✓ Evento encontrado: {$evento['nome']} - MC: {$evento['nome_mc']} (ID: {$evento['id']}, Tenant: {$evento['id_tenants']})</p>";
    } else {
        echo "<p>❌ Evento não encontrado!</p>";
    }
    
    // 3. Verificar compatibilidade tenant
    if ($cantor && $evento) {
        if ($cantor['id_tenants'] == $evento['id_tenants']) {
            echo "<p>✓ Cantor e Evento pertencem ao mesmo tenant ({$cantor['id_tenants']})</p>";
        } else {
            echo "<p>❌ PROBLEMA: Cantor pertence ao tenant {$cantor['id_tenants']}, mas Evento pertence ao tenant {$evento['id_tenants']}</p>";
        }
    }
    
    // 4. Buscar todas as músicas do cantor (sem filtro de evento)
    echo "<h3>3. Todas as Músicas do Cantor (sem filtro de evento)</h3>";
    $stmtTodasMusicas = $pdo->prepare("
        SELECT 
            mc.id AS musica_cantor_id,
            mc.id_eventos,
            m.titulo,
            m.artista,
            mc.status,
            e.nome as nome_evento
        FROM musicas_cantor mc
        JOIN musicas m ON mc.id_musica = m.id
        LEFT JOIN eventos e ON mc.id_eventos = e.id
        WHERE mc.id_cantor = ?
        ORDER BY mc.id_eventos, mc.ordem_na_lista ASC
    ");
    $stmtTodasMusicas->execute([$cantor_selecionado_id]);
    $todasMusicas = $stmtTodasMusicas->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($todasMusicas)) {
        echo "<p>Nenhuma música encontrada para este cantor.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Evento ID</th><th>Nome Evento</th><th>Título</th><th>Artista</th><th>Status</th></tr>";
        foreach ($todasMusicas as $musica) {
            echo "<tr>";
            echo "<td>{$musica['musica_cantor_id']}</td>";
            echo "<td>{$musica['id_eventos']}</td>";
            echo "<td>{$musica['nome_evento']}</td>";
            echo "<td>{$musica['titulo']}</td>";
            echo "<td>{$musica['artista']}</td>";
            echo "<td>{$musica['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 5. Buscar músicas do cantor filtradas por evento (consulta atual)
    echo "<h3>4. Músicas do Cantor Filtradas por Evento (consulta atual)</h3>";
    $stmtMusicasCantor = $pdo->prepare("
        SELECT
            mc.id AS musica_cantor_id,
            m.id AS id_musica,
            m.titulo,
            m.artista,
            m.codigo,
            mc.ordem_na_lista,
            mc.status,
            mc.timestamp_ultima_execucao,
            mc.id_eventos
        FROM musicas_cantor mc
        JOIN musicas m ON mc.id_musica = m.id
        WHERE mc.id_cantor = ? AND mc.id_eventos = ?
        ORDER BY mc.ordem_na_lista ASC
    ");
    $stmtMusicasCantor->execute([$cantor_selecionado_id, $evento_selecionado_id]);
    $musicasDoCantor = $stmtMusicasCantor->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($musicasDoCantor)) {
        echo "<p>Nenhuma música encontrada para este cantor no evento selecionado.</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Evento ID</th><th>Título</th><th>Artista</th><th>Código</th><th>Ordem</th><th>Status</th></tr>";
        foreach ($musicasDoCantor as $musica) {
            echo "<tr>";
            echo "<td>{$musica['musica_cantor_id']}</td>";
            echo "<td>{$musica['id_eventos']}</td>";
            echo "<td>{$musica['titulo']}</td>";
            echo "<td>{$musica['artista']}</td>";
            echo "<td>{$musica['codigo']}</td>";
            echo "<td>{$musica['ordem_na_lista']}</td>";
            echo "<td>{$musica['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Erro de banco de dados: " . $e->getMessage() . "</p>";
}

echo "<br><br><a href='musicas_cantores.php'>← Voltar para Músicas dos Cantores</a>";
?>