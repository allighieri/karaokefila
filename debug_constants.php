<?php
require_once 'conn.php';

function debugUsuario($email) {
    global $pdo;
    
    echo "<h3>Debug para usuário: $email</h3>";
    
    // Buscar dados do usuário
    $stmt = $pdo->prepare("
        SELECT u.*, t.nome as tenant_nome, t.status as tenant_status 
        FROM usuarios u 
        JOIN tenants t ON u.id_tenants = t.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        echo "<p style='color: red;'>Usuário $email não encontrado!</p>";
        return;
    }
    
    echo "<p><strong>Dados do usuário:</strong></p>";
    echo "<ul>";
    echo "<li>ID Usuário: " . $usuario['id'] . "</li>";
    echo "<li>ID Tenants: " . $usuario['id_tenants'] . "</li>";
    echo "<li>Nome: " . $usuario['nome'] . "</li>";
    echo "<li>Nível: " . $usuario['nivel'] . "</li>";
    echo "<li>Tenant: " . $usuario['tenant_nome'] . " (ID: " . $usuario['id_tenants'] . ")</li>";
    echo "</ul>";
    
    // Buscar eventos do usuário
    if ($usuario['nivel'] === 'mc') {
        echo "<p><strong>Eventos do MC (id_usuario_mc = " . $usuario['id'] . "):</strong></p>";
        $stmt = $pdo->prepare("SELECT id, nome, status, id_tenants, id_usuario_mc FROM eventos WHERE id_usuario_mc = ? ORDER BY status DESC, nome");
        $stmt->execute([$usuario['id']]);
    } else {
        echo "<p><strong>Eventos do tenant (id_tenants = " . $usuario['id_tenants'] . "):</strong></p>";
        $stmt = $pdo->prepare("SELECT id, nome, status, id_tenants, id_usuario_mc FROM eventos WHERE id_tenants = ? ORDER BY status DESC, nome");
        $stmt->execute([$usuario['id_tenants']]);
    }
    
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($eventos) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Status</th><th>ID Tenant</th><th>ID MC</th></tr>";
        foreach ($eventos as $ev) {
            $cor = $ev['status'] === 'ativo' ? 'background-color: #d4edda;' : '';
            echo "<tr style='$cor'>";
            echo "<td>" . $ev['id'] . "</td>";
            echo "<td>" . $ev['nome'] . "</td>";
            echo "<td><strong>" . $ev['status'] . "</strong></td>";
            echo "<td>" . $ev['id_tenants'] . "</td>";
            echo "<td>" . $ev['id_usuario_mc'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Mostrar qual evento seria selecionado como ativo
        if ($usuario['nivel'] === 'mc') {
            $stmt = $pdo->prepare("SELECT id, nome FROM eventos WHERE id_usuario_mc = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$usuario['id']]);
        } else {
            $stmt = $pdo->prepare("SELECT id, nome FROM eventos WHERE id_tenants = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$usuario['id_tenants']]);
        }
        
        $eventoAtivo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($eventoAtivo) {
            echo "<p style='color: green;'><strong>Evento ativo selecionado: ID " . $eventoAtivo['id'] . " - " . $eventoAtivo['nome'] . "</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>Nenhum evento ativo encontrado!</strong></p>";
        }
    } else {
        echo "<p>Nenhum evento encontrado.</p>";
    }
    
    // Verificar mesas existentes para este usuário
    echo "<p><strong>Mesas existentes:</strong></p>";
    if ($usuario['nivel'] === 'mc') {
        // Para MC, buscar mesas dos eventos do MC
        $stmt = $pdo->prepare("
            SELECT m.id, m.nome_mesa, m.id_eventos, e.nome as nome_evento, m.id_tenants
            FROM mesas m 
            JOIN eventos e ON m.id_eventos = e.id 
            WHERE e.id_usuario_mc = ?
            ORDER BY e.nome, m.nome_mesa
        ");
        $stmt->execute([$usuario['id']]);
    } else {
        // Para outros níveis, buscar por tenant
        $stmt = $pdo->prepare("
            SELECT m.id, m.nome_mesa, m.id_eventos, e.nome as nome_evento, m.id_tenants
            FROM mesas m 
            JOIN eventos e ON m.id_eventos = e.id 
            WHERE m.id_tenants = ?
            ORDER BY e.nome, m.nome_mesa
        ");
        $stmt->execute([$usuario['id_tenants']]);
    }
    
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($mesas) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID Mesa</th><th>Nome Mesa</th><th>ID Evento</th><th>Nome Evento</th><th>ID Tenant</th></tr>";
        foreach ($mesas as $mesa) {
            echo "<tr>";
            echo "<td>" . $mesa['id'] . "</td>";
            echo "<td>" . $mesa['nome_mesa'] . "</td>";
            echo "<td>" . $mesa['id_eventos'] . "</td>";
            echo "<td>" . $mesa['nome_evento'] . "</td>";
            echo "<td>" . $mesa['id_tenants'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhuma mesa encontrada.</p>";
    }
    
    echo "<hr>";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Constantes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; }
        th, td { padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Debug de Usuários e Eventos</h1>
    
    <?php
    // Debug para dnovaescastro@gmail.com
    debugUsuario('dnovaescastro@gmail.com');
    
    // Debug para claudio@gmail.com
    debugUsuario('claudio@gmail.com');
    ?>
    
</body>
</html>