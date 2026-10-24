# CMS referans deposu ve Personal örneği

- Bu depo bağımsız bir PHP 8.4 + SQLite portföy demosudur. Başka projelerde değişiklik yapma; başka sitelerden içerik/config/upload/kullanıcı taşıma.
- Yeniden kullanımda önce `docs/mimari.md` ve `docs/yeni-site.md`. Personal bu repoda çalışan örnek olarak kalır. Yeni proje için `tools/create-site.php`; çalışma DB/oturum/upload/sır ve demo seed'ini kopyalama. Yeni hedefte `starter/AGENTS.md` rehberi kullanılır.
- Önce `git status --short`. Çalışma kökü bu klasördür; çalışma DB'si `storage/personal.sqlite`, oturumlar ve loglar Git dışındadır.
- Composer/npm gerekmez. Mevcut panel ve resmî paketler korunur; vendor kaynaklarını elle yamalama veya editör/filemanager değiştirme.
- `config/site.php` kurulum adı/profil/DB/session ayarlarını tutar; kaynak demo cookie ve DB yolu korunur. `database/seed-base.sql` temiz metadata/ana sayfa içerir, kullanıcı içermez; starter yöneticisi kurulumda çevreden verilen parola hash'iyle oluşturulur.
- `database/schema.sql` CREATE kodlarını, `database/seed.sql` yalnız kurgusal başlangıç verilerini içerir. İkisini yapılan şema değişikliğiyle birlikte güncelle. Kurulum mevcut DB'yi silmez/ezmez; canlı veriye seed import etme.
- PDO SQLite adaptörü `panel/include/sqlite.php` içindedir. Prepared değerleri koru; identifier ve tablo/alan allowlist kontrollerini kaldırma. Adaptör sadece panelin mevcut sorgu sözleşmesini kapsar.
- HTTP API `data/itemsCount`, string ID, soft-delete, çeviri ve rol/işlem/alan/CSRF sözleşmelerini korur. SQLite `PRAGMA table_info` kolon tip/default bilgileri form üretiminde kullanılır.
- Tema siteye ait `templates/pages` ve `templates/partials` altında manuel PHP'dir; yeni tema motoru kurma. Kuruluma özgü alanlar `config/panel-fields.php` içindedir. Zengin HTML yalnız `panelRichHtml()` ile sunulur; diğer bağlamlar `panelHtml()` / `e()` kullanır.
- Temanın resmî `css/styles.css`, `js/scripts.js`, profil görseli ve Bootstrap/font dosyaları değiştirilmemiştir. Kaynak/hash manifestleri `docs/theme-*.json`. Yeni CSS `css/site.css` içindedir. Asset SRI + içerik sürümü `assetAttributes`/`scriptAttributes` tarafından aynı baytlardan üretilir; bunları kaldırma.
- Panel CKEditor 4.22.1 standard paketidir, desteklenen LTS değildir. `fullPage:false`, `disallowedContent:'script;style'`, `versionCheck:false` kalır. Samples/GeSHi dağıtılmaz/etkinleştirilmez. Bu özellikleri açmak yeni güvenlik değerlendirmesi gerektirir.
- Filemanager ortak auth/CSRF + `RF.subfolder` kayıt kapsamını korur. Klasör yoksa ortak köke dönmez; uzak URL, arşiv, quick-upload, aktif belge yazma kapalıdır. jQuery/UI yerel dosyalarının SRI değerlerini koru.
- Demo yalnız gerçek loopback `REMOTE_ADDR` kabul eder; local giriş CAPTCHA olmadan hash'li parola, CSRF, rate limit ve session ID yenilemesi kullanır. İnternete açmak için bu korumayı sessizce kaldırma; üretim kurulumunu ayrıca tasarla. Test veya kullanıcı için auth bypass endpoint'i ekleme.
- İletişim formu mesajları yerel DB'ye kaydeder; mail göndermez. `datas` tip 6 inbox içindir, mesajlar düz metin sunulur. IP sınırı 5 / 15 dakika.
- `router.php` ve Apache `.htaccess` config/database/storage/tools/templates/tests ve vendor örneklerine HTTP erişimini engeller. PHP dahili sunucusunda router zorunludur. Nginx kurulumunda eşdeğer kurallar gereklidir.
- Değişen PHP dosyalarında lint, `python tests/http-workflows.py` ve `php tools/verify.php` çalıştır. Yazma testleri yalnız disposable SQLite üzerinde yapılır. Gereksiz tek seferlik test dosyaları ekleme.
- Demo varsayılan ön yüz dili Türkçedir; İngilizce panel çevirileri korunur, ayrı ziyaretçi dili rotası yoktur. Genel CMS template seçenekleri Personal'a otomatik yeni görünüm sağlamaz; ön yüz eşlemesi README'dedir.
