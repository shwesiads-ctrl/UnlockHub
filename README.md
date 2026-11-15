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
- [ ] အဆင့် ၂ — Register / Login / Email OTP / Trust device / Google 2FA
- [ ] အဆင့် ၃ — Service catalog နဲ့ Reseller pricing စာမျက်နှာ
- [ ] အဆင့် ၄ — Wallet နဲ့ Myanmar payment (ပြေစာ upload → Admin approve)
- [ ] အဆင့် ၅ — Order system (API / Manual, bulk, auto refund)
- [ ] အဆင့် ၆ — Supplier API (DHRU Fusion)
- [ ] အဆင့် ၇ — Point system
- [ ] အဆင့် ၈ — Content စာမျက်နှာ (KB, Blog, Testimonials, Downloads, Track Order)
- [ ] အဆင့် ၉ — Notification (Email + Telegram bot)
- [ ] အဆင့် ၁၀ — Admin panel အပြည့်အစုံ
- [ ] အဆင့် ၁၁ — Test လုပ်ပြီး Deploy

## License

Apache-2.0
