-- Script de otimização de índices para melhorar a performance da busca de músicas
-- Execute este script no seu banco de dados MySQL/MariaDB

-- Índice composto para id_tenants + titulo (busca mais comum)
CREATE INDEX idx_tenants_titulo ON musicas (id_tenants, titulo);

-- Índice composto para id_tenants + artista
CREATE INDEX idx_tenants_artista ON musicas (id_tenants, artista);

-- Índice composto para id_tenants + codigo (busca exata por código)
CREATE INDEX idx_tenants_codigo ON musicas (id_tenants, codigo);

-- Índice de texto completo para busca em trecho (se suportado pelo MySQL)
-- ALTER TABLE musicas ADD FULLTEXT(trecho);

-- Verificar os índices criados
SHOW INDEX FROM musicas;

-- Estatísticas dos índices (opcional - para monitoramento)
-- ANALYZE TABLE musicas;

/*
Explicação dos índices:

1. idx_tenants_titulo: Otimiza buscas por título dentro do tenant
2. idx_tenants_artista: Otimiza buscas por artista dentro do tenant  
3. idx_tenants_codigo: Otimiza buscas por código dentro do tenant

Estes índices compostos são mais eficientes porque:
- Filtram primeiro por id_tenants (reduz drasticamente o conjunto de dados)
- Depois aplicam a busca no campo específico
- Evitam table scans completos

Para melhor performance:
- Buscas por código numérico usam igualdade (=) em vez de LIKE
- Buscas por texto usam ordenação por relevância
- Limite de 20 resultados evita sobrecarga
*/