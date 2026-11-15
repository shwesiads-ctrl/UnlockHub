# UnlockHub

GSM unlock service, tool credit နဲ့ activation တွေကို reseller တွေဆီ ရောင်းဖို့ platform ပါ။ Laravel 13 နဲ့ ရေးထားပါတယ်။

- Wallet + Myanmar payment (KBZPay / WavePay / AYA Pay / CB Pay) — ပြေစာ upload တင်ပြီး Admin က approve လုပ်ရ
- Order ကို **API** (DHRU Fusion supplier) နဲ့ ဒါမှမဟုတ် **Manual** နဲ့ ဆောင်ရွက်တယ်၊ API fail ရင် Manual queue ထဲ အလိုအလျောက် ရောက်တယ်
- Order ကို reject လုပ်ရင် ငွေ auto ပြန်အမ်းတယ်၊ success ဖြစ်ရင် point ရတယ်
- Point System (ရ / wallet credit အဖြစ် ပြောင်း / referral)
- User group လိုက် ဈေးကွဲ (Customer / Reseller / VIP)
- Flat UI ဒီဇိုင်း၊ Day / Night mode

## ⚡ Fast install (XAMPP / cPanel)

ဆိုဒ်ကို ပထမဆုံး ဖွင့်တာနဲ့ Web installer (`/install.php`) ပေါ်လာတယ် — Server check → Database → Site & admin → Install။ အသေးစိတ်: **[INSTALL.md](INSTALL.md)**။ Upload zip: `scripts\build-release.ps1`။

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
- **Welcome bonus** (အကောင့်ဖွင့်တာနဲ့)၊ **Profile ပြည့်စုံရင် bonus** (email, ဖုန်း, ပုံ, ဆိုင်နာမည်, မြို့ — တစ်ကြိမ်ပဲ)
- **Top-up bonus:** ငွေဖြည့်မှု approve ဖြစ်တိုင်း ပမာဏအလိုက် (ဥပမာ 10,000 Ks = 1 point — default ပိတ်ထား)
- **Daily check-in:** နေ့တိုင်း နှိပ်ရင် point ရ၊ ဆက်တိုက်နေ့တွေ (streak) ဆိုရင် ပိုရ
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

## Admin panel

| Menu | လုပ်လို့ရတာ |
|---|---|
| **Dashboard** | ဒီနေ့ ရောင်းအား/အမြတ်၊ ၇ ရက် chart၊ Pending payment၊ Manual queue၊ Supplier balance၊ Top service၊ လတ်တလော activity |
| **Users** | ရှာ / filter / sort၊ user တစ်ယောက်ချင်း — balance, point, ငွေဖြည့်/သုံးစွဲမှု, order, wallet မှတ်တမ်း၊ နာမည်/email/group/status/role ပြင်၊ **Wallet ငွေ ထည့်/နုတ်** (အကြောင်းပြချက်နဲ့)၊ 2FA reset၊ Password reset link ပို့ |
| **Groups & Pricing** | Group ထည့်/ပြင်/ဖျက်၊ Rank၊ Default group ပြောင်း (ဖျက်ရင် user တွေ default ကို ရွှေ့) |
| **Reports** | ရက်အပိုင်းအခြား (ဒီနေ့ / ၇ ရက် / ဒီလ / ပြီးခဲ့တဲ့လ / စိတ်ကြိုက်)၊ ရောင်းအား, cost, အမြတ်, အောင်မြင်နှုန်း, ငွေဖြည့်, user အသစ်၊ နေ့အလိုက် chart + table၊ Top service / customer / payment method၊ **CSV** |
| **Activity Log** | Admin ဘယ်သူ ဘာလုပ်ခဲ့လဲ (approve, order result, balance ပြင်, setting) |
| **Settings** | ဆိုဒ်နာမည်, Support hours, Contact email/phone/address, Social link, USD rate, Terms စာမျက်နှာ, Telegram |

