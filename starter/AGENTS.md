# Yeni site geliştirici ve AI rehberi

Önce bu dosya, `docs/mimari.md` ve `docs/yeni-site.md` okunur; sonra yalnız görevle ilgili dosyalar incelenir. Bütün vendor kaynaklarını veya bütün projeyi her görevde tarama.

- Bu depo bağımsız kurulumdur. Başka sitelerde değişiklik yapma veya onlardan config, kullanıcı, içerik ve upload kopyalama.
- Önce `git status --short`. Siteye özel tasarım `index.php`, `templates/`, `css/`, `js/`, `assets/` içindedir. Panel çekirdeği tasarımdan bağımsızdır; tema motoru eklenmez.
- `config/site.php`: ad, kurulum profili, DB ve oturum adı. `config/config.runtime.php` Git dışındaki sırlar içindir. `config/panel-fields.php` siteye özgü alan etiketleridir.
- PHP 8.4 + PDO SQLite; Composer/npm gerekmez. `tools/install.php` mevcut DB'yi ezmez. Şema ve temiz seed birlikte güncellenir. Varsayılan yönetici hesabı yoktur.
- `panel/include/sqlite.php` sınırlı SQLite adaptörüdür. SQL değerleri prepared, identifier/sıralama/tablo/alanlar sunucu allowlist'i olmalıdır.
- API `data/itemsCount`, string ID, rol + kaynak + işlem + alan, CSRF, soft-delete ve çeviri sözleşmelerini koru.
- HTML metni/attribute için `panelHtml()` / `e()`, zengin HTML için yalnız `panelRichHtml()`. DB içeriklerini topluca temizleyip tekrar yazma.
- Dosya yöneticisinin auth, CSRF ve kayıt klasörü kapsamını koru; klasör yoksa ortak köke düşme. Uzak URL, quick-upload, aktif belge yükleme ve arşiv açma kapalı kalır.
- Paket kaynaklarını elle yamalama veya editör/dosya yöneticisi değiştirme. Paketler resmî baytları, lisansları ve SHA-256 manifestiyle korunur.
- CKEditor 4.22.1 ücretsiz seridir, LTS değildir. `fullPage:false`, `disallowedContent:'script;style'`, `versionCheck:false` kalır. Bu özellikleri açmak veya GeSHi/samples eklemek yeni güvenlik değerlendirmesi gerektirir.
- CSS/JS `integrity` değerlerini kaldırma. `assetAttributes()` / `scriptAttributes()` gerçek dosya baytı ve içerik sürümü üretir.
- Loopback erişim sınırını veya giriş güvenliğini sessizce kaldırma. Bu başlangıcın yerel parola/CSRF/deneme sınırı/oturum yenileme akışı üretim onayı değildir.
- Değişen PHP dosyalarında lint ve `php tools/verify.php`. Yazma testleri yalnız ayrı SQLite DB/upload/oturumda yapılır. Kullanıcı DB'sine seed/test import etme. Yeni projeye uygun HTTP testlerini ekle; Personal'a özgü demo testlerini kopyalama.
- Git'e storage DB, session, log, kullanıcı upload, sır veya başka deponun `.git` dizinini ekleme.
