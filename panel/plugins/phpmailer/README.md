# PHPMailer 7.1.1 — hazır paket

Kaynak: https://github.com/PHPMailer/PHPMailer/releases/tag/v7.1.1 (release commit `1bc1716`). Lisans: LGPL-2.1; LICENSE korunmuştur.

Resmî `src/` ve `language/` dosyaları değiştirilmeden alınmıştır. Kaynak arşivin SHA-256 özeti: `c45a1ad58cb835921827b9ec2e86a55f5abdbdb6b17345fd1ab55a7dfd31119f`.

Composer kurulumu gerekmez. `autoload.php` yükleyip `PHPMailer\PHPMailer\PHPMailer` sınıfını kullanın. Eski `PHPMailerAutoload.php`, `class.phpmailer.php`, `class.smtp.php`, `class.pop3.php` yolları yerel bootstrap'a yönlenir; global PHPMailer/SMTP/POP3/phpmailerException adları alias olarak sağlanır. Bu, tüm PHPMailer 5 API'si için uyumluluk garantisi değildir. OAuth için ek üçüncü taraf paketler bu dağıtıma dahil değildir.

Uygulama kodunda aktif PHPMailer çağrısı bulunmadı. Eski örnekler, OAuth yardımcı endpoint'leri ve PHP 8'de derlenmeyen extras/htmlfilter kaldırıldı. Güncellemede resmî kaynakları değiştirmeden aktarın; yerel bootstrap ve alias dosyalarını koruyun. `php tests/phpmailer-compatibility.php` e-posta göndermeden UTF-8 MIME ve yükleme yollarını doğrular. Gerçek SMTP teslimatı test edilmedi.

README.upstream.md belgesinde yaln?z sat?r sonu bo?lu?u normalize edildi; ?al??ma zaman? kaynaklar?na yama uygulanmad?.