**ဘာသာစကား:** Header က **မြန်မာ / English** ခလုတ်နဲ့ ပြောင်းလို့ရတယ် (login ဝင်ထားရင် မှတ်ထားမယ်)။ ဘာသာပြန်တွေက `lang/my.json` မှာ ရှိတယ် — Menu, Home, Services, Account menu, Status တွေ ပြန်ပြီးပြီ။ ကျန်တဲ့ စာမျက်နှာတွေကို `__('English text')` နဲ့ wrap လုပ်ပြီး `lang/my.json` ထဲ ထပ်ထည့်ရုံပါပဲ။

## Social Login (Google, Facebook, Discord, TikTok, Telegram)

Admin › **Settings** › Social login မှာ provider တစ်ခုချင်း ဖွင့်ပါ —
1. Provider ရဲ့ developer console မှာ app ဆောက် (link ပါပြီးသား)
2. Client ID / Secret ကို ကူးထည့်ပြီး ပြထားတဲ့ **Redirect URL** ကို အဲဒီ app ထဲ ထည့်
3. **Telegram:** @BotFather မှာ `/setdomain` နဲ့ ကိုယ့် domain ကို သတ်မှတ်ပြီး bot username ထည့် (HTTPS domain အစစ်မှာပဲ အလုပ်လုပ်)

- Login/Register စာမျက်နှာမှာ ခလုတ်တွေ ပေါ်လာမယ်၊ Account › Settings မှာ Connect / Disconnect လုပ်လို့ရတယ်
- Email တူတဲ့ အကောင့်ရှိပြီးသားဆိုရင် အလိုအလျောက် မပေါင်းဘူး (လုံခြုံရေး) — password နဲ့ ဝင်ပြီး Settings ကနေ connect လုပ်ရမယ်
- Google Authenticator ဖွင့်ထားသူကို social login နဲ့ ဝင်ရင်လည်း 2FA code တောင်းတယ်
- Telegram / TikTok က email မပေးလို့ အကောင့်ဖွင့်ပြီးရင် Profile မှာ email ထည့်ခိုင်းတယ်

## Profile & Logo

- **Account › My Profile** — Profile ပုံ upload၊ ဆိုင်နာမည်၊ ဖုန်း၊ မြို့၊ ဘာသာစကား၊ ငွေကြေး၊ Profile ပြည့်စုံမှု %
- **Admin › Settings** — Site logo နဲ့ browser tab icon upload

## Reseller API (DHRU Fusion compatible)

Customer က **Account › API Access** မှာ API key ထုတ်ပြီး သူ့ DHRU ဆိုဒ်ကနေ ကိုယ့်ဆိုဒ်ကို supplier အဖြစ် ချိတ်လို့ရတယ် —
- API URL = ကိုယ့်ဆိုဒ်လိပ်စာ (`/api/index.php` ကို DHRU က အလိုအလျောက် ထည့်), Username, API key
- `accountinfo`, `imeiservicelist`, `getimeiservicedetails`, `placeimeiorder`, `getimeiorder` ကို support လုပ်တယ်
- သူ့ group ဈေးနဲ့ wallet ကနေ နုတ်တယ်၊ Reject ရင် auto refund
- Key ကို hash နဲ့ပဲ သိမ်းတယ် (တစ်ခါပဲ ပြ)၊ IP whitelist ထည့်လို့ရတယ်၊ မှားရိုက်တာ များရင် ခဏပိတ်တယ်

> **Nginx သုံးရင်:** `location ~ \.php$` block က `/api/index.php` ကို ဖမ်းမသွားအောင် `location = /api/index.php { try_files $uri /index.php?$query_string; }` ကို အရင်ထည့်ပါ။

## PayPal / Stripe, SMTP, Social login စစ်ဆေးခြင်း

