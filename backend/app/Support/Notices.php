<?php

namespace App\Support;

use App\Mail\NoticeMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** The e-mails of the account flow. A failed e-mail is logged and never breaks the request. */
class Notices
{
    private static function site(string $path = ''): string
    {
        return rtrim(config('spark.frontend_url'), '/').'/'.$path;
    }

    private static function send(string|array $to, NoticeMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::warning('Mail failed: '.$e->getMessage());
        }
    }

    /** To every admin: someone registered and waits for approval. */
    public static function joinRequest(User $user): void
    {
        if (! config('spark.admins')) {
            return;
        }
        self::send(config('spark.admins'), new NoticeMail(
            'طلب انضمام جديد: '.$user->requested_name,
            ['الاسم: '.$user->requested_name, 'اسم المستخدم: '.$user->username, 'الإيميل: '.$user->email, 'افتح لوحة التحكم لقبول الطلب أو رفضه.'],
            'مراجعة الطلب',
            self::site('#admin'),
        ));
    }

    public static function approved(User $user): void
    {
        self::send($user->email, new NoticeMail(
            'تم قبول حسابك في صُنّاع AI 2',
            ['أهلًا '.$user->requested_name.'، وافق المسؤول على طلبك وصار حسابك فعّالًا.', 'ادخل باسم المستخدم '.$user->username.' وكلمة المرور اللي اخترتها.'],
            'الدخول إلى الموقع',
            self::site('#login'),
        ));
    }

    public static function rejected(User $user): void
    {
        self::send($user->email, new NoticeMail(
            'بخصوص طلب انضمامك إلى صُنّاع AI 2',
            ['أهلًا '.$user->requested_name.'، نعتذر، لم تتم الموافقة على طلب الانضمام.', 'إذا كنت من متدربي المبادرة، تواصل مع المدرب أو قدّم الطلب مرة ثانية.'],
        ));
    }

    public static function passwordReset(string $email, string $url, ?string $username): void
    {
        $lines = ['اضغط الزر لتعيين كلمة مرور جديدة. الرابط يعمل مرة واحدة خلال '.config('spark.reset_ttl').' دقيقة.'];
        if ($username) {
            $lines[] = 'اسم المستخدم تبعك: '.$username;
        }
        self::send($email, new NoticeMail('تعيين كلمة المرور — صُنّاع AI 2', $lines, 'تعيين كلمة المرور', $url, 'إذا لم تطلب هذا فتجاهل الرسالة، وكلمة مرورك الحالية لن تتغير.'));
    }
}
