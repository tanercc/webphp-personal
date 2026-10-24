-- Neutral starter data. No portfolio, images, contact messages or administrator password.
-- The installer creates the administrator using supplied credentials after importing this file.
BEGIN;
INSERT INTO langs (id,name,slug,iso_code,sort_order) VALUES
(1,'Türkçe','tr','tr-TR',10),(2,'English','en','en-GB',20);
INSERT INTO usertypes (id,name,slug) VALUES (1,'Yönetici','admin'),(2,'Editör','user');
INSERT INTO pagetypes (id,name,template) VALUES (1,'Standart sayfa','default');
INSERT INTO menutypes (id,name) VALUES (1,'Ana menü');
INSERT INTO slidertypes (id,name) VALUES (1,'Genel');
INSERT INTO templates (id,name,code,sort_order) VALUES
(1,'Standart içerik','default',10),(2,'Görsel slider','slider',20),
(3,'Galeri','masonry',30),(4,'Kategorili içerik','category',40);
INSERT INTO pages (id,name,slug,type_id,sort_order) VALUES (1,'Ana sayfa','home',1,10);
INSERT INTO page_lang (row_id,lang_id,title,content) VALUES
(1,1,'Yeni siteniz hazır','<p>İlk sayfanızın başlığını ve içeriğini yönetim panelinden düzenleyebilirsiniz.</p>');
INSERT INTO menus (name,type_id,link,page_id,sort_order) VALUES ('Ana sayfa',1,'/',1,10);
INSERT INTO variables (id,name,sort_order) VALUES (1,'site_name',10),(2,'footer_text',20);
INSERT INTO variable_lang (row_id,lang_id,name) VALUES (1,1,'Yeni Site'),(2,1,'');
COMMIT;
