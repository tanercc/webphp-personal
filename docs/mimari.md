# Geliştirici için kısa dosya haritası

## Başlangıç sırası

1. `AGENTS.md`: o deponun kapsamı ve korunacak sözleşmeler.
2. `README.md`, `config/site.php`: hangi örnek/kurulum, DB ve çalıştırma yolu.
3. `database/schema.sql`, ihtiyaca göre ilgili seed satırları.
4. Aşağıdaki tablodan görevle ilgili giriş noktası. Vendor dosyalarını topluca okumayın.

| Görev | Önce okunacak dosya / sembol |
|---|---|
| Ön yüz rotası ve sayfa | `index.php`, `templates/`; örnek Personal'da `templates/pages/` + `partials/` |
| Site verisi | `app/bootstrap.php`: `sitePage`, `siteRecords`, `setting`, `siteRows` |
| Kurulum ve temiz kopya | `tools/install.php`, `tools/create-site.php`, `config/site.php`, `starter/` |
| DB bağlantısı / metadata | `panel/include/sqlite.php`: `PanelSqliteConnection`, `columns`, `execute_query` |
| Eski panel yardımcıları | `panel/include/db_functions.php`: `sqlQuery`, `dbGetTableData`, `dbGetTableFields` |
| API yönlendirme/CSRF | `panel/api/index.php` |
| API izinleri ve alanlar | `panel/api/security.php`: `apiAuthorize`, `apiWritableFields`, `apiValidateFields` |
| CRUD/sayfalama/çeviri | `panel/api/database.php`; `request.php`, `lang.php` |
| Form alanları | `panel/content-*-id.php`, `panel/php/input-*.php`, `config/panel-fields.php` |
| Kullanıcı/rol/oturum | `panel/include/login.php`, `login_security.php`, `permissions.php`, `session.php`, `csrf.php` |
| Kayıt kapsamlı dosya seçimi | `panel/include/media_access.php`, `panel/plugins/filemanager/config/config.php` |
| Güvenli çıktı | `panel/include/output.php`, `rich_html.php`; `e`, `panelHtml`, `panelRichHtml` |
| HTTP erişim sınırları | `.htaccess`, `panel/.htaccess`, `router.php`, `uploads/.htaccess` |
| Paket bütünlüğü | `panel/plugins/DEPENDENCIES.json`, `tools/verify.php` |

## İstek akışları

- Ziyaretçi: router/rewrite → `index.php` → `app/bootstrap.php` → parametreli okuma → izinli PHP şablonu.
- Panel: `panel/index.php` → config/DB/oturum/rol → liste veya kayıt formu.
- API: `panel/api/index.php` → oturum/rol/yöntem/CSRF → tablo/alan/filtre izinleri → SQLite adaptörü → JSON.
- Medya: Filemanager girişleri → ortak oturum/rol/CSRF → `RF.subfolder` kaynak/kayıt sınırı → dosya işlemi.

Panel formu şema kolon tip/default bilgilerini kullanır. SQLite adaptörü mevcut `SHOW COLUMNS` / legacy sonuç arabiriminin yalnız kullanılan bölümünü karşılar; genel MySQL uyumluluk katmanı değildir. Yeni sorgu gerektiğinde SQLite desteğini ve izin listesini birlikte kontrol edin.

## Değişmez sözleşmeler

- Liste JSON'u `data` ve `itemsCount`; kimlik değerleri istemcide string olarak korunur.
- `deleted` soft-delete, `is_active` yayın durumu, `sort_order` sıralamadır.
- Genel kayıt ile `*_lang` çevirileri `row_id/lang_id` üzerinden ayrılır; dolu çeviri alanı genel değeri geçersiz kılar.
- Şablon yolu DB'den alınmaz; kodlar `config/data-templates.php` izin listesinden çözülür. Kayıt → kategori → varsayılan önceliği korunur.
- Medya kökü kurulum ve kayıt bazındadır. Ortak upload kökünü seçiciye açmayın.
- Zengin HTML yalnız çıktı aşamasında `panelRichHtml`; form/DB içeriği topluca değiştirilmez.
- `assetAttributes` ve `scriptAttributes` dosya baytlarından SRI + içerik sürümü üretir.
- Site görünümü manuel PHP parçalarındadır. Panel ortak çekirdeğine firma veya tema davranışı gömmeyin.

## Hangi doğrulama?

- PHP değişikliği: ilgili dosyalarda `php -d error_reporting=32767 -l ...`.
- Panel/API/DB/kurulum değişikliği: bu referans repoda `python tests/http-workflows.py`. Aynı süit demo ve üretilen temiz siteyi ayrı SQLite/oturum/sunucuyla dener.
- Paket/kurulum: `php tools/verify.php`; DB yoksa dosya yaratmadan reddeder.
- Yeni hedef repoda Personal'a özel HTTP süiti kopyalanmaz. Yeni projeye uygun testleri ayrı DB'de yazın; temel paket/SQLite kontrolü hazır gelir.

Eksik bilgiyi gereken dosyadan okuyun. Bütün kaynak ağacını her seferinde taramak veya önceki sohbeti hatırlamaya güvenmek gerekmez.

Doğrulanan referans turu: 101 Personal + 25 üretilen starter HTTP isteği; mevcut hedefe/DB'ye yazmayı reddetme, parolasız kurulumun reddi, yeni yönetici, demo içeriklerinin taşınmaması ve paket özetleri. `panel/include/config.php` sır içermeyen uyumluluk yükleyicisidir; Git'te izlenir. Yalnız private config dosyaları ignore edilir.
