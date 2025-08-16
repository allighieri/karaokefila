-- Script para permitir que MCs diferentes tenham mesas com o mesmo nome
-- mas impedir que o mesmo MC tenha duas mesas com o mesmo nome

-- 1. Remover a constraint única atual que impede nomes duplicados por tenant+evento
ALTER TABLE `mesas` DROP INDEX `uk_mesa_nome_tenant_evento`;

-- 2. A validação de nomes únicos por MC será feita apenas no código PHP
-- através das funções adicionarMesa() e edit_mesa na API

-- 3. Comentários sobre as mudanças implementadas:
/*
MUDANÇAS IMPLEMENTADAS NO CÓDIGO PHP:

1. Função adicionarMesa() em funcoes_fila.php:
   - Modificada para verificar duplicação por MC através de JOIN com tabela eventos
   - Query: SELECT COUNT(*) FROM mesas m JOIN eventos e ON m.id_eventos = e.id 
            WHERE m.nome_mesa = ? AND m.id_tenants = ? AND e.id_usuario_mc = ?

2. Ação edit_mesa em api.php:
   - Adicionada validação antes da edição para verificar duplicação por MC
   - Query: SELECT COUNT(*) FROM mesas m JOIN eventos e ON m.id_eventos = e.id 
            WHERE m.nome_mesa = ? AND m.id_tenants = ? AND e.id_usuario_mc = ? AND m.id != ?

RESULTADO:
- MCs diferentes podem ter mesas com o mesmo nome
- Um MC não pode ter duas mesas com o mesmo nome
- Mantém isolamento por tenant
*/