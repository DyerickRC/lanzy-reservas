-- Remove a massa de dados de demonstração (dados_demonstracao.sql).
-- Apaga os clientes @demo.lanzy; as reservas deles saem junto (ON DELETE CASCADE).
USE lanzy_reservas;
START TRANSACTION;
DELETE FROM usuarios WHERE email LIKE '%@demo.lanzy';
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 725, 0) WHERE id = 1;
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 716, 0) WHERE id = 2;
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 345, 0) WHERE id = 3;
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 837, 0) WHERE id = 4;
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 743, 0) WHERE id = 5;
UPDATE estacoes SET visualizacoes = GREATEST(visualizacoes - 348, 0) WHERE id = 6;
COMMIT;
