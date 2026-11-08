# الباك اند — Laravel API لموقع صُنّاع AI 2

API كامل للموقع مبني بـ **Laravel 12** وقاعدة بيانات **MySQL**، ويستخدم **Sanctum** لتوكنات الدخول.
الموقع (`index.html` في جذر المستودع) يتصل به عندما تضع في إعداداته:

```js
BACKEND: "laravel",
LARAVEL_API_URL: "https://رابط-السيرفر"
```

إلى أن تفعل ذلك يبقى الموقع شغّالًا على Supabase كما هو.

## ما الذي يفعله

| الجزء | التفاصيل |
|---|---|
| الدخول | رابط يُرسل على الإيميل (صالح 30 دقيقة ولمرة واحدة) ← توكن API صالح 30 يومًا |
| العضوية | الانضمام فقط برابط دعوة مربوط بإيميل محدد ويُستخدم مرة واحدة. الأدمن ينضم بدون دعوة |
| الأدمن | الإيميلات في `ADMIN_EMAILS`: إنشاء دعوات، حذف أعضاء، كتابة وصف التمارين، حذف أي عمل أو تعليق |
| الأعمال | رابط، صورة، PDF، أو أي ملف حتى 50MB. الصور وملفات PDF تفتح في المتصفح، وباقي الملفات تُنزَّل فقط (حماية من صفحات HTML الضارة) |
| التفاعل | إعجاب/عدم إعجاب. عدم الإعجاب يراه صاحبه وصاحب العمل والأدمن فقط |
| التعليقات | يكتبها الأعضاء، ويحذفها الكاتب أو الأدمن |
| الإشعارات | لصاحب العمل عند إعجاب أو تعليق (إشعار إعجاب واحد لكل شخص) |
| الحساب | تعديل الاسم والنبذة والصورة، وحذف الحساب مع كل ملفاته وأعماله |

## نقاط الـ API

كل المسارات تبدأ بـ `/api`. المسارات المقفلة تحتاج `Authorization: Bearer <token>`.

| الطريقة | المسار | من |
|---|---|---|
| POST | `/auth/link` `{email, invite?}` | الكل |
| POST | `/auth/verify` `{token}` | الكل |
| GET | `/profiles` · `/works` · `/exercises` · `/reactions` · `/comments/rows` · `/works/{id}/comments` | الكل |
| GET | `/files/works/{id}` · `/files/avatars/{userId}` | الكل |
| GET | `/me` · `/notifications` | مسجّل |
| POST | `/auth/logout` · `/join` `{token?, name}` · `/notifications/read` | مسجّل |
| DELETE | `/me` | مسجّل |
| POST | `/me/profile` · `/works` | عضو |
| PUT | `/works/{id}/reaction` `{value: 1 \| -1 \| 0}` | عضو |
| POST | `/works/{id}/comments` `{body}` | عضو |
| POST/PATCH · DELETE | `/works/{id}` | صاحب العمل أو أدمن |
| DELETE | `/comments/{id}` | الكاتب أو أدمن |
| GET · POST · DELETE | `/admin/invites` · `/admin/invites/{token}` | أدمن |
| DELETE | `/admin/members/{userId}` | أدمن |
| PUT | `/admin/exercises/{no}` `{title, description}` | أدمن |

الأخطاء ترجع بالشكل `{"message": "INVITE_USED"}` والموقع يترجمها للعربي.

## الاختبارات

تعمل تلقائيًا على GitHub Actions مع كل تعديل على مجلد `backend/` (ملف `.github/workflows/backend.yml`):
على SQLite، وعلى **MySQL 8 حقيقية**، ثم بناء صورة Docker. محليًا:

```bash
composer install
php artisan test
```

## النشر

GitHub Pages لا يشغّل PHP، لذلك يحتاج الباك اند سيرفرًا. الخيار المجاني الأنسب:

### الخيار 1: alwaysdata (مجاني، PHP + MySQL + مساحة دائمة للملفات)

1. سجّل في alwaysdata.com واختر الخطة المجانية (للاستخدام الشخصي وغير الربحي).
2. **Databases ← MySQL**: أنشئ قاعدة بيانات ومستخدمًا لها، واحفظ الاسم وكلمة المرور والـ Host.
3. **SSH**: فعّل الدخول بكلمة مرور للمستخدم الموجود، ثم من الطرفية (`ssh sunnaa@ssh-sunnaa.alwaysdata.net`):
   ```bash
   git clone https://github.com/sunnaa-ai2/sunnaa-ai2.github.io.git site
   bash site/backend/deploy/alwaysdata.sh
   ```
   السكربت يثبت الحزم، ينشئ `.env` من `.env.example` (معبأ لحساب sunnaa)، يسألك عن كلمة سر MySQL بشكل مخفي، ينشئ الجداول، ويجهز الكاش.
   للتحديث لاحقًا: `cd site && git pull && bash backend/deploy/alwaysdata.sh`
4. **Web ← Sites**: أنشئ موقعًا من نوع PHP، واجعل المجلد الجذري `site/backend/public`.
5. رابط الـ API سيكون مثل `https://اسم-حسابك.alwaysdata.net`. ضعه في `APP_URL` داخل `.env`.

### الخيار 2: أي استضافة Docker (Render، Koyeb، Railway…)

`Dockerfile` جاهز (FrankenPHP + PHP 8.3). ملاحظتان مهمتان على الخطط المجانية:
- القرص مؤقت ويُمسح عند كل إعادة تشغيل، فاجعل `FILES_DISK=s3` وضع بيانات حاوية S3 (مثل Supabase Storage أو Cloudflare R2).
- تحتاج MySQL خارجية، وضع بياناتها في متغيرات البيئة.
- بعض الخطط المجانية تُنيم السيرفر بعد دقائق بلا زيارات، فيتأخر أول طلب حوالي دقيقة.

### متغيرات البيئة المهمة

| المتغير | القيمة |
|---|---|
| `APP_KEY` | يُولَّد بـ `php artisan key:generate` |
| `APP_URL` | رابط السيرفر نفسه |
| `DB_*` | بيانات MySQL |
| `FRONTEND_URL` · `CORS_ORIGINS` | `https://sunnaa-ai2.github.io` |
| `ADMIN_EMAILS` | إيميلات الأدمن مفصولة بفاصلة |
| `MAIL_*` | بريد alwaysdata نفسه لإرسال روابط الدخول (بدون خدمة خارجية وبدون كلمة سر). `MAIL_FROM_ADDRESS` = العنوان المنشأ من Emails ← Addresses. للطوارئ: `php artisan sunnaa:login-link EMAIL` يطبع رابط دخول بدون بريد |
| `FILES_DISK` | `local` أو `s3` |

### بعد النشر

1. افتح `https://رابط-السيرفر/api` — يجب أن ترى `{"name":"sunnaa-ai2-api","ok":true}`.
2. في `index.html` اجعل `BACKEND: "laravel"` وضع الرابط في `LARAVEL_API_URL`.
3. ادخل بإيميل الأدمن وأنشئ ملفك من "أكمل التسجيل".

> البيانات الحالية في Supabase لا تُنقل تلقائيًا. حاليًا فيها حساب الأدمن فقط، فإنشاؤه من جديد أسهل من النقل.
