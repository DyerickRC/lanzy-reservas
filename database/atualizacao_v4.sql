-- Atualização v4: contador de visualizações das estações (procura), usado pela aba Analytics.
-- Rode uma vez, depois da atualizacao_v3.sql.
USE lanzy_reservas;

ALTER TABLE estacoes ADD COLUMN visualizacoes INT NOT NULL DEFAULT 0 AFTER valor_hora;
