CREATE DATABASE IF NOT EXISTS bookmarks;

DROP DATABASE bookmarks;

DROP TABLE bookmark;



CREATE TABLE bookmark (
    id INT PRIMARY KEY NOT NULL AUTO_INCREMENT,
    title VARCHAR(70) NOT NULL,
    url TEXT NOT NULL,
    description VARCHAR(255);

INSERT INTO bookmark (title, url, description) VALUES
('Google', 'https://www.google.com', 'Moteur de recherche universel.'),
('GitHub', 'https://github.com', 'Hébergement de code et gestion de versions avec Git.'),
('Stack Overflow', 'https://stackoverflow.com', 'Forum d''entraide pour les développeurs et programmation.'),
('MDN Web Docs', 'https://developer.mozilla.org', 'Documentation complète sur HTML, CSS et JavaScript.'),
('YouTube', 'https://www.youtube.com', 'Plateforme de partage de vidéos en ligne.'),
('ChatGPT', 'https://chatgpt.com', 'Interface d''intelligence artificielle conversationnelle par OpenAI.'),
('Trello', 'https://trello.com', 'Outil de gestion de projet basé sur la méthode Kanban.'),
('Canva', 'https://www.canva.com', 'Outil de conception graphique simplifié en ligne.');