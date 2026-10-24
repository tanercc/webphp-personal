# HTML Purifier — hazır paket

Sürüm: **4.19.1**. Panelle birlikte kopyalanır; Composer veya kurulum komutu gerekmez.

- Resmi kaynak: https://github.com/ezyang/htmlpurifier
- Sürüm: https://github.com/ezyang/htmlpurifier/releases/tag/v4.19.1
- Kaynak commit: `5e0539132d934f936fbae2c88b55d58f9438623f`
- Lisans: **LGPL-2.1-or-later**; `LICENSE` ve `CREDITS` korunmuştur.
- `library/` resmi paketten değiştirilmeden alınmıştır. İlk aktarımda 379 dosyanın hash'i kaynakla karşılaştırıldı.
- `README.upstream.md` resmi paketin açıklamasıdır; oradaki Composer seçeneği bu projede kullanılmaz.

Uygulama `panel/include/rich_html.php` üzerinden `library/HTMLPurifier.auto.php` dosyasını doğrudan yükler. İzinli HTML/CSS/URL politikası paket dosyalarında değil uygulama yardımcısında tanımlanır. Runtime cache yazımı kapalıdır. Bu dizin yalnızca PHP include için kullanılır; HTTP erişimi engellenmelidir.

Güncellemede resmi sürümün `library/`, `LICENSE`, `CREDITS` ve `VERSION` dosyalarını birlikte değiştirin; kaynak commit ve bu notu güncelleyin. `php tests/rich-html-security.php` ve `php tests/rich-html-inventory.php --compare` ile güvenlik ve mevcut içerik uyumluluğunu doğrulayın. Paket PHP kodunu yerel olarak yamalamayın.
