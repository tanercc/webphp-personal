# Bu altyapıyla yeni site oluşturma

Bu depo iki işi birlikte taşır: çalışan **Personal örneği** ve aynı CMS'ten temiz proje üretmek için **starter**. Örnek temayı taşımak zorunlu değildir. Yeni sitede bütün kaynak kodunu veya vendor paketlerini yeniden okumak gerekmez; aşağıdaki giriş noktalarıyla başlanır.

## Hızlı başlangıç

Referans deponun kökünde, var olmayan bir kardeş klasör seçin:

```powershell
php tools/create-site.php --target=../yeni-site --name="Yeni Firma"
cd ../yeni-site
```

Komut Git'te izlenen çekirdek dosyaların çalışma kopyasını alır. Git gereklidir; yeni sitenin PHP çalışma zamanında Git gerekmez. Yeni eklediğiniz çekirdek dosyayı önce Git'e alın. Hedef klasör zaten varsa, boş olsa bile üzerine yazılmaz. Kaynak repo içi hedef reddedilir. Kopya kendi `.git` dizinini veya commit geçmişini devralmaz; depo başlatma işlemi aşağıdadır.

Taşınanlar: panel/paketler, SQLite adaptörü, şema/temiz seed, güvenlik yönlendirmeleri, sade PHP ön yüzü, kurulum/doğrulama araçları ve geliştirici rehberi. Taşınmayanlar: Personal tasarımı/görselleri/portföyü, demo seed'i/hesabı, çalışma DB'si, session/log/upload, özel config ve kaynak Git geçmişi. Kaynak `config/config.runtime.php` izleniyor olsa bile kopya listesinde yoktur.

Parolayı komut geçmişine yazmadan kurun:

```powershell
$secret = Read-Host 'Yönetici parolası (12-72 byte)' -AsSecureString
$env:PANEL_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
php tools/install.php --admin-email=admin@example.test
Remove-Item Env:\PANEL_ADMIN_PASSWORD
Remove-Variable secret
php tools/verify.php
php -S 127.0.0.1:8877 -t . router.php
```

E-posta adresini değiştirin; 8877 örnek boş porttur. Başarısız komutun hata mesajını çözmeden sonraki adıma geçmeyin. Yönetici parolası SQL'e, belgeye veya komut argümanına kaydedilmez; veritabanında yalnız hash bulunur. Varsayılan/demo kullanıcı oluşturulmaz. Site kökte, panel `/panel/` adresindedir.

```powershell
git init
git add .
git commit -m "Initialize standalone CMS site"
```

## Yeni tasarımı nereye yazacağız?

- `index.php`: URL → izinli sayfa eşlemesi. DB'deki değerleri serbest include yoluna dönüştürmeyin.
- `templates/`: yeni tasarımın manuel PHP sayfa ve bölüm dosyaları. Ortak head/nav/footer burada ayrılır.
- `css/`, `js/`, `assets/`: yalnız yeni sitenin dosyaları. CSS/JS linklerinde SRI ve içerik sürümü üreten mevcut yardımcıları kullanın.
- `config/site.php`: site adı, kurulum profili, DB yolu ve bağımsız session adı.
- `config/panel-fields.php`: ihtiyaç varsa veri tipinin özel kolon etiketleri.
- `database/schema.sql`: tablo/indeks yapısı; `database/seed-base.sql`: temiz başlangıç kayıtları. Site içerik seed'i eklenecekse ayrı ve sır içermeyen dosya kullanın.
- `panel/`: rol/CRUD/form/medya çekirdeği. Sırf yeni tasarım için burada kod değiştirmeyin.

Yeni hedefte Personal'a ait `templates/pages/`, CSS, görsel ve ücretli form entegrasyonu bulunmaz. Sade `templates/page.php` değiştirilerek yeni tasarım oluşturulur. Yeni tema motoru kurulmaz.

## İçeriği mevcut tablolara bağlama

| İhtiyaç | Mevcut yapı | Dikkat |
|---|---|---|
| Kurumsal sayfa | `pages` + `page_lang` | Genel ayarlar ile dil içeriği ayrı; aktif ve silinmemiş kayıtları sunun. |
| Hizmet/proje/ekip gibi tekrar eden kartlar | `datatypes` + `datas` + `data_lang` | Veri tipini kendi seed'inizde tanımlayın; Personal'ın sayısal tip ID'lerini kopyalamayın. |
| Hiyerarşi/kategoriler | `cats`, `datas.cat_id` | Kategori varsayılanı `cats.template_id`, kayıt istisnası `datas.template_id`. |
| Menü | `menutypes` + `menus` + `menu_lang` | İzinli URL şemaları; sıralama, aktiflik ve soft-delete korunur. |
| Site adı, iletişim ve alt bilgi | `variables` + `variable_lang` | Anahtarlar sabit, değerler panelden; metinleri uygun bağlamda kodlayın. |
| Medya | `uploads/files/{kaynak}/{id}` | Yeni kaydı önce kaydedin; dosya seçici yalnız o kaydın klasörünü açar. |

`sitePage($slug)`, `siteRecords($typeSlug)`, `setting($key)` ve `siteRows($sql,$params)` yardımcılarının yeri `app/bootstrap.php`. Mevcut yardımcıların varsayılan ziyaretçi dili Türkçe (`lang_id=1`); farklı dil rotaları ayrıca tanımlanır. Çevirinin boş alanları genel değere düşer. HTML metin/attribute `e()`, zengin HTML `panelRichHtml()` ile sunulur.

## AI ile yeni site yaparken

Yeni projedeki ajana şu kapsamı vermek yeterli bir başlangıçtır:

> Önce AGENTS.md, docs/mimari.md ve docs/yeni-site.md oku. Bu repoda [site türü] için [tasarım referansı] ile bir site oluştur. Mevcut CMS/SQLite/medya güvenliğini koru. Siteye özel HTML/PHP/CSS/JS'i ön yüzde tut. İçerikleri pages/datas/variables ve çevirilerden besle. Gereken CREATE ve seed SQL'i kaydet. Yalnız ilgili dosyaları incele; vendorları değiştirme ve başka repoya yazma.

Bu rehber uygulama ayrıntılarını hiç incelememe sözü değildir. Ajan, dokunduğu davranışın çağrılarını ve testlerini kontrol eder; veritabanı/rol/medya değişirse ilgili çekirdek dosyaları da okur. Yeni projedeki kararları kendi AGENTS/README dosyasına kaydeder.

## Referans Personal örneği

Bu deponun mevcut `index.php`, `templates/pages/`, `templates/partials/`, `css/`, `assets/` ve `database/seed.sql` dosyaları çalışan örnektir; taşınmadı/silinmedi. `php tools/install.php` referans depoda hâlâ demo kurar; mevcut DB'yi ezmez. Aynı kaynakta farklı bir dosyada temiz DB denemek için `PANEL_DB_FILE` ayarlayıp `--profile=starter --admin-email=...` kullanabilirsiniz; mevcut Personal ön yüzü otomatik yeni bir tema seçmez. Yeni site için önerilen yol oluşturma komutudur.

## Güvenlik ve üretim sınırı

Yeni kopya da loopback erişim sınırı, parola/CSRF/rol/alan/medya korumalarıyla gelir. Bu kopyalama işlemi üretim dağıtımı değildir; CAPTCHA, dış erişim, SMTP, sunucu yönlendirmeleri, kalıcı yedekler ve TLS ayrıca değerlendirilir. Sırlar `config/config.runtime.php`, runtime DB/oturum/loglar `storage/` içindedir ve Git/HTTP dışında kalır. Başka sitenin config veya upload dizinini kopyalamayın.
