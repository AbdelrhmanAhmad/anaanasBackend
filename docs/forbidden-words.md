# نظام الكلمات الممنوعة

## المبدأ

- قائمة **كلمات** (عربي / إnglيزي / مختلط) — بدون «عبارات» أو «نوع مطابقة».
- إذا وُجدت **أي كلمة** محظورة في نص المستخدم → رفض.
- إدخال مثل `happy ending` يُخزَّن كسطر واحد ويُطلب ظهور **happy** و **ending** ككلمتين مستقلتين في النص.

## التشغيل

```bash
php artisan migrate
php artisan db:seed --class=ForbiddenWordsSeeder
```

## لوحة التحكم (Simple Resource)

صفحة واحدة `/back-office-v1/forbidden-words` — جدول + إضافة/تعديل في modals.

حقول: **الكلمة**، **التصنيف**، **نشط**.

## API

| المسار | الحقول |
|--------|--------|
| `POST /api/post` | title, description, location |
| `POST /api/posts/{post}` | title, description, location |
| `POST /api/auth/register` | name |
| `PUT\|POST /api/auth/profile` | first_name, last_name, username, bio |
