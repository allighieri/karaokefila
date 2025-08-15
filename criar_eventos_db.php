<?php
// Script para criar tabela eventos no banco de dados
require_once 'conn.php';

try {
    // SQL para criar a tabela eventos
    $sql = "
    CREATE TABLE IF NOT EXISTS `eventos` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `id_tenants` int(11) NOT NULL,
      `id_mc` int(11) NOT NULL,
      `nome_evento` varchar(255) NOT NULL,
      `descricao` text DEFAULT NULL,
      `status` enum('ativo','inativo') NOT NULL DEFAULT 'inativo',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `fk_evento_tenant` (`id_tenants`),
      KEY `fk_evento_mc` (`id_mc`),
      UNIQUE KEY `unique_nome_evento_tenant` (`nome_evento`, `id_tenants`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($sql);
    
    // Criar índices adicionais
    $indices = [
        "CREATE INDEX IF NOT EXISTS `idx_eventos_mc_status` ON `eventos` (`id_mc`, `status`)",
        "CREATE INDEX IF NOT EXISTS `idx_eventos_tenant_status` ON `eventos` (`id_tenants`, `status`)"
    ];
    
    foreach ($indices as $indice) {
        $pdo->exec($indice);
    }
    
    echo "Tabela 'eventos' criada com sucesso!\n";
    echo "Índices criados com sucesso!\n";
    
} catch (PDOException $e) {
    echo "Erro ao criar tabela eventos: " . $e->getMessage() . "\n";
}
?>