# رتل معي - Quran Recitation Platform

منصة تعليمية متكاملة لتسهيل حفظ القرآن الكريم وضبطه عن بعد.

## المميزات

- **تسجيل ورفع التلاوات**: تسجيل صوتي مباشر أو رفع ملفات صوتية
- **تصحيح التلاوات**: معلمون مؤهلون لمراجعة وتصحيح التلاوات
- **متابعة التقدم**: لوحة تحكم تفاعلية لمتابعة التقدم في الحفظ
- **نظام الاشتراكات**: ثلاث باقات (عادية، ذهبية، مميزة)
- **لوحة تحكم للمعلمين**: إدارة الطلاب والتلاوات والتقارير
- **لوحة تحكم إدارية**: إدارة المستخدمين والاشتراكات

## متطلبات النظام

- PHP 7.4 أو أحدث
- MySQL 5.7 أو أحدث
- Apache/Nginx مع mod_rewrite
- متصفح حديث يدعم MediaRecorder API

## التثبيت

1. **استيراد قاعدة البيانات**:
   ```sql
   mysql -u root -p < database/schema.sql
   ```

2. **تكوين الاتصال بقاعدة البيانات**:
   عدّل ملف `includes/config.php` وأضف بيانات الاتصال:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'tilawa_platform');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. **إنشاء مجلد الرفع**:
   تأكد من وجود مجلد `uploads/` مع صلاحيات الكتابة:
   ```bash
   mkdir uploads
   chmod 755 uploads
   ```

4. **الوصول للموقع**:
   افتح المتصفح وانتقل إلى:
   ```
   http://localhost/tilawa project/
   ```

## الحساب الافتراضي للمدير

- **البريد الإلكتروني**: admin@rattil.com
- **كلمة المرور**: password

## هيكل المشروع

```
tilawa project/
├── index.php              # الصفحة الرئيسية
├── login.php              # تسجيل الدخول
├── register.php           # التسجيل
├── dashboard.php           # لوحة التحكم
├── recitation.php         # رفع ومراجعة التلاوات
├── progress.php           # متابعة التقدم
├── subscription.php       # إدارة الاشتراكات
├── admin/                 # لوحة التحكم الإدارية
│   ├── index.php
│   ├── users.php
│   └── subscriptions.php
├── api/                   # واجهات برمجية
│   ├── upload_recitation.php
│   ├── submit_correction.php
│   ├── get_corrections.php
│   ├── subscribe.php
│   └── update_progress.php
├── includes/              # ملفات التكوين والدوال
│   ├── config.php
│   ├── functions.php
│   └── auth.php
├── assets/                # الملفات الثابتة
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/               # ملفات التلاوات المرفوعة
└── database/              # ملفات قاعدة البيانات
    └── schema.sql
```

## الأمان

- تشفير كلمات المرور باستخدام bcrypt
- حماية من SQL Injection باستخدام PDO Prepared Statements
- حماية من XSS باستخدام htmlspecialchars
- التحقق من صلاحيات المستخدمين
- التحقق من صحة الملفات المرفوعة

## الدعم

للمساعدة والدعم، يرجى التواصل مع فريق التطوير.

## الترخيص

هذا المشروع مخصص للاستخدام التعليمي.


