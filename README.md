# Telegram Bot + Mini App System

Modern Telegram Mini App çözümü.

## Proje Yapısı

- `/bot` - Telegram webhook botu
- `/miniapp` - HTML + Tailwind Mini App frontend
- `/api` - PHP + PDO API uç noktaları
- `/sql` - MySQL başlangıç şeması

## Özellikler

- Telegram Mini App giriş doğrulaması
- Free / VIP üyelik sistemi
- Dinamik servis sorguları
- Yönetici paneli (admin ID ile erişim)
- Güvenli PDO veri tabanı bağlantısı
- Telegram initData hash doğrulaması
- Sorgu geçmişi kayıtları

## Kurulum

1. `sql/init.sql` dosyasını MySQL sunucunuza import edin.
2. `.env.example` dosyasını `.env` olarak kopyalayın ve kendi değerlerinizi girin.
3. `WEBAPP_URL` adresini Mini App dosyanızın yayınlandığı URL ile eşleştirin.
4. `TELEGRAM_BOT_TOKEN` ve `ADMIN_TELEGRAM_ID` değerlerini ayarlayın.
5. PHP sunucusunu `api` ve `bot` dizinleri için çalıştırın.

## Kullanım

- `/bot/webhook.php` Telegram webhook olarak ayarlanmalıdır.
- `/miniapp/index.html` WebApp başlatma sayfası olarak kullanılmalı.
- `/api/auth.php` Telegram initData doğrulaması yapar.
- `/api/services.php` servis listesini döner.
- `/api/query.php` sorgu işlemlerini gerçekleştirir.
- `/api/admin.php` admin işlemlerini yönetir.

## Notlar

- `auth_tokens` tablosu stateless olmayan API talepleri için güvenli token yönetimi sağlar.
- Free kullanıcılar günlük sorgu limiti ile sınırlandırılır.
- VIP kullanıcıların süreleri `membership_expire` alanı ile takip edilir.
