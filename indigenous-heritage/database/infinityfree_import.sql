-- Indigenous Cultural Heritage Portal
-- InfinityFree-ready import (NO Create Database / USE statements)
-- Import this into the database you create in InfinityFree phpMyAdmin

-- Admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crafts table
CREATE TABLE IF NOT EXISTS crafts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    materials TEXT NOT NULL,
    cultural_significance TEXT NOT NULL,
    image VARCHAR(255),
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Traditions table
CREATE TABLE IF NOT EXISTS traditions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    origin VARCHAR(100),
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Materials table
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    uses TEXT NOT NULL,
    cultural_importance TEXT NOT NULL,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stories table
CREATE TABLE IF NOT EXISTS stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100),
    category VARCHAR(50),
    origin VARCHAR(100),
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Gallery table
CREATE TABLE IF NOT EXISTS gallery (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255) NOT NULL,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE
);

-- Users table (for regular user registration)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin (password: admin123)
INSERT INTO admins (username, password, email) VALUES
('admin', '$2y$12$rYzcnjm8stLK4uZEM2iH/uvunLaoaTaYnp4NQ58HhiBH7hG9XsznG', 'admin@indigenousheritage.com');

-- Sample crafts
INSERT INTO crafts (name, description, materials, cultural_significance, image, category) VALUES
('Woven Bamboo Basket', 'Traditional hand-woven basket used for storing rice and other grains. The intricate weaving patterns represent the connection between the weaver and nature.', 'Bamboo strips, natural fiber binding', 'Symbolizes prosperity and abundance in indigenous communities. Used in harvest festivals and daily life.', 'basket.jpg', 'Weaving'),
('Beaded Necklace', 'Elaborate necklace made from colorful beads and shells. Each pattern tells a story about the wearer''s family and tribal history.', 'Glass beads, shells, natural fibers', 'Represents status, achievements, and tribal identity. Worn during ceremonies and important gatherings.', 'necklace.jpg', 'Jewelry'),
('Clay Pottery', 'Hand-molded clay pots used for cooking and storage. The designs are inspired by natural elements like rivers, mountains, and forests.', 'Local clay, natural pigments', 'Essential for food preparation and preservation. The pottery-making process is passed down through generations.', 'pottery.jpg', 'Pottery');

-- Sample traditions
INSERT INTO traditions (name, description, type, origin, image) VALUES
('Harvest Festival', 'Annual celebration of the rice harvest with traditional dances, music, and feasting. Community members share the first harvest as a sign of gratitude.', 'Ceremony', 'Various Indigenous Communities', 'harvest.jpg'),
('Tribal Dance', 'Traditional dance performed during important ceremonies. The movements mimic natural elements and tell stories of ancestors.', 'Dance', 'Mountain Regions', 'dance.jpg'),
('Sacred Ritual', 'Spiritual ceremony performed to honor ancestors and seek blessings for the community. Involves chanting, drumming, and offerings.', 'Ritual', 'Forest Communities', 'ritual.jpg');

-- Sample materials
INSERT INTO materials (name, description, uses, cultural_importance, image) VALUES
('Bamboo', 'Fast-growing grass known for its strength and flexibility. Indigenous communities have used bamboo for centuries in construction, crafts, and daily tools.', 'Construction, weaving, tools, musical instruments', 'Represents resilience and adaptability. Sacred in many indigenous cultures as a symbol of longevity.', 'bamboo.jpg'),
('Rattan', 'Palm vine used extensively in furniture making and basket weaving. Its natural flexibility makes it ideal for creating durable yet lightweight items.', 'Furniture, baskets, crafts, binding', 'Symbolizes the interconnectedness of all living things. Harvested sustainably to maintain forest balance.', 'rattan.jpg'),
('Clay', 'Natural earth material used for pottery and sculpture. Different regions have distinct clay types with unique properties.', 'Pottery, sculpture, building materials', 'Represents the earth and our connection to the land. Pottery-making is often a communal activity.', 'clay.jpg');

-- Sample stories
INSERT INTO stories (title, content, author, category, origin, image) VALUES
('The Legend of the Sacred Mountain', 'Long ago, when the world was young, there lived a great warrior who protected his people from harm. The gods, impressed by his courage and wisdom, transformed him into a mountain that would forever watch over the land. To this day, the indigenous people believe the mountain is their eternal guardian.', 'Elder Maria Santos', 'Legend', 'Northern Highlands', 'mountain.jpg'),
('The First Weaver', 'In the beginning, the people had no knowledge of weaving. One day, a spider appeared to a young woman in a dream and taught her the intricate patterns of weaving. When she awoke, she remembered everything and taught her people. This is why indigenous weavers always honor the spider in their work.', 'Chief Juan dela Cruz', 'Myth', 'Central Valley', 'weaver.jpg'),
('The River''s Gift', 'During a great drought, the river dried up and the people suffered. A young girl prayed to the water spirits for help. In response, the spirits taught the people how to find hidden springs and preserve water. The river returned, and the community learned the importance of respecting and protecting water sources.', 'Storyteller Pedro Reyes', 'Folk Tale', 'River Communities', 'river.jpg');

-- Sample gallery items
INSERT INTO gallery (title, description, image, category) VALUES
('Traditional Weaving', 'Master weaver creating intricate patterns on a backstrap loom.', 'weaving.jpg', 'Crafts'),
('Tribal Ceremony', 'Community members gathered for a traditional ceremony with elaborate costumes.', 'ceremony.jpg', 'Traditions'),
('Handmade Pottery', 'Collection of clay pots with traditional designs.', 'pottery_gallery.jpg', 'Crafts'),
('Cultural Dance', 'Performers in traditional attire doing a tribal dance.', 'dance_gallery.jpg', 'Traditions');
