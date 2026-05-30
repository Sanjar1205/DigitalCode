# 📦 CodeAcademy — O'rnatish ko'rsatmasi

Bu hujjatda **CodeAcademy** loyihasini lokal kompyuteringizga o'rnatish bo'yicha to'liq ko'rsatma berilgan.

## 1️⃣ Talablar

Quyidagi dasturiy ta'minot kerak:

- **PHP 8.0 yoki yuqori** (PDO, mbstring, json, curl extensionlari yoqilgan bo'lishi kerak)
- **MySQL 8.0+** (yoki MariaDB 10.5+)
- **Apache** yoki **Nginx** (yoki PHP-ning built-in serveri)
- **Composer** (ixtiyoriy, hozirgi versiya hech qanday composer dependency talab qilmaydi)

### Tavsiya qilinadigan paketlar:
- **XAMPP** (Windows): https://www.apachefriends.org/
- **MAMP** (macOS): https://www.mamp.info/
- **LAMP** (Linux): apt-get install apache2 mysql-server php php-mysql

## 2️⃣ Loyihani ko'chirish

### Variant A: ZIP faylni chiqarish
1. ZIP faylni yuklab oling
2. Uni quyidagi joyga chiqaring:
   - **XAMPP**: `C:\xampp\htdocs\codeacademy\`
   - **MAMP**: `/Applications/MAMP/htdocs/codeacademy/`
   - **Linux**: `/var/www/html/codeacademy/`

### Variant B: Git orqali
```bash
cd /var/www/html
git clone <repository_url> codeacademy
```

## 3️⃣ Database o'rnatish

1. **MySQL ni ishga tushiring** (XAMPP/MAMP control paneldan)

2. **phpMyAdmin** ga kiring: http://localhost/phpmyadmin

3. **Yangi database yarating**:
   - Nomi: `codeacademy`
   - Kodlash (collation): `utf8mb4_unicode_ci`

4. **Import** tab ga o'ting va `database/codeacademy.sql` faylni tanlang

5. **Go** tugmasini bosing

> Yoki terminal orqali:
> ```bash
> mysql -u root -p
> CREATE DATABASE codeacademy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> USE codeacademy;
> SOURCE /path/to/codeacademy/database/codeacademy.sql;
> ```

## 4️⃣ ✅ Demo foydalanuvchilar (parollar tayyor)

SQL faylida barcha demo foydalanuvchilarning parollari **real bcrypt hash** bilan saqlangan. Hech qanday qo'shimcha sozlash kerak emas.

### Demo akkauntlar (barcha parol bir xil: `password123`)

| Login | Parol | Rol |
|-------|-------|-----|
| `admin` | `password123` | 👑 Administrator |
| `teacher1` | `password123` | 👨‍🏫 O'qituvchi (Aliyev Akmal) |
| `teacher2` | `password123` | 👨‍🏫 O'qituvchi (Karimov Doniyor) |
| `student1` | `password123` | 🎓 Talaba (Toshmatov Sardor) |
| `student2` | `password123` | 🎓 Talaba (Yusupova Madina) |
| `student3` | `password123` | 🎓 Talaba (Rahimov Bekzod) |

### Parolni almashtirish (production uchun)

Production muhitda demo parollarni albatta almashtiring:

```bash
# Yangi hash yaratish
php -r "echo password_hash('YANGI_PAROL', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
```

Keyin SQL bilan yangilang:

```sql
USE codeacademy;
UPDATE users SET password = '$2y$12$YANGI_HASH' WHERE username = 'admin';
```

Yoki kabinetga kirib, profil sozlamalaridan parolni o'zgartiring.

### Tekshirish

Login sahifasiga kiring: `http://localhost/codeacademy/`
- Login: `admin`, Parol: `password123`

Agar kira olsangiz — barcha tayyor! 🎉

## 5️⃣ Konfiguratsiya

### `includes/config.php` faylini sozlang:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'codeacademy');
define('DB_USER', 'root');           // O'z DB userni qo'ying
define('DB_PASS', '');               // O'z DB parolingizni qo'ying

define('SITE_URL', 'http://localhost/codeacademy');  // O'z URL ni qo'ying
```

### `assets/uploads/` papkasiga yozish ruxsatini bering:

**Linux/macOS:**
```bash
chmod -R 755 assets/uploads/
chown -R www-data:www-data assets/uploads/  # Apache uchun
```

**Windows (XAMPP)**: avtomatik ishlaydi.

## 6️⃣ Web serverni ishga tushiring

### XAMPP / MAMP:
- Control panelni oching
- Apache va MySQL ni Start qiling

### PHP built-in server (oddiy test uchun):
```bash
cd codeacademy
php -S localhost:8000
```

## 7️⃣ Brauzerda oching

Quyidagi URL ga kiring:
```
http://localhost/codeacademy
```

Login sahifasi ochilishi kerak. Demo akkauntlardan biri bilan kiring:

| Login | Parol | Roli |
|-------|-------|------|
| `admin` | `admin123` | Administrator |
| `teacher1` | `teacher123` | O'qituvchi |
| `student1` | `student123` | Talaba |

## 8️⃣ API Kalitlarini sozlang (ixtiyoriy)

Kod editor va AI yordamchi to'liq ishlashi uchun API kalitlar kerak.

### A) Judge0 API (kod kompilyatori)

1. https://rapidapi.com/judge0-official/api/judge0-ce ga kiring
2. **Subscribe to Test** tugmasini bosing (BEPUL plan: 50 so'rov/kun)
3. **X-RapidAPI-Key** ni nusxa qiling
4. CodeAcademy ga **admin** sifatida kiring
5. **Sozlamalar → API kalitlar** ga o'ting
6. **Judge0 API Key** maydoniga kalitni qo'ying va saqlang

### B) Claude API (AI yordamchi)

1. https://console.anthropic.com ga kiring va ro'yxatdan o'ting
2. **API Keys** bo'limida yangi kalit yarating
3. CodeAcademy → **Sozlamalar → API kalitlar** ga o'ting
4. **AI Provider** ni `Claude 3.5 Sonnet` qilib tanlang
5. **Claude API Key** maydoniga kalitni qo'ying va saqlang

### C) OpenAI API (alternativa)

1. https://platform.openai.com ga kiring
2. **API Keys → Create new secret key** ga bosing
3. CodeAcademy → **Sozlamalar → API kalitlar** ga o'ting
4. **AI Provider** ni `GPT-4o` yoki `GPT-4o mini` qilib tanlang
5. **OpenAI API Key** maydoniga kalitni qo'ying va saqlang

## 9️⃣ Birinchi mavzuni yarating

1. **Admin** sifatida kiring → **Fanlar** → mavjud fanlarni ko'rasiz
2. **Tayinlashlar** → biror fanga o'qituvchi va talabalarni biriktiring
3. **O'qituvchi** sifatida (`teacher1`) kiring
4. **Mening fanlarim** → fanni tanlang → **Mavzu qo'shish**
5. Mavzu, video URL, test savollari va amaliy masala qo'shing
6. **Talaba** sifatida (`student1`) kiring va o'qishni boshlang!

## 🐛 Tez-tez uchraydigan muammolar

### Muammo: "Could not find driver" (PDO xato)
**Yechim**: `php.ini` faylida `extension=pdo_mysql` ni yoqing va Apache ni qayta ishga tushiring.

### Muammo: "Headers already sent"
**Yechim**: PHP fayllarining boshida bo'sh joy bo'lmasligi kerak. UTF-8 BOM ni o'chiring.

### Muammo: Login bo'lmadi (parol noto'g'ri)
**Yechim**: 4-bosqichni qaytadan o'qing — parol hashlarini real bcrypt ga almashtiring.

### Muammo: Monaco editor yuklanmaydi
**Yechim**: Internet ulanishini tekshiring (CDN dan yuklanadi).

### Muammo: Kod editor "Judge0 API kalit sozlanmagan" deydi
**Yechim**: 8-bosqimda Judge0 API kalitni sozlang.

### Muammo: AI chat ishlamayapti
**Yechim**: Admin sozlamalarida Claude yoki OpenAI API kalit kiritilganligini tekshiring.

### Muammo: Video kuzatish ishlamayapti
**Yechim**: Video URL `https://www.youtube.com/embed/VIDEO_ID` formatida bo'lishi kerak (oddiy `watch?v=` emas).

### Muammo: 500 Internal Server Error
**Yechim**: Apache error log ni tekshiring (`logs/error.log`). Odatda config faylda xatolik.

## 🔄 Yangilash

Yangi versiyaga o'tish uchun:

```bash
# Backup oling!
mysqldump -u root -p codeacademy > backup_$(date +%Y%m%d).sql

# Yangi fayllarni ustiga yozing (config.php ni saqlang!)

# Migratsiya (agar mavjud bo'lsa)
mysql -u root -p codeacademy < database/migrations.sql
```

## 📞 Yordam

Savollar yoki muammolar uchun:
- GitHub Issues ga yozing
- Email: support@codeacademy.local

---

**Omad tilaymiz! 🚀**
