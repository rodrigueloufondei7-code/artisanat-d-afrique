-- Artisanat d'Afrique : ajout de 15 articles avec des photos hébergées sur des sites externes.

-- Les catégories manquantes sont créées automatiquement. Les articles existants ne sont pas dupliqués par nom.

INSERT INTO categories (nom, description, image_url)
SELECT 'Accessoires', 'Articles artisanaux de la catégorie Accessoires.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Accessoires');

INSERT INTO categories (nom, description, image_url)
SELECT 'Bijoux', 'Articles artisanaux de la catégorie Bijoux.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Bijoux');

INSERT INTO categories (nom, description, image_url)
SELECT 'Décoration', 'Articles artisanaux de la catégorie Décoration.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Décoration');

INSERT INTO categories (nom, description, image_url)
SELECT 'Instruments de musique', 'Articles artisanaux de la catégorie Instruments de musique.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Instruments de musique');

INSERT INTO categories (nom, description, image_url)
SELECT 'Maroquinerie', 'Articles artisanaux de la catégorie Maroquinerie.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Maroquinerie');

INSERT INTO categories (nom, description, image_url)
SELECT 'Mobilier artisanal', 'Articles artisanaux de la catégorie Mobilier artisanal.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Mobilier artisanal');

INSERT INTO categories (nom, description, image_url)
SELECT 'Poterie', 'Articles artisanaux de la catégorie Poterie.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Poterie');

INSERT INTO categories (nom, description, image_url)
SELECT 'Sculpture', 'Articles artisanaux de la catégorie Sculpture.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Sculpture');

INSERT INTO categories (nom, description, image_url)
SELECT 'Textiles', 'Articles artisanaux de la catégorie Textiles.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Textiles');

INSERT INTO categories (nom, description, image_url)
SELECT 'Vannerie', 'Articles artisanaux de la catégorie Vannerie.', ''
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE nom = 'Vannerie');



INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Vannerie' ORDER BY id LIMIT 1), 'Panier tressé du Ghana', 'Panier décoratif et utilitaire réalisé avec des fibres végétales tressées. Chaque pièce apporte une touche chaleureuse à la maison.', 12500, 'https://woolery.com/cdn/shop/files/AMB-G157__88945.jpg?v=1751995011&width=1214', 12, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Panier tressé du Ghana');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Poterie' ORDER BY id LIMIT 1), 'Vase en céramique terracotta', 'Vase en céramique décoré de motifs géométriques inspirés des arts africains. Idéal pour une décoration intérieure.', 18000, 'https://i.etsystatic.com/17482674/r/il/99c8a9/7738307964/il_fullxfull.7738307964_cst6.jpg', 6, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Vase en céramique terracotta');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Sculpture' ORDER BY id LIMIT 1), 'Masque sculpté du Kenya', 'Masque décoratif en bois sculpté à la main, orné de détails gravés. Une pièce de caractère pour votre intérieur.', 22000, 'https://www.label-emmaus.co/media/ext/900x900/d1kvfoyrif6wzg.cloudfront.net/assets/images/56/main/baf03a913a419b92d43dade94a7a095b_da08bf5.jpeg.webp', 5, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Masque sculpté du Kenya');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Bijoux' ORDER BY id LIMIT 1), 'Collier africain en perles', 'Collier artisanal composé de perles multicolores assemblées en motifs circulaires. Un accessoire expressif et élégant.', 9500, 'https://i.etsystatic.com/13804941/c/2250/2250/0/267/il/0df133/6028902175/il_600x600.6028902175_f1pn.jpg', 14, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Collier africain en perles');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Accessoires' ORDER BY id LIMIT 1), 'Chapeau tressé du Ghana', 'Chapeau à large bord tissé à partir de fibres végétales, avec bande décorative aux couleurs chaleureuses.', 14000, 'https://theafricanhomegoods.com/cdn/shop/files/CC7F35A1-6307-4E20-B1C8-C67BAB6C3616_1024x1024.jpg?v=1763119211', 8, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Chapeau tressé du Ghana');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Accessoires' ORDER BY id LIMIT 1), 'Éventail en raphia', 'Éventail tissé à la main avec des fibres naturelles et des motifs colorés. Objet pratique et décoratif.', 6500, 'https://theanimalrescuesite.com/cdn/shop/products/NVC-285948_1.jpg?v=1638909187&width=1946', 10, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Éventail en raphia');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Décoration' ORDER BY id LIMIT 1), 'Bol sculpté en bois', 'Bol en bois travaillé et poli, mettant en valeur les veines naturelles du matériau. Convient à la décoration ou au service.', 11000, 'https://a.1stdibscdn.com/tribal-wooden-bowl-with-carved-design-tuareg-of-west-africa-mid-20th-century-for-sale/1121189/f_160567721568080596898/16056772_master.jpg?width=768', 7, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Bol sculpté en bois');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Décoration' ORDER BY id LIMIT 1), 'Calebasse gravée', 'Calebasse décorative ornée de gravures géométriques traditionnelles. Chaque motif souligne le travail manuel de l''artisan.', 8500, 'https://i.pinimg.com/originals/31/ba/2d/31ba2d8ef5b60a90af6498839df2e194.jpg', 9, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Calebasse gravée');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Instruments de musique' ORDER BY id LIMIT 1), 'Tambour djembé artisanal', 'Instrument de percussion en bois sculpté avec une peau tendue et un laçage traditionnel. Pour la découverte des rythmes.', 42000, 'https://www.afrahouse.com/cdn/shop/products/djembe-drumafrahouseafrican-art-620903.jpg?v=1705243216', 3, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Tambour djembé artisanal');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Bijoux' ORDER BY id LIMIT 1), 'Bracelet tissé de perles', 'Bracelet artisanal composé de petites perles colorées assemblées en bandes décoratives.', 5500, 'https://www.fatishaboutique.com/cdn/shop/files/Photoroom_20250523_181328.jpg?v=1772904123&width=1946', 16, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Bracelet tissé de perles');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Maroquinerie' ORDER BY id LIMIT 1), 'Sac bandoulière en cuir', 'Sac en cuir travaillé à la main, avec couture apparente et motif central inspiré du continent africain.', 28000, 'https://www.sklep.salvatti.pl/img/6351/torba-skorzana-recznie-robiona-etiopia', 4, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Sac bandoulière en cuir');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Textiles' ORDER BY id LIMIT 1), 'Tissu imprimé style kente', 'Étoffe en coton aux motifs géométriques et couleurs vives, idéale pour la confection et la décoration.', 12000, 'https://africanfabs.de/cdn/shop/products/AF-4005_5836a1a6-a2bf-433c-9a51-9b1585d7dd02_1200x1200.jpg?v=1670535101', 20, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Tissu imprimé style kente');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Mobilier artisanal' ORDER BY id LIMIT 1), 'Tabouret sculpté en bois', 'Petit tabouret en bois massif décoré de formes géométriques ajourées. Une pièce artisanale pour la décoration.', 35000, 'https://a.1stdibscdn.com/hand-carved-wooden-african-stool-for-sale-picture-4/f_30443/f_286317621652364303014/IMG_2764_master.jpg?width=768', 2, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Tabouret sculpté en bois');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Bijoux' ORDER BY id LIMIT 1), 'Manchette en laiton gravé', 'Bracelet manchette en laiton recyclé, décoré de gravures géométriques. Une création au style sobre et authentique.', 10500, 'https://www.jjcaprices.com/cdn/shop/files/image_73fc47f1-4559-44d5-8769-38f7b3b5357c_2400x.jpg?v=1684447589', 11, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Manchette en laiton gravé');

INSERT INTO articles (categorie_id, nom, description, prix, image_url, stock, statut)
SELECT (SELECT id FROM categories WHERE nom = 'Maroquinerie' ORDER BY id LIMIT 1), 'Sandales en cuir et perles', 'Sandales en cuir ornées de perles colorées sur les brides, inspirées du travail de bijouterie artisanale est-africain.', 16000, 'https://nyotakazuri.com/cdn/shop/files/WhatsApp_Image_2025-09-19_at_17.16.45_34c74d39.jpg?v=1758614678', 6, 'actif'
WHERE NOT EXISTS (SELECT 1 FROM articles WHERE nom = 'Sandales en cuir et perles');
