-- Atualização v3: estações podem ser desativadas (some do site, mas mantém o histórico).
-- Rode uma vez, depois da atualizacao_v2.sql.
USE lanzy_reservas;

ALTER TABLE estacoes
    MODIFY status ENUM('disponivel', 'manutencao', 'inativa') NOT NULL DEFAULT 'disponivel';
