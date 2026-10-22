-- Banco de dados do sistema Lanzy - Reservas de Computadores e Video Games
CREATE DATABASE IF NOT EXISTS lanzy_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lanzy_reservas;

-- Usuários do sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    bloqueado TINYINT(1) NOT NULL DEFAULT 0,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Estações disponíveis (computadores e video games)
CREATE TABLE estacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('computador', 'video game') NOT NULL,
    marca VARCHAR(50) DEFAULT NULL,
    descricao VARCHAR(255) DEFAULT NULL,
    valor_hora DECIMAL(6,2) NOT NULL DEFAULT 0,
    visualizacoes INT NOT NULL DEFAULT 0,
    status ENUM('disponivel', 'manutencao', 'inativa') NOT NULL DEFAULT 'disponivel',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reservas feitas pelos usuários
-- Quando hora_fim <= hora_inicio, a reserva termina no dia seguinte (corujão)
CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    estacao_id INT NOT NULL,
    data_reserva DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    valor_total DECIMAL(8,2) NOT NULL DEFAULT 0,
    status ENUM('confirmada', 'cancelada', 'concluida') NOT NULL DEFAULT 'confirmada',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (estacao_id) REFERENCES estacoes(id) ON DELETE CASCADE
);

-- Dados de exemplo
INSERT INTO estacoes (nome, tipo, marca, descricao, valor_hora, status) VALUES
('PC Gamer 01', 'computador', NULL, 'RTX 4070, 32GB RAM, monitor 144Hz', 15.00, 'disponivel'),
('PC Gamer 02', 'computador', NULL, 'RTX 4060, 16GB RAM, monitor 144Hz', 12.00, 'disponivel'),
('PC Gamer 03', 'computador', NULL, 'RTX 3060, 16GB RAM, monitor 120Hz', 10.00, 'disponivel'),
('PlayStation 5 - Sala 01', 'video game', 'PlayStation', 'PS5 com 2 controles, tela 55"', 18.00, 'disponivel'),
('Xbox Series X - Sala 02', 'video game', 'Xbox', 'Xbox Series X com 2 controles, tela 50"', 18.00, 'disponivel'),
('Nintendo Switch - Sala 03', 'video game', 'Nintendo', 'Switch OLED com 4 controles', 14.00, 'disponivel');

-- Usuário admin padrão (senha: admin123)
INSERT INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador', 'admin@lanzy.com', '$2y$10$o1cCkFZ4BPSqP2k1Y8f.i.PHnJGmwewvYcwx3peT4WVjoZaMrDy1a', 'admin');
