<?php
/** Neutral starter data: no portfolio, images, contact messages or administrator account. The installer creates the administrator from supplied credentials after these rows.
 * Tables in foreign-key-safe insertion order; every row has an explicit id. Inserted by tools/install.php into a new, empty database only. */
return [
    'langs' => [
        ['id' => 1, 'name' => 'Türkçe', 'slug' => 'tr', 'iso_code' => 'tr-TR', 'sort_order' => 10],
        ['id' => 2, 'name' => 'English', 'slug' => 'en', 'iso_code' => 'en-GB', 'sort_order' => 20],
    ],
    'usertypes' => [
        ['id' => 1, 'name' => 'Yönetici', 'slug' => 'admin'],
        ['id' => 2, 'name' => 'Editör', 'slug' => 'user'],
    ],
    'pagetypes' => [
        ['id' => 1, 'name' => 'Standart sayfa', 'template' => 'default'],
    ],
    'menutypes' => [
        ['id' => 1, 'name' => 'Ana menü'],
    ],
    'slidertypes' => [
        ['id' => 1, 'name' => 'Genel'],
    ],
    'templates' => [
        ['id' => 1, 'name' => 'Standart içerik', 'code' => 'default', 'sort_order' => 10],
        ['id' => 2, 'name' => 'Görsel slider', 'code' => 'slider', 'sort_order' => 20],
        ['id' => 3, 'name' => 'Galeri', 'code' => 'masonry', 'sort_order' => 30],
        ['id' => 4, 'name' => 'Kategorili içerik', 'code' => 'category', 'sort_order' => 40],
    ],
    // Contact inbox type: app/contact.php stores messages as datas.type_id = 6.
    'datatypes' => [
        ['id' => 6, 'name' => 'İletişim mesajları', 'slug' => 'messages', 'sort_order' => 60],
    ],
    'pages' => [
        ['id' => 1, 'name' => 'Ana sayfa', 'slug' => 'home', 'type_id' => 1, 'sort_order' => 10],
    ],
    'page_lang' => [
        ['id' => 1, 'row_id' => 1, 'lang_id' => 1, 'title' => 'Yeni siteniz hazır', 'content' => '<p>İlk sayfanızın başlığını ve içeriğini yönetim panelinden düzenleyebilirsiniz.</p>'],
    ],
    'menus' => [
        ['id' => 1, 'name' => 'Ana sayfa', 'type_id' => 1, 'link' => '/', 'page_id' => 1, 'sort_order' => 10],
    ],
    'variables' => [
        ['id' => 1, 'name' => 'site_name', 'sort_order' => 10],
        ['id' => 2, 'name' => 'footer_text', 'sort_order' => 20],
    ],
    'variable_lang' => [
        ['id' => 1, 'row_id' => 1, 'lang_id' => 1, 'name' => 'Yeni Site'],
        ['id' => 2, 'row_id' => 2, 'lang_id' => 1, 'name' => ''],
    ],
];
