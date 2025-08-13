<?php
session_start();
include_once('conn.php');

// Configurações de cache e headers para SEO
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // Cache de 5 minutos
header('Vary: Accept-Encoding');

$pesquisa = isset($_POST['pesquisa']) ? trim($_POST['pesquisa']) : '';
$response = [];

if (strlen($pesquisa) < 2) {
    $response = [
        'success' => false,
        'message' => 'Digite mais de 2 caracteres para pesquisar.',
        'results' => [],
        'total' => 0
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Query otimizada com prepared statement para evitar SQL injection
    $sql = "SELECT 
                l.idLista,
                l.interprete,
                l.codigo,
                l.titulo,
                l.inicio,
                l.genero,
                l.idioma,
                -- Relevância baseada em matches exatos e parciais
                CASE 
                    WHEN l.codigo = ? THEN 100
                    WHEN l.titulo LIKE ? THEN 95
                    WHEN l.interprete LIKE ? THEN 90
                    WHEN l.titulo LIKE ? THEN 85
                    WHEN l.inicio LIKE ? THEN 75
                    WHEN l.interprete LIKE ? THEN 60
                    WHEN l.inicio LIKE ? THEN 45
                    ELSE 30
                END as relevancia
            FROM lista l
            WHERE (l.interprete LIKE ? 
                OR l.titulo LIKE ? 
                OR l.codigo LIKE ? 
                OR l.inicio LIKE ? 
                OR l.idioma LIKE ?)
            ORDER BY relevancia DESC, l.interprete ASC, l.titulo ASC"; // Sem LIMIT - retorna todos os resultados
    
    $stmt = $db->prepare($sql);
    
    // Parâmetros para busca
    $searchTerm = "%{$pesquisa}%";
    $exactCode = $pesquisa;
    $exactMatch = "{$pesquisa}%";
    
    $stmt->bind_param('ssssssssssss', 
        $exactCode,      // Para relevância de código exato
        $exactMatch,     // Para relevância de título (início)
        $exactMatch,     // Para relevância de intérprete (início)
        $searchTerm,     // Para relevância de título (qualquer posição)
        $exactMatch,     // Para relevância de início (início)
        $searchTerm,     // Para relevância de intérprete (qualquer posição)
        $searchTerm,     // Para relevância de início (qualquer posição)
        $searchTerm,     // interprete LIKE (WHERE)
        $searchTerm,     // titulo LIKE (WHERE)
        $searchTerm,     // codigo LIKE (WHERE)
        $searchTerm,     // inicio LIKE (WHERE)
        $searchTerm      // idioma LIKE (WHERE)
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $results = [];
    $i = 0;
    
    while ($row = $result->fetch_assoc()) {
        $i++;
        $idioma_classe = strtolower($row['idioma']);
        
        // Estrutura de dados otimizada para SEO e performance
        $results[] = [
            'id' => $row['idLista'],
            'codigo' => $row['codigo'],
            'interprete' => $row['interprete'],
            'titulo' => $row['titulo'],
            'inicio' => $row['inicio'],
            'idioma' => $row['idioma'],
            'idioma_classe' => $idioma_classe,
            'genero' => $row['genero'] ?? '',
            'relevancia' => $row['relevancia'],
            'fade_class' => ($i > 5) ? 'fade-in' : ''
        ];
    }
    
    $total = count($results);
    
    if ($total > 0) {
        $response = [
            'success' => true,
            'message' => "Sua busca retornou {$total} resultados",
            'results' => $results,
            'total' => $total,
            'query' => $pesquisa,
            'timestamp' => date('c') // ISO 8601 para melhor SEO
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Nenhuma ocorrência para a sua pesquisa: ' . mb_convert_case($pesquisa, MB_CASE_UPPER, 'UTF-8'),
            'results' => [],
            'total' => 0,
            'query' => $pesquisa,
            'timestamp' => date('c')
        ];
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erro interno do servidor. Tente novamente.',
        'results' => [],
        'total' => 0,
        'error' => $e->getMessage() // Remover em produção
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>