- **Admin › Settings › PayPal & Stripe** — Stripe (Card) နဲ့ PayPal key ထည့် → **Test keys** (✓ Tested ပြ)။ ငွေကြေး (USD), 1 USD = ? Ks, Min/Max, Fee %
  - Customer **Add Funds › Pay online** → Card/PayPal နဲ့ပေး → ပြန်လာတာနဲ့ Gateway API ကို ပြန်မေးပြီး ငွေဝင်မှ Wallet ထဲ အလိုအလျောက်ထည့် (Browser URL ကို မယုံ)
  - Stripe webhook (signature စစ်) + PayPal webhook + ၅ မိနစ်တိုင်း sweep (`payments:sync-online`) — Webhook က "ဘယ် payment" ကိုပဲ ပြော၊ ရလဒ်ကို API ကနေ ပြန်ဖတ်
  - ပမာဏ/ငွေကြေး မကိုက်ရင် မထည့် (Failed + Admin alert)၊ ဘယ်နှစ်ခါ confirm လာလာ တစ်ခါပဲ ထည့်၊ Reconciliation ကလည်း စစ်
  - **Admin › Payments › Card & PayPal** — စာရင်း, Check now
- **Admin › Settings › Email (SMTP)** — Host/Port/TLS/User/Password (encrypted)/From → **Send test email** (✓ Tested)
- **Social login › Test login** — Google/Facebook/Discord/TikTok ကို တကယ် sign-in လုပ်ကြည့်ပြီး ပြန်ရလာတဲ့ နာမည်/email ပြ (ဘယ်သူ့ကိုမှ login မဝင်/account မဖန်တီး)၊ Telegram က Bot token ↔ username စစ်။ ID/Secret ပြောင်းရင် "Not tested" ပြန်ဖြစ်

## ဖုန်း / PWA

- **App အဖြစ် ထည့်လို့ရ** — Android: Browser menu › *Install app* (Dashboard/Notifications မှာ **Install app** ခလုတ်ပေါ်)၊ iPhone: Share › *Add to Home Screen*
- **Admin › Settings › Mobile app (PWA)** — Icon (PNG/JPG တင်ရင် 192/512/maskable/Apple icon အလိုအလျောက်ထုတ်), အမည်, Theme colour, Push ဖွင့်/ပိတ်, Push keys ဖန်တီး
- Service worker (`public/sw.js`) က **static file (CSS/JS/icon) ပဲ cache** လုပ်တယ်။ Page, Wallet, Receipt, API key, ကိုယ်ရေးအချက်အလက် ဘာမှ offline cache ထဲ မသိမ်း (Account/Admin page တွေ `Cache-Control: no-store`)
- Internet မရှိရင် Offline စာမျက်နှာ + အနီရောင် banner၊ Form ပို့လို့မရ ("nothing was sent and nothing was charged") — offline order ကို ငွေနုတ်ပြီးလို့ ဘယ်တော့မှ မပြ
- Version အသစ်ထွက်ရင် "A new version is available — Update" ပြ
- **Push notification** — Account › Notifications › *Phone notifications › Turn on* (ခွင့်ပြုချက် တောင်း)။ Push ထဲမှာ data မပါ (phone က site ကို login နဲ့ နောက်ဆုံး notification ကို မေးတယ်)။ HTTPS လိုတယ် (localhost မှာ စမ်းလို့ရ)။ iPhone က Home Screen app ကနေမှ ရ
- ဖုန်းမှာ Account/Admin menu ကို ☰ နဲ့ ဖွင့်၊ Account အောက်ခြေမှာ Home / Order / Orders / Funds / Support bar၊ Public menu ☰၊ Input 16px (iPhone zoom မဖြစ်)၊ Bulk preview က card list

## Service အမှန်တကယ်ကြာချိန်

- နာရီတိုင်း (`services:delivery-stats`) Success order တွေရဲ့ **တင်ချိန် → ပြီးချိန်** ကို Service အလိုက်၊ Supplier အလိုက်၊ နောက်ဆုံး ၇ ရက် / ၃၀ ရက် တွက်တယ်: order အရေအတွက်, ပျမ်းမျှ, အလယ်တန်ဖိုး (median), အများစုကြာချိန် (25–75%), 90% ပြီးချိန်, နောက်ဆုံးတွက်ချိန်
- **Admin › Reports › Delivery times** — စာရင်း + ကြာနေတဲ့ open order စာရင်း
- Service › *Alert if open longer than (hours)* ထက်ကျော်တဲ့ Pending/In process order ကို ၁၅ မိနစ်တိုင်း စစ်ပြီး Admin ကို Telegram (order တစ်ခုကို တစ်ခါပဲ)
- Customer Service page မှာ order လုံလောက်မှ (default ≥ 10, setting `delivery.min_samples`) "**Estimate**… Not a guarantee" အနေနဲ့ပဲ ပြ၊ နည်းရင် ခန့်မှန်းချက် မပြ

