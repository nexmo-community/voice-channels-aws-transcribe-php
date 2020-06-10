

CREATE DATABASE IF NOT EXISTS `sample_database` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE `transcriptions` (
    `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `conversation_uuid` varchar(255) NOT NULL,
    `channel` varchar(255) NOT NULL,
    `message` text COLLATE utf8mb4_general_ci NOT NULL,
    `created` datetime NOT NULL,
    `modified` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
