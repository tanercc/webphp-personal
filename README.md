# Personal CMS — SQLite demo

Start Bootstrap Personal 1.0.1 temasının mevcut PHP CMS ile çalışan bağımsız demosu. Kaynak sitenin içeriği, görselleri, kullanıcıları, veritabanı kayıtları ve yerel config dosyaları taşınmaz. Profil ve örnek çalışmalar kurgusaldır; profil fotoğrafı Personal temasının kendi dağıtımındandır.

## Çalıştırma

PHP 8.4 ve `pdo_sqlite`, `mbstring`, `dom`, `fileinfo`, `gd` eklentileri gerekir. Composer veya npm kurulumu gerekmez.

```powershell
cd webphp-personal
php tools/install.php
php -S 127.0.0.1:8876 -t . router.php
```

Veritabanı zaten oluşturulmuşsa `install.php` mevcut dosyanın üzerine yazmayı reddeder. Çalışan demoda ikinci kez kurulum yapmanız gerekmez. `tools/serve.ps1` de sunucuyu başlatır.

- Site: http://127.0.0.1:8876/
- Panel: http://127.0.0.1:8876/panel/
- Demo kullanıcı: `admin@personal.test`
- Demo parola: `PersonalDemo!2026`

Bu hesap yalnız yerel demo içindir. Uygulama gerçek `REMOTE_ADDR` loopback değilse isteği reddeder. Girişte parola hash'i, CSRF, deneme sınırı ve oturum yenileme korunur; bu yerel demoda Google CAPTCHA kullanılmaz. İnternete dağıtım ayrı kimlik doğrulama/kurulum değerlendirmesi gerektirir. Sunucu `127.0.0.1` üzerinde başlatılmalıdır.

## CREATE ve seed SQL

- `database/schema.sql`: 28 tablo, indeksler, çeviri unique anahtarları ve template foreign key ilişkileri.
- `database/seed.sql`: yeni portföy sayfaları, menü, site değişkenleri, proje/özgeçmiş verileri ve yalnız demoya ait kullanıcı.
- `tools/install.php`: SQL dosyalarını geçici SQLite dosyasında uygular; integrity/foreign-key kontrolünden sonra yayınlar. Mevcut DB'yi sıfırlamaz.
- `tools/seed-media.php`: temanın profil görselini ve yeni örnek proje görsellerini kayıt klasörlerine kopyalar; mevcut dosyaları ezmez.
- `storage/personal.sqlite`: çalışan veritabanı; Git dışındadır ve HTTP'den erişilemez.

SQL dosyaları standart SQLite 3 SQL'idir; herhangi bir SQLite istemcisinde boş veritabanına önce schema, ardından seed uygulanabilir. Medya için `php tools/seed-media.php` çalıştırın. Yedek almak için sunucuyu durdurup SQLite dosyasını ve `uploads/` dizinini birlikte kopyalayın.

## Panelden ne düzenlenir?

| Alan | Panel yeri | Kullanılan veri |
|---|---|---|
| Ana başlık ve giriş satırı | Sayfalar → Ana sayfa → Türkçe | `page_lang.name/title`; rozet ve görsel Genel sekmesinde |
| Hakkımda, iletişim, gizlilik ve koşullar | Sayfalar → ilgili sayfa → Türkçe | `page_lang.content`, HTML Purifier ile sunulur |
| Projeler | Veriler → Projeler | Genel ad/kısa bilgi/görsel; Türkçe sekmesinde detay yazısı |
| Deneyim ve eğitim | Veriler → ilgili tip | Dönem, kurum, konum; Türkçe sekmesinde açıklama |
| Yetenek ve diller | Veriler → ilgili tip | Genel ad; dil çevirisi varsa adı geçersiz kılar |
| Marka, sosyal bağlantılar, CTA ve alt bilgi | Ayarlar → Site değişkenleri | `variables` + Türkçe `variable_lang` |
| Gezinme | Menüler → Menü listesi | `menus.link/name/sort_order` |
| Gelen mesajlar | Panel başlangıç ekranı → Mesajlar | Yerel forma ait kayıtlar; HTML olarak çalıştırılmaz |