## CSV / Excel Bulk Order

- Service စာမျက်နှာ › **Bulk from CSV / Excel** → Template (.xlsx / .csv) ဒေါင်း (Excel template က column အားလုံး Text ဖြစ်လို့ IMEI ရှေ့က 0 မပျောက်)
- File တင် (max 2 MB / 500 rows — setting `bulk.max_kb`, `bulk.max_rows`) → Column ကို IMEI/SN/field တွေနဲ့ ချိတ်ရွေး → Row တစ်ခုချင်း **Valid / Invalid / Duplicate** + အကြောင်းရင်း + line နံပါတ်
- စုစုပေါင်း order, ကျသင့်ငွေ, Wallet လက်ကျန်၊ ပြဿနာ row တွေကို CSV နဲ့ ဒေါင်း (ပြင်ပြီး ပြန်တင်)
- **Preview မှာ order မဖန်တီးဘူး** — Confirm နှိပ်မှ ဈေး/balance/duplicate ပြန်စစ်ပြီး Valid row တွေပဲ တင် (Confirm ၂ ခါနှိပ်လည်း တစ်ခါပဲ)
- .xlsx ကို package မလိုဘဲ PHP `zip` extension နဲ့ ဖတ်တယ် (php.ini မှာ `extension=zip` ဖွင့်ထားဖို့)

## Favourite + Order again

- Services စာရင်း / Service စာမျက်နှာမှာ ☆ နှိပ်ပြီး Favourite ထည့်/ဖြုတ် (page မပြောင်းဘဲ)
- Account Dashboard မှာ Favourite service ၈ ခု၊ **Account › Favourites** မှာ အကုန် + ရှာဖွေ
- Order detail / Bulk order စာရင်းက **Order again** — Service form ကို IMEI, email, username… (Bulk ဆို IMEI အကုန်) နဲ့ ဖြည့်ပေးတယ်၊ ပြင်ပြီးမှ တင်လို့ရ
- ဈေးအသစ်/အဟောင်း၊ စုစုပေါင်း၊ Wallet လက်ကျန် လုံလောက်မှု ပြတယ်။ Service ပိတ်ထား/Pause ဖြစ်နေရင် order မတင်ရ
- **Order again နှိပ်ရုံနဲ့ ငွေမနုတ်ဘူး** — Place order နှိပ်ပြီး confirm လုပ်မှ order အသစ်ဖြစ်

## ဈေးနှုန်းနဲ့ အမြတ်ကာကွယ်မှု (Profit guard)

- **Groups & Pricing** — Group တစ်ခုချင်းစီမှာ Markup (`%` သို့ `Ks` ပုံသေ) + အနည်းဆုံးအမြတ်၊ ဈေးအဝိုင်း (10/50/100/500/1000 Ks)၊ Cost ≥ ရောင်းဈေး ဖြစ်ရင် **Pause new orders** / Warn only၊ Auto ဈေးပြောင်းမှု X% ထက်ကြီးရင် Admin အတည်ပြုမှ
- **Service › Pricing** — **Manual** (ကိုယ်တိုင်ဈေး) / **Auto** (Group rule + cost လိုက်ပြောင်း)၊ Rule ဈေး preview၊ Supplier ဈေး × exchange rate နဲ့ နောက်ဆုံးပြင်ချိန်၊ Cost history
- Supplier cost ကို နာရီတိုင်း (`suppliers:sync-costs`) စစ်ပြီး ပြောင်းတိုင်း **မူလ cost → အသစ် cost, supplier ဈေး, rate, ဘယ်ကပြောင်းလဲ (sync/import/rate/manual), ဘယ်သူ** မှတ်တယ်။ Exchange rate ပြင်ရင် ချိတ်ထားတဲ့ service cost တွေ ပြန်တွက်တယ်
- **Admin › Profit guard** — အရှုံး/အမြတ်နည်း service စာရင်း၊ Pause ထားတဲ့ service၊ အတည်ပြုစောင့်နေတဲ့ ဈေးပြောင်းမှု (Apply / Keep old)၊ Cost change log
- Pause ဖြစ်နေတဲ့ service ကို Website/API နှစ်ခုလုံးက order မလက်ခံ၊ ဈေးပြန်မြင့်/cost ပြန်ကျရင် အလိုအလျောက် ပြန်ဖွင့်
- တင်ပြီးသား order ရဲ့ ဝယ်ဈေးနဲ့ cost မှတ်တမ်း ဘယ်တော့မှ မပြောင်း

