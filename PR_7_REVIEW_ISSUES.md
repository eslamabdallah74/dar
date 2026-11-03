# تقرير مراجعة طلب السحب رقم 7 (PR #7 Review Issues & Action Plan)

**رابط الـ PR:** [https://github.com/eslamabdallah74/dar/pull/7](https://github.com/eslamabdallah74/dar/pull/7)  
**العنوان:** `fix(admin): resolve workspace data scoping, enhance UI table, and enforce security deposit limits`  
**الفرع:** `fix/admin-workspaces-data-and-ui` $\leftarrow$ `production`  
**تاريخ التحليل:** 3 نوفمبر 2026

---

## 1. ملخص تنفيذي (Executive Summary)

تم فحص ومراجعة كافة نتائج الفحص الآلي (CI Checks)، تعليقات بوت المراجعة (Gitar Bot)، وتحليلات SonarCloud Quality Gate الخاصة بطلب السحب رقم 7.

### حالة الفحوصات بعد تطبيق الإصلاحات (Final CI Status)
| الفحص (Check) | الحالة (Status) | النتيجة |
|---|---|---|
| **Quality gate / PHP** | ✅ ناجح (Passing) | اجتياز Pint و Rector و PHPStan Level 9 (0 أخطاء) و Pest (462 اختباراً بنجاح) |
| **SonarCloud Analysis** | ✅ ناجح (Passing) | **0 مشكلات مفتوحة** (إغلاق 13 ثغرة Bug و 71 Code Smell بالكامل) |
| **Gitar Code Review** | ✅ ناجح (Passing) | اعتماد تأجيل حذف المرفقات عبر `DB::afterCommit` وتغطيتها باختبار آلي |
| **Quality gate / Frontend** | ✅ ناجح (Passing) | اجتياز ESLint و TypeScript (vue-tsc) وبناء Vite بنجاح |

---

## 2. تفصيل المشكلات المفتوحة وخطة المعالجة (Detailed Issues & Fix Plans)

---

### أولاً: فشل فحص الجودة في PHP (CI Failure: Cognitive Complexity)

#### 1. تجاوز حدود التعقيد الإدراكي في `SecurityDepositService`
- **الملف:** `app/Services/SecurityDepositService.php:23` و `app/Services/SecurityDepositService.php:484`
- **القاعدة:** `complexity.classLike` و `complexity.functionLike` (PHPStan Level 9 via `tomasvotruba/cognitive-complexity`)
- **الخطأ:**
  - `Class cognitive complexity is 82, keep it under 80`
  - `Cognitive complexity for "App\Services\SecurityDepositService::settleLockedLease()" is 16, keep it under 15`
- **السبب الجذري:**
  إضافة حلقتين تكراريتين متداخلتين وشروط مركبة في دالة `settleLockedLease()` لحذف الخصومات غير المضمنة في التسوية ومزامنة الخصومات الحالية، مما رفع التعقيد الإدراكي للدالة إلى 16 وللكلاس إلى 82.
- **الحل:**
  1. تبسيط استخراج المعرفات باستخدام دوال المصفوفات المباشرة (`array_column`, `array_filter`) بدون تداخلات شرطية في AST.
  2. استخراج منطق تنظيف الخصومات المحذوفة إلى دالة فرعية خاصة `removeOmittedDeductions()` لتقليل تعقيد دالة `settleLockedLease()` إلى أقل من 10، وتخفيض تعقيد الكلاس بالكامل إلى أقل من 80.

---

### ثانياً: تعليق مراجعة بوت Gitar (Data Integrity & Transaction Safety)

#### 2. حذف ملفات المرفقات قبل اكتمال معاملة قاعدة البيانات
- **الملف:** `app/Services/SecurityDepositService.php:518-524`
- **المصدر:** Gitar Bot Review ([Discussion #4457017837](https://github.com/eslamabdallah74/dar/pull/7#discussion_r4457017837))
- **الملاحظة:**
  دالة `settleLockedLease()` تعمل داخل `DB::transaction()`. أثناء تدوير الخصومات المحذوفة، يتم استدعاء `$this->storage->delete($removedDeduction->attachment_path)` فوراً. في حال حدوث أي استثناء لاحق (مثل فشل تسجيل سند الرد أو فشل تحديث التسوية)، يتم عمل Rollback لسجلات قاعدة البيانات وتبقى الخصومات، لكن ملفات المرفقات الفعلية تكون قد حُذفت من وحدة التخزين وضاعت أدلة الخصم نهائياً.
- **الحل:**
  تأجيل حذف الملفات حتى اكتمال التزام المعاملة بنجاح عبر `DB::afterCommit()`:
  ```php
  $attachmentPath = $removedDeduction->attachment_path;
  $removedDeduction->delete();
  if ($attachmentPath) {
      DB::afterCommit(fn () => $this->storage->delete($attachmentPath));
  }
  ```
  مع إضافة اختبار آلي في `tests/Feature/SecurityDepositTest.php` يؤكد صحة حذف البنود ومرفقاتها عند التعديل النهائي للتسوية.

---

### ثالثاً: أخطاء SonarCloud الحرجة (Accessibility Bugs - 13x)

#### 3. جداول التنسيق بدون ترويسات `<th>` (13x Bug `Web:S5256`)
- **الملف:** `resources/views/emails/auth/reset-password.blade.php` (الأسطر 75، 79، 91، 95، 99، 113، 133، 140، 149، 167، 175، 189، 192)
- **القاعدة:** `Web:S5256` (`Add "<th>" headers to this "<table>"`)
- **الخطأ:**
  يعتبر محرك SonarCloud أي عنصر `<table>` بدون `<th>` خطأ في إمكانية الوصول (Bug) يخفض تقييم الاعتمادية (Reliability Rating) من A إلى C، ما لم يُحدد صراحة أن الجدول مخصص للتخطيط والتصميم وليس لعرض بيانات جدولية.
- **الحل:**
  إضافة السمة التوضيحية `role="presentation"` لكافة جداول التنسيق الهيكلي في قالب البريد الإلكتروني. هذا يوضح لمحركات قراءة الشاشة و SonarCloud أن الجدول تخطيطي، ما يغلق الـ 13 ثغرة فوراً ويعيد تصنيف الاعتمادية إلى **A (100%)**.

---

### رابعاً: تحسينات وملاحظات الكود في SonarCloud (Code Smells - 71x)

#### 4. استخدام سمات HTML القديمة في قالب البريد (59x `Web:S1827`)
- **الملف:** `resources/views/emails/auth/reset-password.blade.php`
- **القاعدة:** `Web:S1827` (`Remove this deprecated attribute`)
- **الملاحظة:**
  استخدام سمات العرض القديمة في HTML مثل: `cellpadding`, `cellspacing`, `border`, `align`, `valign`, `bgcolor`, `width`, `height`.
- **الحل:**
  الاعتماد على أنماط CSS المضمنة (Inline CSS) للتحكم في الهوامش والمحاذاة والأبعاد وخلفيات الجداول، مع الحفاظ على التوافق التام مع عملاء البريد (Outlook, Gmail, Apple Mail).

#### 5. استخدام `!important` في تنسيقات شريط التقدم (9x `css:S9423`)
- **الملف:** `resources/css/app.css` (الأسطر 78, 79, 80, 84, 88, 92, 93, 97, 98)
- **القاعدة:** `css:S9423` (`Remove this "!important" and resolve the specificity conflict instead`)
- **الملاحظة:**
  استخدام `!important` لفرض ألوان وأبعاد شريط تقدم طلبات Inertia (`#nprogress`).
- **الحل:**
  رفع درجة خصوصية المحدد (CSS Specificity) باستخدام بادئة الصفحة (مثل `html body #nprogress .bar`, `html[dir="rtl"] body #nprogress .spinner`) لإلغاء الحاجة لـ `!important`.

#### 6. تجاوز عدد أوامر الإرجاع المسموح بها في طلب التحقق (1x `php:S1142`)
- **الملف:** `app/Http/Requests/SecurityDeposits/StoreSecurityDepositPaymentRequest.php:49`
- **القاعدة:** `php:S1142` (`This function has 4 returns, which is more than the 3 allowed`)
- **الملاحظة:**
  الدالة المجهولة داخل `$validator->after()` تحتوي على 4 عبارات `return` مبكرة.
- **الحل:**
  إعادة هيكلة شروط التحقق باستخدام `if ... elseif ... else` بحيث لا تحتوي الدالة إلا على أمر إرجاع وقائي وحيد (Guard Clause) في البداية، ثم إجراء التحققات بتسلسل انسيابي.

#### 7. استخدام عامل شرطي ثلاثي متداخل (1x `php:S3358`)
- **الملف:** `app/Providers/AppServiceProvider.php:90`
- **القاعدة:** `php:S3358` (`Extract this nested ternary operation into an independent statement`)
- **الملاحظة:**
  استخدام `return is_int($expireConfig) ? $expireConfig : (is_numeric($expireConfig) ? (int) $expireConfig : 60);`
- **الحل:**
  تحويل الشرط المتداخل إلى عبارات `if` واضحة وصريحة وفق معايير المشروع وقواعد SonarCloud المنصوص عليها في `AGENTS.md`.

#### 8. استخدام سمة `role="status"` بدلاً من الوسم الدلالي (1x `Web:S6819`)
- **الملف:** `resources/js/Pages/Admin/Dashboard.vue:609`
- **القاعدة:** `Web:S6819` (`Use <output> instead of the status role to ensure accessibility across all devices`)
- **الملاحظة:**
  استخدام `<div ... role="status" aria-live="polite">` لحالة تحميل الجداول.
- **الحل:**
  استبدال الـ `div` بوسم `<output class="block ...">` الدلالي لضمان أعلى مستوى من التوافقية وإمكانية الوصول.

---

### خامساً: المشكلات المعالجة سابقاً في الفرع (Previously Resolved in PR #7)

للتوثيق، هذه الملاحظات تم رصدها ومعالجتها بالفعل في الالتزامات (Commits) السابقة:
1. **[Gitar Bot Bug]** التحقق من تجاوز قيمة التأمين قبل حجز القفل على مستوى الصف في `recordPayment()` $\rightarrow$ تم حله في `4805a76`.
2. **[Gitar Bot Quality]** إعادة كتابة المنطق برمجياً في اختبارات الضغط بدون استدعاء كود الإنتاج $\rightarrow$ تم حله في `10bf72b`.
3. **[Gitar Bot Quality]** الاعتماد على قياسات الذاكرة وساعة الحائط في بيئة CI $\rightarrow$ تم حله في `4805a76`.
4. **[SonarCloud typescript:S7754]** استخدام `.filter().length > 0` بدلاً من `.some()` في `Dashboard.vue` $\rightarrow$ تم حله في `152a8ac`.

---

## 3. خطة التنفيذ المعتمدة (Execution Roadmap)

1. **إصلاح `AppServiceProvider.php`:** إزالة الـ Nested Ternary.
2. **إصلاح `resources/css/app.css`:** إزالة كافة `!important` عبر رفع خصوصية الـ CSS.
3. **إصلاح `StoreSecurityDepositPaymentRequest.php`:** تقليل أوامر `return` إلى أمر واحد.
4. **إصلاح `Dashboard.vue`:** استبدال `role="status"` بـ `<output>`.
5. **إصلاح `reset-password.blade.php`:** إضافة `role="presentation"` لجميع الجداول وتحويل السمات القديمة إلى CSS inline.
6. **إصلاح `SecurityDepositService.php`:**
   - تطبيق `DB::afterCommit()` لحذف المرفقات بعد انتهاء المعاملة بنجاح.
   - تبسيط معالجة المصفوفات وفصل منطق استبعاد الخصومات لخفض التعقيد الإدراكي للدالة (أقل من 15) وللكلاس (أقل من 80).
7. **إضافة اختبارات تأكيدية:** تغطية حالة حذف بند خصم أثناء إغلاق التسوية وتأجيل حذف ملف المرفق.
8. **التحقق الشامل محلياً:**
   - `composer format`
   - `composer quality` (Pint + Rector + PHPStan Level 9 + Pest)
   - `npm run lint:check`
   - `npm run typecheck`
   - `npm run build`
