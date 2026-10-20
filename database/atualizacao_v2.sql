-- Atualização v2: marca das estações, bloqueio de clientes e valor guardado na reserva.
-- Rode uma vez em um banco criado com a versão anterior do schema.sql.
USE lanzy_reservas;

ALTER TABLE estacoes ADD COLUMN marca VARCHAR(50) DEFAULT NULL AFTER tipo;
ALTER TABLE usuarios ADD COLUMN bloqueado TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo;
ALTER TABLE reservas ADD COLUMN valor_total DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER hora_fim;

UPDATE estacoes SET marca = 'PlayStation' WHERE marca IS NULL AND nome LIKE '%PlayStation%';
UPDATE estacoes SET marca = 'Xbox' WHERE marca IS NULL AND nome LIKE '%Xbox%';
UPDATE estacoes SET marca = 'Nintendo' WHERE marca IS NULL AND (nome LIKE '%Nintendo%' OR nome LIKE '%Switch%');

-- Calcula o valor das reservas antigas (com desconto corujão a partir de 8h)
UPDATE reservas r
JOIN estacoes e ON e.id = r.estacao_id
JOIN (
    SELECT id,
           (TIME_TO_SEC(TIMEDIFF(hora_fim, hora_inicio)) + IF(hora_fim <= hora_inicio, 86400, 0)) / 3600 AS horas
    FROM reservas
) d ON d.id = r.id
SET r.valor_total = ROUND(
    d.horas * e.valor_hora * (1 - CASE
        WHEN d.horas >= 12 THEN 0.20
        WHEN d.horas >= 10 THEN 0.15
        WHEN d.horas >= 8 THEN 0.10
        ELSE 0
    END), 2);
