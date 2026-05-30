# 🎓 CodeAcademy — Online Dasturlash O'qitish Platformasi

Bu loyiha to'liq funksional **Learning Management System (LMS)** bo'lib, talabalarga bosqichma-bosqich dasturlashni o'rgatish uchun mo'ljallangan. **3 rolli kabinet** (Admin, O'qituvchi, Talaba), **Monaco kod editor**, **Judge0 kompilyator**, **AI yordamchi** va **mavzular cheklov tizimi** mavjud.

## ✨ Asosiy xususiyatlar

### 🎯 Cheklov tizimi (eng muhim qism)
Talaba mavzularni **KETMA-KETLIKDA** o'rganadi. Keyingi mavzu **QULFLANGAN** bo'ladi va quyidagi 4 shartni bajargandagina ochiladi:
1. ✅ **Mavzu matni** 100% o'qildi (scroll kuzatish)
2. ✅ **Video** 90%+ ko'rildi (YouTube IFrame API)
3. ✅ **Test** 60%+ to'g'ri javob (default, sozlanadigan)
4. ✅ **Amaliy masala** "3" baho yoki yuqori bilan yechildi

### 📊 Bahoyash tizimi
- **90-100%** → Baho **5** (A'lo)
- **70-89%** → Baho **4** (Yaxshi)
- **50-69%** → Baho **3** (Qoniqarli)
- **0-49%** → Baho **2** (Qoniqarsiz)

### 👥 3 rolli kabinetlar

#### 🔴 Admin
- Foydalanuvchilar boshqaruvi (CRUD, parol tiklash, status)
- Fanlar boshqaruvi (6 dasturlash tili)
- O'qituvchi va talabalarni fanlarga biriktirish
- Hisobotlar (Chart.js bilan)
- Tizim sozlamalari (API kalitlar, ranglar, xavfsizlik)
- Faoliyat loglari

#### 🔵 O'qituvchi
- Faqat o'ziga biriktirilgan fanlar
- Mavzular CRUD (HTML editor, video URL)
- Test savollari (Single/Multiple/True-False)
- Amaliy masalalar (test caselar, time/memory limit)
- Talabalar progressini monitoring
- Hisobotlar

#### 🟢 Talaba
- Mening fanlarim (progress bar bilan)
- O'qish sahifasi (cheklov tizimi vizual)
- Test topshirish
- **Monaco kod editor** (VS Code dvigateli)
- Mening baholarim (Chart.js)
- AI yordamchi (Claude/OpenAI)
- Profil

## 🛠 Texnologiyalar

| Texnologiya | Versiya | Maqsad |
|-------------|---------|--------|
| PHP | 8.0+ | Backend (PDO, OOP, MVC) |
| MySQL | 8.0+ | Ma'lumotlar bazasi (InnoDB) |
| Bootstrap | 5.3 | Frontend dizayn |
| Monaco Editor | 0.45.0 | Kod editor (VS Code bazasida) |
| Judge0 API | CE | Kod kompilyatori (RapidAPI) |
| Claude API | 3.5 | AI yordamchi (yoki OpenAI) |
| Chart.js | 4.x | Diagrammalar |
| Font Awesome | 6.4 | Ikonkalar |

## 📁 Loyiha strukturasi

```
codeacademy/
├── admin/              # Admin paneli (7 sahifa)
│   ├── dashboard.php
│   ├── users.php
│   ├── subjects.php
│   ├── assignments.php
│   ├── reports.php
│   ├── settings.php
│   └── logs.php
├── teacher/            # O'qituvchi paneli (7 sahifa)
│   ├── dashboard.php
│   ├── my_subjects.php
│   ├── topics.php
│   ├── questions.php
│   ├── tasks.php
│   ├── monitoring.php
│   ├── student_detail.php
│   └── reports.php
├── student/            # Talaba paneli (7 sahifa)
│   ├── dashboard.php
│   ├── my_subjects.php
│   ├── learn.php       # ⭐ Cheklov tizimi
│   ├── test.php
│   ├── code_editor.php # ⭐ Monaco editor
│   ├── grades.php
│   ├── ai_assistant.php
│   └── profile.php
├── api/                # REST API endpointlar
│   ├── progress_tracker.php  # Scroll/video kuzatish
│   ├── code_executor.php     # Judge0 integratsiya
│   └── ai_chat.php           # AI yordamchi
├── includes/           # Yordamchi fayllar
│   ├── config.php
│   ├── db.php          # PDO Singleton
│   ├── auth.php        # Auth class
│   ├── functions.php   # ⭐ isTopicUnlocked()
│   ├── header.php
│   └── footer.php
├── assets/
│   ├── css/style.css   # Modern UI (CSS variables, dark mode)
│   ├── js/main.js
│   ├── images/
│   └── uploads/
├── database/
│   └── codeacademy.sql # 17 jadval, demo data
├── index.php           # Login
├── logout.php
├── README.md
└── INSTALL.md          # ⭐ O'rnatish ko'rsatmasi
```

## 🚀 O'rnatish

To'liq o'rnatish ko'rsatmasi uchun **[INSTALL.md](INSTALL.md)** faylini ko'ring.

Qisqa qadamlar:
1. Faylllarni `htdocs/codeacademy/` papkasiga ko'chiring
2. MySQL da `codeacademy.sql` ni import qiling
3. `includes/config.php` da DB ma'lumotlarini sozlang
4. **MUHIM**: Demo foydalanuvchilarining parolini yangilang (INSTALL.md ga qarang)
5. Browser da `http://localhost/codeacademy` ga kiring

## 👤 Demo akkauntlar (parolni almashtirish kerak!)

| Login | Roli | Default parol |
|-------|------|---------------|
| `admin` | Administrator | `admin123` |
| `teacher1` | O'qituvchi | `teacher123` |
| `teacher2` | O'qituvchi | `teacher123` |
| `student1` | Talaba | `student123` |
| `student2` | Talaba | `student123` |
| `student3` | Talaba | `student123` |

> ⚠️ **OGOHLANTIRISH**: Yuqoridagi parollar SQL faylda **YOLG'ON hash** bilan saqlangan. INSTALL.md dagi ko'rsatmaga binoan ularni real bcrypt hash ga almashtirishingiz kerak.

## 🔑 API Kalitlar (ixtiyoriy)

Loyiha to'liq ishlashi uchun quyidagi API kalitlar kerak (admin paneldan kiritiladi):

1. **Judge0 API** (kod kompilyator) — [RapidAPI](https://rapidapi.com/judge0-official/api/judge0-ce)
2. **Claude API** (AI yordamchi) — [Anthropic Console](https://console.anthropic.com)
3. **OpenAI API** (alternativa) — [OpenAI Platform](https://platform.openai.com)

Agar API kalitlar bo'lmasa, kod editor va AI chat ishlamaydi, lekin loyihaning qolgan barcha qismlari to'liq ishlaydi.

## 🎨 Dasturlash tillari (qo'llab-quvvatlanadi)

- **C++** (GCC 9.2.0)
- **Java** (OpenJDK 13.0.1)
- **Python** (3.8.1)
- **JavaScript** (Node.js 12.14.0)
- **PHP** (7.4.1)
- **C#** (Mono 6.6.0.161)

## 🔒 Xavfsizlik

- Bcrypt parol hashing (cost: 12)
- CSRF token har bir formada
- Brute-force himoyasi (5 urinishdan keyin 15 daqiqa lock)
- SQL injection oldini olish (PDO prepared statements)
- XSS himoyasi (htmlspecialchars)
- Session timeout (default 1 soat)
- Activity logs

## 📝 Litsenziya

Bu loyiha o'quv maqsadida yaratilgan. Bepul foydalanish va o'zgartirish mumkin.

## 🤝 Hissa qo'shish

Loyiha o'zbek tilidagi dasturchilar uchun open-source. Pull requestlar va bug reportlar xush kelibsiz!

---

**Muallif**: Xayriddin va Claude AI hamkorlikda yaratilgan  
**Yili**: 2026  
**Versiyasi**: 1.0.0