Türkçe çeviri değerleri doluysa genel değerleri geçersiz kılar. Yeni veri kaydını önce Genel sekmesinde kaydedin; sonra Türkçe içerik ve kayıt kapsamlı dosya seçimi açılır. Siteye özgü ek alan etiketleri `config/panel-fields.php` içindedir; panelde marka kodu tutulmaz.

## Yapı

- `panel/`: mevcut AdminLTE/jsGrid/CKEditor/Filemanager paneli ve rol/CSRF/alan izinleri.
- `panel/include/sqlite.php`: PDO SQLite bağlantısı ve panelin kullandığı sınırlı sonuç/statement adaptörü. Genel bir MySQL emülatörü değildir; migration/dump/Adminer taşınmaz.
- `app/`: halka açık sorgular, güvenli çıktılar ve iletişim işlemi.
- `templates/pages/`: ana sayfa, özgeçmiş, projeler, proje detayı, iletişim, metin ve 404.
- `templates/partials/`: head, nav, hero, proje/özgeçmiş kartları, CTA ve footer.
- `config/`: bağımsız kurulum ve alan etiketleri.

Yeni tema/modül motoru eklenmedi. Personal tasarımı PHP parçalarıyla elle kurulur; CMS kayıtlarını okur. Paneldeki genel slider/kategori/şablon yönetimi korunur; Personal ön yüzü yalnız yukarıdaki içerik eşlemesini kullanır. Ön yüz varsayılan Türkçedir; panelin İngilizce çeviri kayıtları/API'si korunur, ayrı İngilizce ziyaretçi rotası bu demo kapsamında değildir.

## İletişim ve medya

İletişim formu sunucu doğrulaması, CSRF, honeypot ve IP başına 15 dakikada 5 mesaj sınırı uygular. Mesajlar SQLite'a yazılır; e-posta veya ücretli SB Forms servisine gönderilmez. Kayıt bazlı Filemanager kapsamı korunur: yalnız düzenlenen kaydın dosyaları görülür. Quick-upload, uzak URL yükleme, aktif belge yükleme ve arşiv açma kapalıdır.

## ServBay

Yeni PHP site belgesinin kökünü bu klasöre ayarlayıp PHP 8.4 seçin; örneğin `webphp-personal.test` alan adı kullanılabilir. Apache'de `mod_rewrite`, `mod_headers` ve `.htaccess` desteği gerekir. Nginx kullanılıyorsa config/database/storage/tools/templates erişim engelleri ve panel/API rewrite kuralları ayrıca eşdeğer şekilde tanımlanmalıdır. Mevcut başka sitelerin ServBay ayarları değiştirilmez. Yerel hazır önizleme yukarıdaki PHP sunucusundadır.

## Doğrulama

```powershell
python tests/http-workflows.py
php tools/verify.php
```

HTTP süiti ayrı SQLite dosyası, oturum ve sunucuda çalışır; demo içeriklerini değiştirmez. Kurulumun üzerine yazmayı reddetmesi, gerçek login/logout, liste/form/CRUD/çeviri, ek alanların ön yüzde görünmesi, iletişim, rol/CSRF, oran sınırı, SRI ve özel dosya erişim engellerini doğrular. Tarayıcıda masaüstü tema, 375px mobil menü/projeler, editör ve kayıt kapsamlı görsel seçimi kontrol edildi. Test sunucusu Apache `.htaccess` kurallarını uygulamaz; `router.php` eşdeğer engeller sağlar.

Tema [Start Bootstrap Personal](https://github.com/startbootstrap/startbootstrap-personal), MIT: `LICENSE-THEME`. Dağıtılan CSS/JS/profil görseli değiştirilmedi; kaynak/özetler `docs/theme-source.json` ve `docs/theme-assets.json` içindedir. Panel paketleri kendi lisanslarını ve `panel/plugins/DEPENDENCIES.json` manifestini korur; vendor kaynakları yamalanmadı. CSS/JS SRI değerleri gerçek dosya baytlarından ve içerik sürümünden üretilir. Google fontları ve Filemanager'ın bazı alt bağımlılıkları hâlâ dış kaynak kullanır; tamamen çevrimdışı dağıtım iddiası yoktur. CKEditor ücretsiz 4.22.1'in kapalı özelliklere dayanan güvenlik sınırları `AGENTS.md` içindedir.
