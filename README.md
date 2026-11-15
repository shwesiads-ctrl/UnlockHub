# UnlockHub

GSM unlock service, tool credit နဲ့ activation တွေကို reseller တွေဆီ ရောင်းဖို့ platform ပါ။ Laravel 13 နဲ့ ရေးထားပါတယ်။

- Wallet + Myanmar payment (KBZPay / WavePay / AYA Pay / CB Pay) — ပြေစာ upload တင်ပြီး Admin က approve လုပ်ရ
- Order ကို **API** (DHRU Fusion supplier) နဲ့ ဒါမှမဟုတ် **Manual** နဲ့ ဆောင်ရွက်တယ်၊ API fail ရင် Manual queue ထဲ အလိုအလျောက် ရောက်တယ်
- Order ကို reject လုပ်ရင် ငွေ auto ပြန်အမ်းတယ်၊ success ဖြစ်ရင် point ရတယ်
- Point System (ရ / wallet credit အဖြစ် ပြောင်း / referral)
- User group လိုက် ဈေးကွဲ (Customer / Reseller / VIP)
- Flat UI ဒီဇိုင်း၊ Day / Night mode

## လိုအပ်ချက်

- PHP 8.3+ (extension: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`)
- Composer 2
- MySQL 8 / MariaDB 10.6+ (စမ်းရုံဆို SQLite နဲ့လည်း ရတယ်)
- Node 20+ (Tailwind / Vite အတွက်)

## Install လုပ်နည်း

```bash
git clone https://github.com/shwesiads-ctrl/UnlockHub.git
cd UnlockHub

composer install
cp .env.example .env
php artisan key:generate

# .env ထဲမှာ DB_*, ADMIN_EMAIL, ADMIN_PASSWORD ကို ပြင်ပါ
php artisan migrate --seed
php artisan storage:link

npm install
npm run build        # development အတွက်ဆို: npm run dev

php artisan serve    # http://localhost:8000
```

Seeder က ထည့်ပေးတာတွေ:
- Admin account (`.env` ထဲက `ADMIN_EMAIL` / `ADMIN_PASSWORD` အတိုင်း)
- User group ၃ ခု — Customer (default), Reseller, VIP
- Payment method ၄ ခု — **ပိတ်ထားတယ်**၊ အကောင့်နံပါတ်ထည့်ပြီးမှ ဖွင့်ပါ
- နမူနာ service တွေ — **Hidden** လုပ်ထားတယ်၊ ဈေးသတ်မှတ်ပြီးမှ Active လုပ်ပါ
- Point setting: 1,000 Ks သုံးတိုင်း 1 point ရ၊ 1 point = 10 Ks၊ အနည်းဆုံး 100 point မှ ပြောင်းလို့ရ

## Login လုပ်ပုံ

1. Username (သို့) email + password ရိုက်ထည့်တယ်
2. ဒီ browser ကို "Trust this device" လုပ်ထားပြီးသားဆိုရင် → ချက်ချင်း ဝင်လို့ရတယ်
3. မဟုတ်ရင် → Google Authenticator ဖွင့်ထားရင် app ထဲက code ကို ထည့်ရမယ်၊ မဖွင့်ထားရင် email ကို ပို့တဲ့ ၆ လုံး code ကို ထည့်ရမယ်
4. "Trust this device?" ကို အမှန်ခြစ်ထားရင် ရက် ၃၀ ကြာအောင် ဒီအဆင့်ကို ကျော်လို့ရမယ်

> **Local မှာ စမ်းရင်:** `.env` ထဲမှာ `MAIL_MAILER=log` ဖြစ်နေတဲ့အတွက် email code က `storage/logs/laravel.log` ထဲကို ရောက်ပါတယ်။ အဲဒီဖိုင်ရဲ့ အောက်ဆုံးမှာ ရှာကြည့်ပါ။
> Production မှာ email တကယ်ပို့ဖို့ `MAIL_*` တန်ဖိုးတွေကို SMTP (Gmail, Mailgun, Brevo …) နဲ့ ပြောင်းပါ။

## Service တစ်ခု ထည့်နည်း

1. **Admin › Services › Manage groups** — Group (Samsung, Tool Activations …) ကို အရင် ထည့်ပါ
2. **Admin › Services › + New service** —
   - နာမည်၊ Group၊ Delivery time
   - **Order fields** — user ဖြည့်ရမယ့် field (IMEI, Username …)။ Bulk order လက်ခံမယ်ဆိုရင် field တစ်ခုကို "Bulk (one per line)" လို့ အမှန်ခြစ်ပါ
   - **Pricing** — Cost နဲ့ Default price ထည့်ပါ။ Group လိုက် ဈေးကွဲချင်ရင် ထည့်ပါ၊ မထည့်ရင် Default price ကို သုံးမယ်။ "Fill prices from cost" ခလုတ်က profit % နဲ့ ဈေးကို အလိုအလျောက် တွက်ပေးတယ်
   - **Processing mode** — Manual (သို့) API
3. Status ကို **Active** ပြောင်းလိုက်ရင် `/services` စာမျက်နှာမှာ ပေါ်လာမယ်။ ဈေး 0 ဖြစ်နေရင် Active လုပ်လို့ မရပါ

Seeder က ထည့်ပေးထားတဲ့ နမူနာ service တွေက Hidden ဖြစ်နေလို့ ဈေးထည့်ပြီး Activate လုပ်ပါ။

## ငွေဖြည့်စနစ် (Add Funds)

**Admin က တစ်ခါပဲ ပြင်ဆင်ရမယ်:** Admin › Payments › **Payment methods** မှာ KBZPay / WavePay / AYA Pay / CB Pay တစ်ခုချင်းစီအတွက် အကောင့်နာမည်၊ ဖုန်းနံပါတ်၊ QR ပုံ (ရှိရင်)၊ အနည်းဆုံး/အများဆုံး ပမာဏ ထည့်ပြီး **On** ကို အမှန်ခြစ်ပါ။ QR ပုံပေါ်ဖို့ `php artisan storage:link` ကို တစ်ခါ run ထားရပါမယ်။

**User:** Account › Add Funds → payment method ရွေး → ငွေလွှဲ → ပမာဏ + Transaction ID + ပြေစာ screenshot တင် → Pending

**Admin:** Payments → **Review** → ပြေစာကို payment app ထဲက မှတ်တမ်းနဲ့ တိုက်စစ် →
- **Approve & credit** — Wallet ထဲ ငွေဝင်ပြီး Statement မှာ မှတ်တမ်းဝင်တယ်။ လွှဲတဲ့ပမာဏ မတူရင် credit ပမာဏကို ပြင်လို့ရတယ်
- **Reject** — အကြောင်းပြချက်ကို user မြင်ရတယ်
- ပြီးတာနဲ့ နောက် Pending တစ်ခုကို အလိုအလျောက် ဖွင့်ပေးတယ်

လုံခြုံရေး: Transaction ID တူတာကို ထပ်တင်လို့မရ (reject ဖြစ်ထားတာကလွဲရင်)၊ user တစ်ယောက် Pending ၅ ခုထက် မပိုရ၊ ပြေစာပုံတွေကို private storage မှာ သိမ်းထားလို့ ပိုင်ရှင်နဲ့ admin ပဲ ကြည့်လို့ရတယ်၊ Approve ကို တစ်ကြိမ်ပဲ လုပ်လို့ရတယ် (row lock)။

## Order လုပ်ပုံ

**User:** Service စာမျက်နှာ → field တွေ ဖြည့် (Bulk ဆိုရင် တစ်ကြောင်းကို တစ်ခု) → **Place order**
- Wallet ထဲက ငွေကို ချက်ချင်း နုတ်တယ် (Bulk ဆိုရင် အရေအတွက်အလိုက် ပေါင်းတွက်)၊ ငွေမလုံလောက်ရင် order မတင်ရဘူး
- IMEI ကို Luhn check နဲ့ စစ်တယ်၊ Bulk မှာ မှားတဲ့စာကြောင်းကို "Line 3: …" လို့ ပြတယ်၊ ထပ်နေတာတွေကို ဖယ်ပေးတယ်
- Order History မှာ filter / ရှာ / CSV download လုပ်လို့ရတယ်။ Service က ခွင့်ပြုထားရင် Pending ဖြစ်နေတုန်း cancel လုပ်လို့ရတယ်

**Status:** Pending → In Process → **Success** (point ရ) / **Rejected** (ငွေ auto ပြန်အမ်း) / Cancelled (ငွေပြန်အမ်း)

**Admin › Manual Queue** (အဟောင်းဆုံးကို အရင်ပြ):
1. Order ကိုဖွင့် → IMEI ကို Copy ကူး → supplier / tool မှာ လုပ်
2. Result (code) ထည့် → **Mark success**၊ မရရင် အကြောင်းပြချက်ထည့် → **Reject & refund**
3. နောက် order ကို အလိုအလျောက် ဖွင့်ပေးတယ်။ Pending အများကြီးကို တစ်ခါတည်း "In Process" လုပ်လို့ရတယ်

**API mode service:** Order ကို queue job (`SubmitOrderToSupplier`) နဲ့ supplier ဆီ ပို့တယ်။ ပို့မရရင် Manual queue ထဲ "API failed" အမှတ်နဲ့ ရောက်လာမယ် (Fallback ပိတ်ထားရင်တော့ auto reject + refund လုပ်မယ်)။ DHRU API driver ကို အဆင့် ၆ မှာ ထည့်ပါမယ်။

## Supplier API (DHRU Fusion)

1. **Admin › Suppliers & API › + Add supplier** — Supplier ဆိုဒ်လိပ်စာ (`https://supplier.com` လောက်ဆို ရပြီ)၊ API username၊ API access key၊ ငွေကြေး နဲ့ "1 USD = ? Ks" ထည့် → **Save & test connection**။ အောင်မြင်ရင် balance ပေါ်လာမယ်
   - "Authentication Failed" ဆိုရင် supplier ဆိုဒ်မှာ ကိုယ့် server IP ကို whitelist ထည့်ရတာ များပါတယ်
2. **Browse & import services** — Supplier ရဲ့ service တွေကို group လိုက် ပြမယ်။ ရွေးပြီး Profit % နဲ့ Import လုပ်ရင် cost/price ကို Ks နဲ့ တွက်ပြီး API mode service အဖြစ် ဆောက်ပေးမယ် (IMEI service ဆိုရင် IMEI field + Bulk ပါပြီးသား)
3. Import ပြီးရင် service တစ်ခုချင်း ဖွင့်ပြီး order field နဲ့ group ဈေးကို စစ်ပါ၊ ပြီးရင် Active လုပ်ပါ
4. **Sync costs** — Supplier ဈေးပြောင်းရင် cost ကို update လုပ်ပေးမယ်

**Order စီးဆင်းပုံ:** User order တင် → queue job က supplier ဆီ ပို့ (`In Process` + supplier ref) → scheduler က ၅ မိနစ်တစ်ခါ result ကို လှမ်းမေး → Success ဆိုရင် code ကို user ဆီ ပို့ပြီး point ပေး၊ Reject ဆိုရင် ငွေပြန်အမ်း။ ပို့မရရင် Manual queue ထဲ "API failed" အဖြစ် ရောက်မယ်။

**Local မှာ run ဖို့ (terminal ၃ ခု):**
```powershell
php artisan serve
php artisan queue:work      # API order တွေကို supplier ဆီ ပို့တယ်
php artisan schedule:work   # result စစ်တယ်၊ balance update လုပ်တယ်
```
> Worker မ run ချင်ရင် `.env` မှာ `QUEUE_CONNECTION=sync` ထားလို့ရတယ် (order တင်တဲ့အချိန်မှာပဲ supplier ဆီ တန်းပို့မယ်၊ နည်းနည်းနှေးမယ်)။
> Server ပေါ်မှာဆိုရင် cron: `* * * * * php /path/artisan schedule:run` နဲ့ `queue:work` ကို supervisor နဲ့ run ပါ။

Field key တွေက supplier ဆီ ဒီလို ရောက်တယ်: `imei`→IMEI, `sn`/`serial`→SN, `modelid`, `providerid`, `networkid`, `pin`, `kbh`, `mep`, `prd`, `type`, `reference`, `qnt`။ တခြား key တွေ (ဥပမာ `username`) ကို `CUSTOMFIELD` (base64 JSON) နဲ့ ပို့တယ်။

## Point System

| ဘယ်လိုရမလဲ | ဘယ်လောက် |
|---|---|
| Order success တစ်ခုစီ | Service မှာ သတ်မှတ်ထားတဲ့ point (မသတ်မှတ်ရင် 1,000 Ks = 1 point) |
| မိတ်ဆွေကို ဖိတ်ခေါ် — သူ့ရဲ့ ပထမဆုံး order success ဖြစ်ရင် | 50 point (မိတ်ဆွေ တစ်ယောက်ကို တစ်ကြိမ်ပဲ) |
| Admin က ပေးတာ | Admin › Points › Adjust |

- **Redeem:** Account › Points မှာ point ကို wallet credit အဖြစ် ပြောင်းလို့ရတယ် (default: 1 point = 10 Ks၊ အနည်းဆုံး 100 point)
- **Group auto-upgrade:** Admin › Points › Rules မှာ Reseller / VIP အတွက် "lifetime points" ကန့်သတ်ချက် ထည့်ထားရင် အဲဒီ point ပြည့်တာနဲ့ user ကို အဲဒီ group ကို အလိုအလျောက် တင်ပေးမယ် (တက်ပဲတက်မယ်၊ မကျဘူး)
- Reject / Cancel ဖြစ်တဲ့ order တွေက point မရပါ
- Rule အားလုံးကို Admin › **Points** မှာ ပြင်လို့ရတယ်

## Content စာမျက်နှာများ

| URL | ဘာလဲ | Admin မှာ ပြင်ရန် |
|---|---|---|
| `/kb` | Knowledge Base (လမ်းညွှန်ဆောင်းပါး၊ ရှာလို့ရ) | Content › Knowledge Base |
| `/blog` | Blog (cover ပုံပါ) | Content › Blog |
| `/testimonials` | Review — login ဝင်ထားသူ ရေးလို့ရ၊ Admin approve မှ ပေါ် | Content › Testimonials |
| `/downloads` | Driver / tool ဖိုင် (link သို့ upload)၊ download အရေအတွက် မှတ် | Content › Downloads |
| `/track-order` | Order နံပါတ်နဲ့ status စစ် (IMEI ကို ဖုံးပြ၊ result ကို ပိုင်ရှင်ပဲ မြင်) | — |
| `/contact` | Contact form → `contact.email` setting (သို့) `ADMIN_EMAIL` ဆီ email ပို့ | — |
| `/rss` | Blog post အသစ် + service အသစ် RSS feed | — |
| (အပေါ်ဆုံး bar) | Announcement — အရောင်၊ စ/ဆုံး ရက် သတ်မှတ်လို့ရ | Content › Announcements |

ဆောင်းပါးတွေကို **Markdown** နဲ့ ရေးပါ (`## ခေါင်းစဉ်`, `**bold**`, `- list`, `[link](https://…)`)။ Publish date ကို အနာဂတ်ထားရင် အဲဒီနေ့ရောက်မှ ပေါ်မယ်။

## Notification

**User (ဆိုဒ်ထဲ 🔔 + Email):**
- Order Success (result ပါ) / Reject (ငွေပြန်အမ်းကြောင်း)
- Bulk order — item တစ်ခုချင်းစီ email မပို့ဘဲ အားလုံးပြီးမှ summary email တစ်စောင်ပဲ ပို့
- ငွေဖြည့်မှု Approve / Reject
- Referral bonus / Admin ပေးတဲ့ point (ဆိုဒ်ထဲမှာပဲ ပြ)
- Email မလိုချင်ရင် Account › Settings မှာ ပိတ်လို့ရတယ်

**Admin (Telegram):** Admin › **Settings** မှာ bot token နဲ့ chat ID ထည့် → **Send test message**
- ပြေစာအသစ်၊ Manual order အသစ်၊ API order fail၊ Supplier balance နည်း၊ Review အသစ်၊ Contact message — တစ်ခုချင်း ဖွင့်/ပိတ်လို့ရ
- Bot token ကို encrypt လုပ်ပြီး သိမ်းတယ်

> Email တကယ်ပို့ဖို့ `.env` မှာ `MAIL_MAILER=smtp` နဲ့ `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` ကို ထည့်ပါ (Gmail ဆိုရင် App Password သုံး)။ Email / Telegram ပို့မရရင်လည်း order နဲ့ payment ကို မထိခိုက်ပါ။

## Project ဖွဲ့စည်းပုံ

| နေရာ | ဘာရှိလဲ |
|---|---|
| `app/Models` | User, UserGroup, Service, ServiceCategory, ServiceField, ServicePrice, Supplier, Order, WalletTransaction, PointTransaction, PaymentMethod, PaymentRequest, Post, Setting … |
| `app/Services/WalletService.php` | `users.balance` ကို ပြောင်းလို့ရတဲ့ တစ်ခုတည်းသောနေရာ (row lock + transaction log) |
| `app/Services/PointService.php` | Point ပေါင်း / နုတ် / wallet အဖြစ်ပြောင်း |
| `app/Rules/Imei.php` | IMEI ၁၅ လုံး + Luhn check |
| `database/migrations` | Database schema အပြည့်အစုံ |
| `resources/css/app.css` | Design token (Day/Night) နဲ့ Flat component တွေ (`.btn`, `.card`, `.badge-*` …) |
| `resources/views/layouts` | `app` (user ဘက်) နဲ့ `admin` layout |

## Database (အကျဉ်း)

- **users** — balance, points, group, role (user/admin), status, 2FA, referral
- **service_categories → services → service_fields / service_prices** — service အမျိုးအစား (imei / server / remote)၊ order မှာ ဖြည့်ရမယ့် field၊ group လိုက်ဈေး၊ processing mode (manual/api)၊ supplier
- **orders** — IMEI တစ်ခုကို row တစ်ခု (bulk order တွေက `batch_id` တူတူ)၊ status: pending → in_process → success / rejected
- **wallet_transactions / point_transactions** — balance နဲ့ point ပြောင်းတိုင်း မှတ်တမ်းဝင်
- **payment_methods / payment_requests** — ငွေဖြည့်တာ၊ ပြေစာ၊ approve/reject
- **suppliers** — DHRU API ချိတ်ဆက်မှု (API key ကို encrypt လုပ်ထား)
- **posts / testimonials / downloads / announcements / settings / activity_logs**

## လုပ်ငန်းအဆင့်များ

- [x] **အဆင့် ၁** — Project setup, Database schema, Design system (Day/Night), Seeder
- [x] **အဆင့် ၂** — Register / Login / Email OTP / Trust device / Google 2FA / Password reset / Account settings
- [x] **အဆင့် ၃** — Service catalog နဲ့ Reseller pricing စာမျက်နှာ၊ Admin ဘက်က Group / Service စီမံခန့်ခွဲမှု
- [x] **အဆင့် ၄** — Wallet နဲ့ Myanmar payment (ပြေစာ upload → Admin approve)၊ Statement၊ Payment method settings
- [x] **အဆင့် ၅** — Order system (API / Manual, bulk, auto refund)၊ Manual queue
- [x] **အဆင့် ၆** — Supplier API (DHRU Fusion)၊ Service import၊ Order result auto-check
- [x] **အဆင့် ၇** — Point system (Redeem၊ Referral bonus၊ Group auto-upgrade၊ Admin adjust)
- [x] **အဆင့် ၈** — Content စာမျက်နှာ (KB, Blog, Testimonials, Downloads, Track Order, Contact, RSS) + Admin Content
- [x] **အဆင့် ၉** — Notification (ဆိုဒ်ထဲ bell + Email + Admin Telegram bot)
- [ ] အဆင့် ၁၀ — Admin panel အပြည့်အစုံ
- [ ] အဆင့် ၁၁ — Test လုပ်ပြီး Deploy

## License

Apache-2.0
