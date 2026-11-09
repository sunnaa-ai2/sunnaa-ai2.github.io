# Spark صُنّاع AI 2

معرض أعمال متدربي مبادرة **صُنّاع** — مؤسسة سبارك للابتكار والإبداع. لكل متدرب صفحة شخصية يرفع عليها حلول التمارين، والكل يقدر يتصفح ويعلّق ويتفاعل.

> An invite-only gallery where Spark's "Sunna'a AI 2" trainees publish their exercise work. Arabic, RTL, single-page frontend on GitHub Pages with a Laravel 12 + MySQL API.

**الموقع:** https://sunnaa-ai2.github.io/

## المزايا
- صفحة شخصية لكل متدرب (اسم وصورة)، تظهر في الواجهة الرئيسية.
- رفع الأعمال حسب رقم التمرين: حتى 8 ملفات بأي صيغة (صور، PDF، Word، ZIP…) أو رابط، وترتيبها من الأقدم للأحدث.
- إعجاب وعدم إعجاب (عدد عدم الإعجاب يراه صاحب العمل والأدمن فقط) وتعليقات.
- صفحة لكل تمرين بوصف يكتبه الأدمن، وإشعارات، وشارات، وصور مصغرة لملفات PDF.
- الانضمام بدعوة فقط: رابط لمرة واحدة مربوط بإيميل محدد، ينشئه الأدمن ويرسله من Gmail.
- الدخول برابط دائم يصل على الإيميل، بدون كلمات مرور، ويعمل من أي جهاز.
- تعديل وحذف الأعمال، وحذف الحساب بالكامل.
- التصفح متاح للجميع بدون تسجيل.

## التقنيات
| الجزء | المستخدم |
|---|---|
| الواجهة | ملف واحد `index.html` (HTML/CSS/JS بدون خطوات بناء)، على GitHub Pages |
| الحركة | [GSAP](https://gsap.com) 3.15 (ScrollTrigger, SplitText, DrawSVG)، و[Lenis](https://github.com/darkroomengineering/lenis) للسكرول الناعم |
| ملفات PDF | [PDF.js](https://github.com/mozilla/pdf.js) للصور المصغرة |
| الباك اند | Laravel 12 + Sanctum (توكنات API)، في مجلد [`backend/`](backend/README.md) |
| قاعدة البيانات | MySQL / MariaDB |
| الاستضافة | alwaysdata (PHP + MySQL + بريد الإرسال) |
| الاختبارات | PHPUnit على GitHub Actions، على SQLite وMySQL 8 |

## الهوية
هوية "المختبر": خلفية سوداء بشبكة خفيفة، والأحمر `#FF2E3B` إشارة للإجراءات فقط. الخطوط: Alexandria للعناوين، IBM Plex Sans Arabic للنصوص، IBM Plex Mono للتسميات. افتتاحية متحركة 15 ثانية، ثم جولة سكرول تلقائية تتوقف عند أي تفاعل.

## بنية المستودع
```
index.html                 الواجهة كاملة
backend/                   Laravel API
  app/Http/Controllers/    نقاط الـ API
  database/migrations/     جداول MySQL
  tests/Feature/           اختبارات الـ API
  deploy/alwaysdata.sh     تثبيت أو تحديث السيرفر بأمر واحد
.github/workflows/         الاختبارات وملف composer.lock
```

## التشغيل والنشر
تفاصيل الـ API ومتغيرات البيئة وخطوات النشر في [`backend/README.md`](backend/README.md).

تحديث السيرفر بعد أي تعديل:
```bash
cd ~/site && git pull && bash backend/deploy/alwaysdata.sh
```
