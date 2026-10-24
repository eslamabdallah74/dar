# خطط وميزات منصة دار (Dar) — تكامل WhatsApp Business والتنبيهات المؤتمتة
# Dar — Plans & Features: WhatsApp Business Integration & Automated Notifications

أنسب حل لمنصة دار (Dar) هو استخدام WhatsApp Business Platform — Cloud API الرسمي من Meta، وربطه بـ Laravel بحيث يرسل رسائل تلقائية بناءً على مواعيد الإيجار والأحداث التي تحدث داخل النظام، بدلًا من الاعتماد على WhatsApp Web أو مكتبات غير رسمية. توفر Meta واجهات لإرسال الرسائل، وقوالب رسائل معتمدة، وWebhooks لمتابعة حالات الرسائل. [Facebook Developers](https://developers.facebook.com/documentation/business-messaging/whatsapp/get-started?utm_source=chatgpt.com)

---

## كيف نريد أن تعمل الميزة؟

- **مثال توضيحي:** إذا كان موعد استحقاق الإيجار يوم 25 نوفمبر، يرسل النظام تذكيرًا يوم 23 نوفمبر تلقائيًا، في الوقت الذي يحدده مالك مساحة العمل.
- **نقطة جوهرية:** التذكير مرتبط بتاريخ استحقاق الإيجار، وليس بتاريخ تسجيل الدفعة. لذلك سنعتمد على جدول `rent_charges` وحقل `due_date`، مع إعادة التحقق من المبلغ المتبقي قبل الإرسال؛ فإذا دفع المستأجر الإيجار كاملًا، يُلغى التذكير المعلّق.
- **قابلية التوسع:** نريد تصميم الميزة بحيث تبدأ بتذكيرات المستأجرين، ثم تسمح بإضافة أنواع أخرى من الرسائل، مثل:
  - إخطار المالك عند استلام إيجار.
  - تأخر مستأجر عن الدفع.
  - اقتراب انتهاء عقد الإيجار.
  - تسجيل أو تحديث طلب صيانة.
- **الموافقة والتسعير:** رسائل التذكير التي تبدأها الشركة تحتاج إلى مراعاة موافقة المستلم وقواعد قوالب واتساب المعتمدة. كذلك، تختلف التكلفة بحسب بلد المستلم وفئة الرسالة، ولذلك يجب مراجعة الأسعار الحالية لمصر وعدم افتراض أن جميع الرسائل مجانية. [WhatsApp for Business](https://whatsappbusiness.com/products/business-platform-features/?utm_source=chatgpt.com)

---

## 1. Prompt للـ Coding Agent

> البرومبت التالي يطلب تنفيذ الميزة داخل مشروعك، مع مراعاة الـ multi-tenancy، وإمكانية توسيع أنواع الرسائل لاحقًا، وعدم إرسال رسائل حقيقية أثناء الاختبار.

### Dar — WhatsApp Business Integration and Automated Notifications

#### 1. Objective
Implement an extensible WhatsApp notification system in the existing Dar rental management SaaS application.
The application uses Laravel, Vue 3, TypeScript, Inertia, and MySQL.

The initial business requirement is to automatically remind renters about upcoming rent payments through WhatsApp, with a default reminder scheduled two days before the due date.

The system must also support future notifications to property owners, including payment confirmations, overdue rent alerts, upcoming lease expirations, and maintenance updates.

- Do not implement a one-off rent reminder. Build a reusable notification system that can support additional events and recipient types without rewriting the entire integration.
- Use the official Meta WhatsApp Business Platform Cloud API.
- Do not use browser automation, WhatsApp Web scraping, or unofficial WhatsApp libraries for message delivery.
- Before implementing anything, inspect the existing application, database schema, routes, queue configuration, scheduler, renter models, lease and rent-charge models, workspace architecture, and existing message-related code.
- Reuse the existing implementation where possible.

---

#### 2. Core Business Rules

##### Rent reminders
The initial notification event is: `rent_due_reminder`.

A reminder is generated when a rent charge approaches its due date.
For example:
- **Rent due date:** November 25.
- **Reminder setting:** 2 days before.
- **Scheduled reminder:** November 23 at the workspace's configured reminder time.

Use the existing rent-charge model and its `due_date` field.
Do not schedule reminders based on:
- The lease creation date.
- The date a payment was recorded.
- The date a renter was created.
- The date a rent charge was inserted into the database.

The due date is the source of truth.

##### Before sending
Immediately before sending a reminder, recheck that:
1. The rent charge still exists.
2. The charge is not cancelled or otherwise ineligible for collection.
3. Its outstanding balance is greater than zero.
4. The renter has a valid WhatsApp phone number.
5. The renter has the required recorded consent.
6. WhatsApp reminders are enabled for the workspace.
7. The notification has not already been processed successfully.
8. The charge still belongs to the correct workspace.

- If the rent charge has been fully paid, cancel any unsent reminder.
- If a partial payment has been received, calculate the outstanding balance using the existing financial allocation model and use the current balance in the message.
- Do not rely on a stale balance calculated when the reminder was originally scheduled.

---

#### 3. WhatsApp Provider Integration
Create a dedicated WhatsApp integration service using Laravel's HTTP client and the official Meta Cloud API.
Use a small provider abstraction so the business logic does not depend directly on Meta-specific HTTP requests.

For example, follow the existing project conventions to introduce components equivalent to:
- `WhatsAppProvider`
- `MetaWhatsAppCloudApiProvider`
- `WhatsAppMessageService`

These are conceptual responsibilities, not mandatory class names. Reuse existing services if the codebase already has appropriate equivalents.

The provider must support:
- Sending approved template messages.
- Returning the provider's message identifier.
- Reporting API errors in a structured way.
- Recording the initial send result.
- Supporting future message types without duplicating HTTP logic.

Keep API request construction, authentication, response parsing, and error handling inside the provider integration.
Do not scatter Meta-specific API calls across controllers, models, and scheduled tasks.
Use the API version configured for the application rather than hardcoding an outdated version throughout the codebase.

---

#### 4. Configuration and Secrets
Read integration credentials from environment variables or the application's existing secret-management mechanism.
Support configuration equivalent to:

```dotenv
WHATSAPP_ENABLED=false
WHATSAPP_DRIVER=meta
WHATSAPP_API_VERSION=v20.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_BUSINESS_ACCOUNT_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_APP_SECRET=
WHATSAPP_WEBHOOK_VERIFY_TOKEN=
WHATSAPP_DEFAULT_LANGUAGE=ar
```

Adapt names to the application's existing configuration conventions where appropriate.

##### Requirements:
- Never commit access tokens or app secrets to Git.
- Never expose secrets through Inertia page props, frontend JavaScript, browser responses, or application logs.
- Never place access tokens in URLs.
- Do not allow ordinary workspace users to modify platform-wide secrets.
- Fail safely when configuration is missing or invalid.
- Make the integration's health and configuration status visible to authorized administrators without displaying secret values.
- Keep live sending disabled until the integration is explicitly configured and enabled.
- Support a development or log-only mode that allows reminder scheduling and message previews without contacting real WhatsApp recipients.
- Use the appropriate production credentials and permissions when moving from development to live sending.

---

#### 5. Workspace-Level Notification Settings
Each workspace must control its own notification preferences.
A workspace must not be able to modify or access another workspace's notification settings or message history.
Implement a simple, maintainable settings model that supports the following requirements.

##### Initial settings:
| Setting | Default | Purpose |
| :--- | :--- | :--- |
| **WhatsApp notifications enabled** | Disabled until configured and enabled | Master notification toggle |
| **Rent reminders enabled** | Enabled after workspace setup, subject to consent and integration availability | Controls rent reminders |
| **Reminder days before due date** | 2 | Number of days before rent is due |
| **Reminder sending time** | 09:00 | Local time at which reminders may be sent |
| **Reminder language** | Arabic | Language of the selected approved template |

- The master toggle should prevent new messages from being sent when disabled.
- Use `Africa/Cairo` as the default workspace timezone if that matches the existing application configuration, but respect each workspace's configured timezone if one exists.
- Validate reminder settings on the backend.
- For the initial UI, allow owners to:
  - Enable or disable rent reminders.
  - Select how many days before the due date to send them.
  - Choose a supported sending time.
  - Preview the message.
  - Inspect recent message results.
- Do not build a complicated visual workflow editor for this MVP.

##### Future extensibility:
Use a simple event-based notification structure so additional notification rules can be introduced later.
Avoid implementing all future workflows now.
The architecture should allow new events to define:
- Event type.
- Recipient type.
- Channel.
- Whether the event is enabled.
- Timing rules, when applicable.
- Approved message template.
- Variables to populate the template.

Do not store independent, duplicated WhatsApp logic inside every business module.

---

#### 6. Recipients: Renters and Workspace Owners
Initially, support two recipient types.

##### A. Renters
- For rent reminders, resolve the renter from the rent charge's related lease.
- Use the renter's current, valid WhatsApp phone number.
- Phone numbers must be normalized to a consistent international format, such as E.164, using an appropriate phone-number validation approach.
- Because Dar initially targets Egypt, allow Egyptian phone numbers to be entered conveniently while storing them in a normalized international format.
- Do not silently assume every phone number belongs to Egypt if the application supports international numbers.

##### B. Workspace owners
- Design the recipient model so future messages can be sent to the appropriate workspace owner or another authorized workspace user.
- Do not make `renter_id` the only way to represent a notification recipient.
- Use a consistent recipient-resolution and consent model that supports both renter records and user accounts.
- The initial delivery implementation may focus on rent reminders to renters, but the schema and notification service must not require every recipient to be a renter.
- All owner notifications must be routed to the appropriate user for the relevant workspace.

##### Consent
Record WhatsApp opt-in and opt-out information.
Reuse existing renter consent fields if they exist.
For both renters and owner recipients, record sufficient information to determine whether the recipient has agreed to receive the relevant notifications.
At minimum, support:
- Consent status.
- Consent timestamp.
- Consent source.
- Opt-out timestamp.
- Appropriate audit information.

A phone number in the database does not automatically imply permission to message that contact.
Provide an explicit opt-in process appropriate to how the contact was collected.
When an opt-out is recorded:
- Prevent future eligible messages from being scheduled or sent.
- Cancel queued messages that have not yet been sent.
- Recheck consent immediately before sending.
- Preserve the existing message history for auditing.
- If inbound WhatsApp messages are implemented, support the configured opt-out handling there as well. Do not interpret arbitrary incoming text as consent.

---

#### 7. Approved Message Templates
Outbound business-initiated notifications must use an appropriate WhatsApp message template approved by Meta where required.
Do not assume that having a user's phone number permits unrestricted automated messages.
Create a clear mapping between internal notification events and approved template names.
For example:
`rent_due_reminder` → configured approved rent-reminder template.

- Store the template name and language in application configuration or the appropriate settings model.
- Do not allow arbitrary user-supplied text to bypass Meta's template requirements.
- Allow safe variable substitution into the approved template.
- The message must use the appropriate variables for:
  - Renter name.
  - Property name.
  - Unit identifier or name.
  - Rent due date.
  - Outstanding amount.
  - Relevant contact or payment instructions, if configured.
- Only include information that is necessary for the notification.
- Do not invent payment links or expose private information belonging to another workspace.

##### Suggested Arabic template content:
Prepare an Arabic utility-style rent-reminder template for submission through Meta's template-management process.
Suggested content:
> أهلًا {{1}}، نذكّرك بأن موعد استحقاق الإيجار الخاص بـ {{2}} هو {{3}}. المبلغ المتبقي للسداد: {{4}} {{5}}. إذا كنت قد سددت بالفعل، يُرجى تجاهل هذه الرسالة. للاستفسار، يُرجى التواصل مع إدارة العقار.

- Use placeholders for the renter's name, unit or property, due date, currency, and outstanding amount as appropriate.
- Adapt the final variable mapping to the approved template's actual structure.
- The suggested message is not considered an approved template until it has been submitted and approved through the appropriate Meta workflow.
- Do not attempt to send live template messages using an unapproved template name.

---

#### 8. Reminder Scheduling
Implement reminder scheduling using Laravel Scheduler and Laravel Queue.
The process should:
1. Find eligible rent charges.
2. Determine which charges reach their configured reminder window.
3. Resolve the workspace's notification settings.
4. Resolve the recipient and the approved template.
5. Check consent and current outstanding balance.
6. Create a pending message record with a unique deduplication key.
7. Dispatch an asynchronous job.
8. Send the message through the WhatsApp provider.
9. Record the provider response.
10. Update the message state when webhook events arrive.

- Use the workspace timezone when determining when a reminder should be sent.
- Avoid sending every pending reminder synchronously during a normal web request.
- Keep the scheduler and queue worker configuration compatible with the deployment environment.
- The system should tolerate scheduler overlap and job retries without generating duplicate reminders.

---

#### 9. Idempotency and Duplicate Prevention
Prevent duplicate reminders when:
- The scheduler executes more than once.
- A queue job is retried.
- Two workers process eligible charges concurrently.
- The owner changes the reminder settings.
- A webhook is delivered more than once.
- A page is refreshed after an operation.

- Create a stable `deduplication_key` for each scheduled notification occurrence.
- For a rent reminder, the key should incorporate enough information to distinguish the workspace, rent charge, event, recipient, and reminder occurrence.
- Enforce uniqueness at the database level.
- Do not assume a queue's uniqueness mechanism alone guarantees that duplicate records cannot exist.
- If a network failure leaves the provider's acceptance status ambiguous, do not blindly resend the message and risk sending duplicate reminders. Record the uncertainty and use a safe retry or reconciliation strategy appropriate to the provider's response.

---

#### 10. Message Logging and Statuses
Extend the existing `message_logs` table if it exists instead of creating a competing message-history implementation.
The data model must support notifications to renters and workspace owners.
Review existing columns and add only the required fields.

The record should be able to represent:
- Workspace.
- Notification event type.
- Recipient type and relevant recipient reference.
- Destination phone number.
- Related rent charge, lease, property, or maintenance record when applicable.
- Provider name.
- Approved template name.
- Template language.
- Scheduling timestamp.
- Provider message ID.
- Send timestamp.
- Delivery timestamp.
- Read timestamp, if available.
- Failure timestamp, where appropriate.
- Current status.
- Provider error code and sanitized error information.
- Attempt count.
- Unique deduplication key.
- Provider cost and currency when reliably available.

##### Suggested states:
- `pending`
- `processing`
- `sent`
- `delivered`
- `read`
- `failed`
- `cancelled`

- Include an appropriate policy for uncertain provider outcomes if needed.
- A successful API response does not necessarily prove delivery.
- Preserve the distinction between a request accepted by the provider and a message delivered to its recipient.
- Record costs as unknown when they are unavailable; do not store zero as if the message were confirmed to be free.
- Do not store access tokens or unnecessary sensitive message payloads in the message history.

---

#### 11. WhatsApp Webhooks
Implement the webhook endpoint required for Meta's Cloud API integration.
It must support:
- Initial webhook verification (`hub.mode`, `hub.challenge`, `hub.verify_token`).
- Incoming message events, where needed.
- Outgoing message status updates.
- Delivered, read, and failed states when provided.
- Proper request validation and signature verification (`X-Hub-Signature-256`).
- Duplicate webhook handling.

- Use a publicly reachable HTTPS URL in the configured environment.
- Do not trust an incoming webhook merely because it targets the correct URL.
- Validate the webhook according to Meta's documented verification and signature requirements.
- Update existing message records using the provider's message ID and workspace-safe application logic.
- A webhook must not accidentally update a different workspace's message record or create duplicate messages.
- Webhook processing should be resilient and idempotent.
- Return the appropriate HTTP responses expected by Meta.
- Avoid performing extensive, slow business operations synchronously inside the webhook request.

---

#### 12. Retry and Failure Handling
- Implement safe retry behavior for recoverable failures (such as selected rate-limit responses and temporary server errors).
- Do not repeatedly retry permanent failures such as invalid destination numbers or rejected templates without an appropriate corrective action.
- Use a configurable, bounded retry policy with backoff.
- Store sanitized error codes and useful diagnostic information.
- Do not leak secrets or sensitive personal information into logs.
- Provide authorized users with enough information to understand whether a message is:
  - Waiting to be sent.
  - Sent to the provider.
  - Delivered.
  - Read, when known.
  - Failed.
  - Cancelled.
- Never claim that a failed message was delivered.

---

#### 13. Notification Settings and Message History UI
Integrate the feature into the existing Dar interface without redesigning unrelated pages.
Inspect the existing navigation before adding new routes.

The WhatsApp section should provide:
##### Overview:
- Integration availability.
- Notification enablement.
- Recent message counts.
- Failed messages requiring attention.

##### Settings:
- Enable or disable rent reminders.
- Configure days before the due date.
- Configure the sending time.
- View the configured reminder template.
- Preview the message using sample data.

##### Message history:
- Recipient.
- Event type.
- Associated renter or workspace record.
- Related rent charge when applicable.
- Scheduled time.
- Current message status.
- Failure information when available.
- Links to relevant existing records.

- Apply workspace scoping and authorization to all data.
- Do not show fake statistics or pretend a message was sent when the integration is only in log-only mode.
- Display a clear indication when live integration is not configured.
- If a "Send test message" action is supported, restrict it to authorized users and require an explicitly supplied or verified test destination. Do not send an unexpected message to an actual renter.

---

#### 14. Future Notification Events
Design the system so additional notification events can be added later.
Examples include:

##### Notifications to renters:
- Upcoming rent due.
- Rent payment recorded.
- Outstanding rent reminder.
- Lease expiration reminder.
- Relevant maintenance updates.

##### Notifications to workspace owners:
- Rent payment received.
- Rent overdue.
- Lease approaching expiration.
- New maintenance request.
- Relevant property activity requiring attention.

These are future extension points; do not implement every event in the initial scope.
Keep the notification event separate from the recipient, channel, template, and delivery provider.
A new event should not require copying the complete WhatsApp API integration into a controller.
Do not build a general-purpose workflow engine prematurely.

---

#### 15. Sender Account Strategy
- For the initial MVP, assume a single platform-managed WhatsApp Business sender configured securely at the application level.
- That sender can deliver authorized notifications to both renters and workspace owners.
- Make sure the message content identifies the platform or relevant property-management context clearly.
- Keep the provider/account resolution behind a small abstraction so the system can later support workspace-specific WhatsApp Business accounts if the product requires each landlord to connect and send from their own business number.
- Do not implement complex per-workspace WhatsApp account onboarding or token management in this phase unless the current codebase already supports it.
- Do not assume that every landlord's personal WhatsApp number can automatically be used as an API sender.

---

#### 16. Cost Tracking
- Meta's pricing can vary by destination market and message category.
- Do not hardcode message prices in the application.
- Record provider usage and cost information when reliably available.
- Allow later reconciliation against Meta's actual billing records.
- Keep the distinction between:
  - WhatsApp provider charges.
  - Dar subscription charges.
  - Any future platform-level markup.
- Do not include WhatsApp messaging as a free, unlimited feature in a subscription plan without an explicit business decision.
- For the MVP, avoid building automatic usage billing unless it has been separately approved.

---

#### 17. Security and Tenant Isolation
Apply the application's existing multi-tenancy and authorization rules.
Verify that:
- A workspace can only read its own notification settings.
- Message history is scoped to the active workspace.
- A workspace cannot use another workspace's recipients or related records.
- Recipient resolution verifies the relevant workspace relationships.
- Owner notifications are sent only to the intended authorized recipients.
- Secrets never appear in frontend code or API responses.
- Webhook events cannot modify arbitrary records.
- Opted-out recipients cannot receive new automated messages.
- Use transactions and database constraints wherever they are necessary to preserve consistency.

---

#### 18. Testing and Verification
Do not test only by inspecting code or creating test files.
Verify the actual application behavior using the available local environment.
Initially use a fake provider or log-only mode.

Verify these scenarios:
1. A valid unpaid rent charge generates a reminder at the configured time.
2. Changing the reminder offset changes the scheduled reminder appropriately.
3. A fully paid charge does not receive a reminder.
4. A partially paid charge uses the current outstanding balance.
5. An opted-out renter receives no automated reminder.
6. A workspace with reminders disabled generates no sendable reminder.
7. Running the scheduler repeatedly does not create duplicate messages.
8. A queue retry does not blindly send an already accepted message again.
9. Provider failures are logged correctly.
10. Webhook updates change delivery status correctly.
11. Duplicate webhooks do not corrupt message history.
12. A workspace cannot access another workspace's settings or message history.
13. Owner notifications can be routed through the same event/recipient model when introduced.
14. Missing API configuration does not break unrelated application pages.
15. The settings and history interfaces work in Arabic RTL on desktop and mobile.

- After the fake-provider tests, perform a real end-to-end test only after valid Meta credentials, an approved template, consented test recipients, and an appropriate test environment are available.
- Do not send messages to actual renters without explicit authorization.
- Report what was tested using the live provider versus what was only tested using the fake provider.

---

#### 19. Implementation Scope
Implement the initial production-oriented foundation:
- Official Meta Cloud API provider.
- Secure configuration.
- Workspace notification settings.
- Rent-due scheduling.
- Template-based message sending.
- Queue processing.
- Consent checks.
- Duplicate prevention.
- Message history.
- Delivery-status webhooks.
- Basic WhatsApp settings and history UI.
- Owner-recipient support in the notification architecture.
- Safe development and testing mode.

Do not implement:
- Marketing campaigns.
- Bulk promotional messaging.
- An AI chatbot.
- Automatic subscription billing for WhatsApp costs.
- A general-purpose workflow builder.
- Full embedded signup for every landlord's WhatsApp account.
- Other notification channels unless they already exist.

---

#### 20. Final Deliverables
After implementation, report:
- Files created and modified.
- Database migrations introduced.
- Required environment variables.
- Meta dashboard configuration steps still required.
- Queue and scheduler setup requirements.
- Webhook configuration requirements.
- The approved template names expected by the code.
- The exact steps needed to perform a real test message.
- The scenarios verified in the local application.
- Any remaining limitations or decisions that require my input.

> **Important:** Build the reusable notification foundation now, but keep the initial feature focused on automated rent reminders. The system must be reliable, workspace-safe, consent-aware, and easy to extend for future owner notifications.

---

## 2. إيه المطلوب منك أنت؟

فيه جزآن: إعداد حساب واتساب على Meta، وإعداد الرسائل داخل دار.

### أولًا: إعداد Meta وWhatsApp
هتحتاج إلى حساب Meta تستطيع من خلاله إدارة Business Portfolio، وإنشاء تطبيق للمطورين وإضافة منتج WhatsApp، ثم إعداد حساب WhatsApp Business Platform ورقم الإرسال. دليل Meta الرسمي يشرح بدء إعداد Cloud API والحصول على بيانات الاتصال اللازمة. [Facebook Developers](https://developers.facebook.com/documentation/business-messaging/whatsapp/get-started?utm_source=chatgpt.com)

| المطلوب منك | وظيفته |
| :--- | :--- |
| **حساب Meta بصلاحيات الإدارة** | إدارة تطبيق WhatsApp Business. |
| **Business Portfolio وحساب WhatsApp Business** | ربط رقم الإرسال وإدارة القوالب. |
| **رقم هاتف مخصص للإرسال أو رقم مناسب للتسجيل على المنصة** | الرقم الذي سيظهر للمستلمين. |
| `PHONE_NUMBER_ID` | تحديد رقم الإرسال داخل API. |
| `WHATSAPP_BUSINESS_ACCOUNT_ID` | تحديد حساب WhatsApp Business. |
| **Access Token مناسب للإنتاج** | السماح للسيرفر بإرسال الرسائل. |
| **App Secret وWebhook Verify Token** | إعداد التحقق الآمن من Webhooks. |
| **قالب رسالة عربي معتمد** | استخدامه في التذكيرات التلقائية. |
| **إعداد الفوترة عند الحاجة** | تغطية رسوم الرسائل بحسب قواعد Meta. |

- يمكنك بدء تطوير واختبار التكامل باستخدام رقم الاختبار الذي توفره Meta، إذا كان متاحًا لإعداد حسابك.
- أما تشغيل الإرسال الحقيقي فقد يتطلب استكمال إعداد الرقم، وإعدادات الحساب والفوترة، وأي تحقق تجاري تطلبه Meta لحسابك.
- لا تفترض أنك تحتاج بالضرورة إلى تقديم مستندات شركة قبل إجراء أول تجربة؛ اتبع المتطلبات التي تظهر لحسابك.
- **مهم:** لا ترسل لي الـ Access Token أو App Secret، ولا تضعهما داخل الكود أو Vue. ستضع القيم في `.env` على جهازك أو في إعدادات أسرار بيئة الإنتاج.

---

### ثانيًا: إعداد التذكيرات في دار
أقترح أن نبدأ بالإعدادات التالية:

| الإعداد | القيمة الافتراضية المقترحة |
| :--- | :--- |
| **تذكير الإيجار قبل موعد الاستحقاق** | يومان |
| **وقت الإرسال** | 9 صباحًا |
| **اللغة** | العربية |
| **التذكيرات مفعّلة** | بعد إعداد الربط وموافقة المالك |
| **إرسال التذكير إذا كان الإيجار مدفوعًا بالكامل** | لا |
| **إرسال الرسائل إلى مستأجر رفض استقبالها** | لا |
| **إرسال نسخة إلى مالك العقار** | إعداد مستقل، ويمكن إضافته للأحداث المناسبة |

- يجب حفظ موافقة كل مستلم على تلقي الرسائل التسويقية أو التشغيلية بحسب نوع الرسائل المطلوبة، وعدم اعتبار مجرد وجود رقم هاتف موافقة تلقائية.

---

### ثالثًا: قرار مهم بخصوص رقم الإرسال
أوصي في النسخة الأولى بأن يكون لدى دار رقم WhatsApp Business مركزي، وتصدر منه إشعارات المستأجرين ومُلّاك العقارات.

لكن هناك فرق بين حالتين:
1. **الحالة الأولى — رقم مركزي لدار:** إعداد أبسط، وتتم إدارة التكامل من حساب واحد، بينما تحدد كل مساحة عمل المستلمين ونوع التذكيرات التي تريدها.
2. **الحالة الثانية — كل مالك يربط رقمه بنفسه:** تظهر الرسائل للمستأجر وكأنها صادرة من رقم المالك نفسه، ولكن هذا يحتاج إلى مسار إعداد وربط حساب WhatsApp Business لكل مساحة عمل، وإدارة اتصالات وصلاحيات مستقلة.

البرومبت أعلاه يعتمد **الحالة الأولى**، مع إبقاء التصميم قابلًا للتوسع مستقبلًا.

---

## 3. أول رسالة نختبرها

بعد إعداد الحساب والرقم واعتماد القالب، نبدأ برسالة واحدة إلى رقم اختبار تملكه، مرتبطة باستحقاق إيجار تجريبي.

### مثال على النص الذي سيصل للمستأجر:
```text
أهلًا أحمد، 👋
نذكّرك بأن موعد استحقاق إيجار شقة 101 في عمارة النور هو 25 نوفمبر.
المبلغ المتبقي للسداد: 5,000 جنيه.
إذا كنت قد سددت بالفعل، يُرجى تجاهل هذه الرسالة.
شكرًا لاستخدامك منصة دار.
```

- هذا مثال لمحتوى الرسالة، وليس قالبًا معتمدًا بعد؛ يجب تقديم النسخة النهائية إلى Meta للمراجعة قبل استخدامها كرسالة تلقائية تبدأها المنصة، حيثما تتطلب القواعد ذلك. [Facebook Developers](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview?utm_source=chatgpt.com)
- ابدأ بإعداد حساب Meta ورقم الاختبار، ثم اجعل الـ coding agent ينفّذ التكامل في وضع الاختبار أولًا؛ لا تفعّل الإرسال الحقيقي إلا بعد التحقق من القالب، وموافقة المستلم، وجدولة التذكير وإلغاء الرسالة عند سداد الإيجار.