## Wallet စာရင်းညှိစစ်ခြင်း (Reconciliation)

- **Admin › Reconciliation** — နေ့စဉ် 02:30 (`wallet:reconcile`) နဲ့ "Run check now"
- စစ်တာတွေ: User balance ≠ transaction စုစုပေါင်း, running balance ပြတ်နေတာ, Rejected/Cancelled order refund မရတာ, Refund ၂ ခါ, Success order ကို refund ပေးမိတာ, Order ငွေ ၂ ခါနုတ်/မနုတ်မိတာ, Approved top-up မဝင်/၂ ခါဝင်/ပမာဏမတူတာ, reference မရှိတဲ့ transaction
- Deposit / Order debit / Refund / Point redemption / Admin adjustment အမျိုးအစားအလိုက် စုစုပေါင်း (ရက်ရွေး)၊ CSV report ထုတ်
- ပြဿနာတွေ့ရင် Finance/Owner ကို Notification + email + Telegram
- **Balance ကို အလိုအလျောက် မပြင်ဘူး** — ဝန်ထမ်းတစ်ယောက်က အကြောင်းရင်းနဲ့ correction (Credit / Debit / Set balance = ledger) တင်ပြ၊ **တခြားတစ်ယောက်** Approve မှ အသုံးချ၊ ဘယ်သူတင်ပြ/ဘယ်သူ approve မှတ်ထား။ "Checked — no correction" လည်း အကြောင်းရင်းနဲ့ မှတ်လို့ရ

## Support Ticket

- **Customer (Account › Support)**: Ticket ဖွင့် (Order / Payment / Account / Other)၊ Order detail ထဲက **Get help with this order** ကနေလည်း ဖွင့်လို့ရ၊ စာ + screenshot/file (၃ ခု၊ JPG/PNG/WebP/PDF/TXT၊ 5 MB)
- Status: **Open → In Progress → Waiting for Customer → Resolved → Closed**။ Resolved ဖြစ်ပြီး ၇ ရက်အတွင်း (setting `tickets.reopen_days`) ပြန်ဖွင့်/ပြန်ရေးလို့ရ၊ ပြီးရင် `tickets:close-resolved` (နေ့စဉ်) က Closed လုပ်တယ်
- **Admin › Tickets**: Needs reply / Status / Priority / Topic / Assigned (Me, Unassigned) filter၊ ticket no.·subject·username·order no.·IMEI နဲ့ ရှာ၊ Priority, Assign, **Internal note** (customer မမြင်ရ), **Saved replies** (`{name}` `{ticket}` `{order}`)
- Customer က ကိုယ့် ticket နဲ့ internal မဟုတ်တဲ့ attachment ကိုပဲ ကြည့်လို့ရ (တခြားသူ့ဟာ 404)၊ File တွေ private disk ပေါ်မှာ
- Staff ပြန်စာ → customer ကို notification + email၊ Customer ticket/ပြန်စာ → Admin Telegram

## Staff (ဝန်ထမ်း) ခွင့်ပြုချက်

