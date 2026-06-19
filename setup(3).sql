-- ============================================================
-- SETUP BASE DE DATOS — Sistema de Votación Bolivia
-- Ejecutar como root en MariaDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS votos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE votos_db;

-- Tabla de electores
CREATE TABLE IF NOT EXISTS electores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    carnet VARCHAR(20) NOT NULL UNIQUE,
    ha_votado TINYINT(1) DEFAULT 0,
    fecha_voto DATETIME NULL,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de votos
CREATE TABLE IF NOT EXISTS votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    elector_id INT NOT NULL,
    candidato ENUM('A','B') NOT NULL,
    fecha_voto DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (elector_id) REFERENCES electores(id),
    UNIQUE KEY voto_unico (elector_id)
);

-- Tabla de resultados
CREATE TABLE IF NOT EXISTS resultados (
    candidato ENUM('A','B') PRIMARY KEY,
    total_votos INT DEFAULT 0
);

INSERT IGNORE INTO resultados VALUES ('A', 0), ('B', 0);

-- 5 electores de prueba
INSERT INTO electores (nombre, carnet) VALUES
('Juan Carlos Mamani Flores',  '1234567'),
('Maria Elena Condori Quispe', '2345678'),
('Pedro Alejo Tarqui Huanca',  '3456789'),
('Rosa Nilda Vargas Choque',   '4567890'),
('Luis Antonio Paco Mamani',   '5678901');

-- Usuario para la aplicación
CREATE USER IF NOT EXISTS 'votacion_user'@'%' IDENTIFIED BY 'VotacionBolivia2025!';
GRANT SELECT, INSERT, UPDATE ON votos_db.* TO 'votacion_user'@'%';
FLUSH PRIVILEGES;

SELECT 'Base de datos lista' AS estado;
SELECT carnet, nombre FROM electores;
