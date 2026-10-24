# Yeni site — PHP CMS + SQLite

Bu klasör boş başlangıç komutuyla üretildi. Örnek portföy, demo hesabı, kullanıcı dosyaları veya başka bir projenin Git geçmişi taşınmadı. PHP 8.4; Composer/npm gerekmez.

Önce `AGENTS.md`, `docs/mimari.md` ve `docs/yeni-site.md` okuyun. Tasarımı `templates/` altında manuel PHP/HTML/CSS ile oluşturun; panel çekirdeğinde siteye özel tasarım kodu yazmayın.

## Kurulum

PowerShell üzerinde parolayı komut geçmişine yazmadan:

```powershell
$secret = Read-Host 'Yönetici parolası (12-72 byte)' -AsSecureString
$env:PANEL_ADMIN_PASSWORD = [System.Net.NetworkCredential]::new('', $secret).Password
php tools/install.php --admin-email=admin@example.test
Remove-Item Env:\PANEL_ADMIN_PASSWORD
Remove-Variable secret
php tools/verify.php
php -S 127.0.0.1:8876 -t . router.php
```

E-posta adresini kendinize göre değiştirin. PHP komutu başarısızsa hata mesajını kontrol edin. Site `http://127.0.0.1:8876/`, panel `/panel/`. Başka bir demo 8876 portunu kullanıyorsa boş bir port seçin.

`config/site.php` site adı, DB yolu ve kurulum profili; `config/panel-fields.php` özel alan etiketleridir. Sırlar için Git dışındaki `config/config.runtime.php` kullanılır. DB `storage/site.sqlite` içindedir; `database/schema.sql` ve `database/seed-base.sql` yeniden kurulabilir kaynaktır. Seed dosyasında sabit yönetici hesabı yoktur. Kurulum var olan DB'yi ezmez.

```powershell
git init
git add .
git commit -m "Initialize standalone CMS site"
```

Bu başlangıç da yalnız loopback erişimine açıktır. Üretime alma; kimlik doğrulama, CAPTCHA, sunucu, yedekleme ve mail ayarları için ayrı değerlendirme gerektirir. Koruma otomatik kaldırılmaz.