- **Admin › Staff** (Owner ပဲ ဝင်လို့ရ): ရှိပြီးသား account ကို Staff အဖြစ်ထည့် (Google Authenticator ဖွင့်ထားမှ)၊ Role ပြောင်း၊ ဖယ်ရှား
- Role: **Owner** (အကုန်), **Finance** (Payment, Wallet, Reconcile, Reports), **Order Operator** (Order ဆောင်ရွက်), **Support** (Ticket, customer/order ကြည့်), **Content Editor** (Blog/KB/Announcement/Popup)
- Role တစ်ခုချင်းစီ ဘာလုပ်ခွင့်ရှိလဲ (View / Process / Approve / Export…) ကို checkbox table နဲ့ ပြင်လို့ရ။ Supplier API key, Settings (social/Telegram secret), Staff စီမံခြင်း က Default အားဖြင့် Owner ပဲ
- Menu ဖျောက်ရုံမက **URL တိုက်ရိုက်ခေါ်လည်း 403** (route တိုင်းကို permission နဲ့ map ထားတယ်၊ map မထားတဲ့ route အသစ်က Owner ပဲ)
- အပြောင်းအလဲတိုင်း Activity Log ထဲ ဘယ်သူ ဘာပြောင်းလဲ မှတ်တယ်
- **Wallet approvals** — Staff › threshold (ဥပမာ 500,000 Ks) ထက်များတဲ့ manual wallet add/remove က ဒုတိယ ဝန်ထမ်း Approve မှ ဝင်တယ် (ကိုယ့်ဟာကိုယ် approve လို့မရ)

## Order / Payment ထပ်မဖြစ်အောင် ကာကွယ်ခြင်း

- **Order request token** — Order form တစ်ခါဖွင့်တိုင်း token တစ်ခု ပါတယ်။ ခလုတ်နှစ်ခါနှိပ်တာ၊ Refresh/Resend၊ connection ပြတ်ပြီး ပြန်ပို့တာ ဖြစ်လည်း order နဲ့ ငွေနုတ်တာ **တစ်ကြိမ်ပဲ** ဖြစ်တယ် (`order_requests` table, unique)
- **API** — Client က `REFERENCE` ပို့ရင် အဲဒါကို token အဖြစ်သုံးတယ်၊ ထပ်ပို့ရင် ပထမ order ရဲ့ REFERENCEID ကိုပဲ ပြန်ပေးတယ်
- **Order ထပ်တင်မှု စစ်ခြင်း** — User + Service + IMEI/SN တူတဲ့ Pending / In process order ရှိရင် order နံပါတ်နဲ့ status ပြတယ်။ Service › *Same IMEI/SN ordered again* မှာ ရွေး:
  - **Warn** — သတိပေးပြီး "place anyway" ဆိုတဲ့ checkbox ကို အမှန်ခြစ်မှ ဆက်တင်ခွင့်ပေးတယ် (API ကတော့ ငြင်းတယ်)
  - **Block** — ဘယ်တော့မှ ခွင့်မပြု (Website + API)
  - **Allow** — မစစ်ဘူး
- Bulk list ထဲမှာ ထပ်နေတဲ့ line တွေကို တစ်ခါပဲ order တင်ပြီး ဘယ် line တွေ ထပ်နေလဲ ပြောပြတယ်
- **Top-up** — Transaction ID တူတာကို တစ်ပြိုင်နက် နှစ်ခါပို့ရင် တစ်ခုပဲ ဝင်တယ် (lock)၊ Approve က တစ်ကြိမ်ပဲ credit ပေါင်းတယ်
- **Supplier** — Job နှစ်ခု တစ်ပြိုင်နက် run လည်း supplier ဆီ တစ်ခါပဲ ပို့တယ် (atomic claim)။ Connect မရတာ (DNS / refused) ကိုပဲ အလိုအလျောက် ပြန်ကြိုးစားတယ်။ Reply မရ (timeout) ရင် supplier မှာ order ဖြစ်ပြီးသားဖြစ်နိုင်လို့ ပြန်မပို့ဘဲ Manual queue ကို ပို့ပြီး "check the supplier panel" လို့ မှတ်ပေးတယ်
- Tests: `php artisan test` (SQLite memory DB)

## Popups (Welcome + Service)

