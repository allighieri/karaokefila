-- Script para corrigir o problema de mesas compartilhadas entre eventos
-- Adiciona isolamento de mesas por evento

-- 1. Adicionar campo id_eventos na tabela mesas
ALTER TABLE `mesas` ADD COLUMN `id_eventos` INT NOT NULL AFTER `id_tenants`;

-- 2. Adicionar índice para melhor performance
ALTER TABLE `mesas` ADD INDEX `idx_mesas_tenant_evento` (`id_tenants`, `id_eventos`);

-- 3. Adicionar chave estrangeira para garantir integridade
ALTER TABLE `mesas` ADD CONSTRAINT `fk_mesas_eventos` 
    FOREIGN KEY (`id_eventos`) REFERENCES `eventos` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE;

-- 4. Migrar dados existentes (associar mesas ao evento ativo de cada tenant)
-- ATENÇÃO: Este UPDATE deve ser executado com cuidado em produção
-- Ele associa todas as mesas existentes ao primeiro evento ativo de cada tenant
UPDATE `mesas` m 
SET `id_eventos` = (
    SELECT e.id 
    FROM `eventos` e 
    WHERE e.id_tenants = m.id_tenants 
    AND e.status = 'ativo' 
    ORDER BY e.id ASC 
    LIMIT 1
)
WHERE `id_eventos` = 0;

-- 5. Verificar se há mesas sem evento associado (caso não haja evento ativo)
-- Estas mesas precisarão ser tratadas manualmente
SELECT m.id, m.nome_mesa, m.id_tenants, 'Mesa sem evento ativo associado' as problema
FROM `mesas` m 
WHERE `id_eventos` = 0;

-- 6. Atualizar constraint para tornar id_eventos obrigatório
-- (Já está NOT NULL, mas garantindo que não há valores 0)
UPDATE `mesas` SET `id_eventos` = 1 WHERE `id_eventos` = 0 AND id_tenants = 1;

-- 7. Criar índice único para evitar mesas duplicadas por evento
-- (nome_mesa deve ser único por tenant + evento)
ALTER TABLE `mesas` ADD UNIQUE KEY `uk_mesa_nome_tenant_evento` (`id_tenants`, `id_eventos`, `nome_mesa`);

-- 8. Comentários sobre as mudanças necessárias no código:
/*
MUDANÇAS NECESSÁRIAS NO CÓDIGO PHP:

1. Função adicionarMesa() - Incluir id_eventos:
   INSERT INTO mesas (id_tenants, id_eventos, nome_mesa) VALUES (?, ?, ?)

2. Função getTodasMesas() - Filtrar por evento:
   SELECT id, nome_mesa, tamanho_mesa FROM mesas WHERE id_tenants = ? AND id_eventos = ?

3. Todas as consultas que usam mesas devem incluir filtro por id_eventos

4. Interface de usuário deve mostrar que mesas são específicas do evento atual

5. Ao trocar de evento, as mesas devem ser recarregadas

6. Regras de configuração de mesa permanecem por tenant (não por evento)
   pois são regras gerais do estabelecimento
*/

-- 9. Script de verificação da integridade após a migração
SELECT 
    'Mesas por tenant/evento' as verificacao,
    t.nome as tenant,
    e.nome as evento,
    COUNT(m.id) as total_mesas,
    SUM(m.tamanho_mesa) as total_cantores
FROM mesas m
JOIN tenants t ON m.id_tenants = t.id
JOIN eventos e ON m.id_eventos = e.id
GROUP BY m.id_tenants, m.id_eventos
ORDER BY t.nome, e.nome;