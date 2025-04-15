-- Structure de la base de données pour le système de réservation

-- Table des clients
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telephone VARCHAR(20),
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des services
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    description TEXT,
    prix_min DECIMAL(10, 2),
    prix_max DECIMAL(10, 2),
    duree_min INT, -- durée en minutes
    duree_max INT -- durée en minutes
);

-- Table des réservations
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    service_id INT,
    date_reservation DATE NOT NULL,
    heure_reservation TIME NOT NULL,
    statut ENUM('confirmé', 'en attente', 'annulé') DEFAULT 'en attente',
    note TEXT,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Insertion des services de base
INSERT INTO services (nom, description, prix_min, prix_max, duree_min, duree_max) VALUES
('Lissage Indien', 'Ultra naturel, basé sur des herbes ayurvédiques. Offre un effet soyeux tout en respectant la fibre capillaire.', 120.00, 220.00, 120, 210),
('Lissage Tanin', 'Au tanin, s\'origine en dépourvissant sans produits chimiques. Idéal pour les cheveux sensibilisés.', 150.00, 250.00, 90, 180); 