- **Admin › Popups** — ဆိုဒ်ဖွင့်ရင် ပေါ်မယ့် Welcome popup (စည်းကမ်း၊ WhatsApp/Telegram ခလုတ်၊ ငွေဖြည့်နည်း…)
  - ဘယ်သူမြင်မလဲ (အားလုံး / Guest / Login ဝင်ထားသူ)၊ ဘယ်မှာ (Home / စာမျက်နှာတိုင်း)၊ ဘယ်နှစ်ခါ (တစ်ခါဝင်ရင်တစ်ခါ / တစ်ရက်တစ်ခါ / တစ်ခါတည်း / အမြဲ)၊ ပေါ်မယ့်အချိန် (စက္ကန့်)
- **Admin › Services › Edit › Service popup** — Service တစ်ခုချင်းစီအတွက် popup (Service page ဖွင့်ရင် ပေါ်မယ် / "Service info & rules" ခလုတ်နှိပ်မှ ပေါ်မယ်)
- Block တွေနဲ့ တည်ဆောက်တယ်: Section heading, Paragraph, List (• ✅ ❌ 1.), Coloured box (တစ်ဝက်စီ ဘေးချင်းကပ်လို့ရ), Steps, Chips grid (နိုင်ငံ/Model), Info table (checker sample), Buttons
- Template ၁၀ ခု: Welcome, IMEI service, Tool/software, Guide, Region chips, Clean only, FMI off, Carrier unlock, Code service, Blank — "copy from a service" နဲ့ တခြား service ကနေ ကူးလို့ရ
- ညာဘက်မှာ Live preview ပေါ်တယ်။ `**စာ**` = Bold, `{name}` = user နာမည်, `{site}` = ဆိုဒ်နာမည်
- Button URL ကို `#order` ထည့်ရင် popup ပိတ်ပြီး Order form ကို ရောက်သွားမယ်
- လုံခြုံရေး: စာအားလုံး escape လုပ်တယ် (HTML/JS ထည့်လို့မရ)၊ Link တွေက https / mailto / tel / tg / `/` path ပဲ ခွင့်ပြုတယ်

## Statement (PDF + Email)

- **Account › Statement** — ရက်အပိုင်းအခြား (ဒီလ / ပြီးခဲ့တဲ့လ / ၃၀ ရက် / ဒီနှစ် / စိတ်ကြိုက်) နဲ့ Type ရွေးပြီး Opening / Money in / Money out / Closing balance ကို ကြည့်လို့ရတယ်
  - **Download PDF** — Logo, customer အချက်အလက်, စာရင်း, Type အလိုက် summary, စာမျက်နှာ နံပါတ် ပါတယ်
  - **Email PDF** — ကိုယ့် email ကို PDF attachment နဲ့ ပို့တယ်
- **Admin › Users › (user)** — ရက်ရွေးပြီး အဲဒီ user ရဲ့ statement ကို PDF download / email ပို့
- **လစဉ်** — လတိုင်း ၁ ရက်နေ့ မနက် ၈ နာရီမှာ ပြီးခဲ့တဲ့လ statement ကို customer တွေဆီ အလိုအလျောက် email ပို့တယ် (Admin › Settings › Monthly statements မှာ ဖွင့်/ပိတ်)။ `schedule:work` / cron run နေမှ အလုပ်လုပ်တယ်
  - ကိုယ်တိုင် ပို့ချင်ရင်: `php artisan statements:monthly --month=2026-10`

> PDF ကို `dompdf/dompdf` နဲ့ ထုတ်တယ် (`composer install` မှာ ပါလာမယ်)။ PDF ထဲမှာ English/ဂဏန်းတွေ မှန်မှန်ပေါ်ပေမဲ့ dompdf က မြန်မာစာ (Unicode ပေါင်းစပ်စာလုံး) ကို မှန်မှန် မဆွဲနိုင်လို့ description တွေကို English နဲ့ပဲ သိမ်းထားပါတယ်။

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
- [x] **အဆင့် ၁၀** — Admin panel အပြည့်အစုံ (Users, Groups, Reports, Activity log, Site settings, Dashboard, MM/EN)
- [ ] အဆင့် ၁၁ — Test လုပ်ပြီး Deploy

## License

Apache-2.0
