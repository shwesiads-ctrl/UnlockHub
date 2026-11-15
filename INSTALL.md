# UnlockHub — တပ်ဆင်နည်း (Fast install)

ဆိုဒ်ကို ပထမဆုံးဖွင့်တာနဲ့ **Installer** (`/install.php`) အလိုလို ပေါ်လာပါမယ် — အဆင့် ၄ ဆင့်:
**Server check → Database → Site & admin → Install**။ ပြီးရင် Installer က သူ့ကိုယ်သူ lock လုပ်တယ် (`storage/installed`)။

> PHP **8.3+** လိုတယ်။ Extension: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, curl, dom (+ zip, gd အကြံပြု)

---

## 0. Upload zip လုပ်ခြင်း (ကိုယ့် PC ပေါ်မှာ တစ်ခါ)

cPanel မှာ composer / npm မရှိတတ်လို့ `vendor/` နဲ့ `public/build/` ပါတဲ့ zip ကို PC ပေါ်မှာ လုပ်ပါ:

```powershell
cd C:\Users\MMA\UnlockHub
powershell -ExecutionPolicy Bypass -File scripts\build-release.ps1
```
→ `release\unlockhub-YYYYMMDD-HHMM.zip`

---

## A. XAMPP (Windows)

1. XAMPP Control Panel → **Apache** နဲ့ **MySQL** Start
2. `C:\xampp\php\php.ini` ထဲ `extension=zip`, `extension=gd`, `extension=fileinfo` ရှေ့က `;` ဖျက် → Apache Restart
3. Zip ကို `C:\xampp\htdocs\unlockhub\` ထဲ ဖြည်
4. Browser: **http://localhost/unlockhub/** → Installer ပေါ်လာမယ်
   - Database: Host `127.0.0.1`, User `root`, Password အလွတ် — Database ကို Installer က ဖန်တီးပေးတယ်
5. Background jobs: Installer နောက်ဆုံးစာမျက်နှာက command ကို Terminal မှာ run ထား
   ```
   C:\xampp\php\php.exe artisan schedule:work
   ```
   (အမြဲ run ချင်ရင် Task Scheduler — Installer မှာ ပြထားတယ်)

> Git နဲ့ ယူထားတဲ့ folder (`C:\Users\MMA\UnlockHub`) ကို XAMPP မှာ သုံးချင်ရင် `htdocs` ထဲ copy ကူးပြီး `composer install` + `npm run build` လုပ်ပြီးမှ ဖွင့်ပါ။

---

## B. cPanel (Shared hosting)

1. **Select PHP Version / MultiPHP Manager** → PHP 8.3+ ရွေး, extension တွေ tick
2. **MySQL® Databases** → Database အသစ် + User အသစ် ဖန်တီး → User ကို Database ထဲ **ALL PRIVILEGES** နဲ့ ထည့်
3. **File Manager** → Zip ကို upload → Extract
   - **ကောင်းဆုံး**: `/home/USER/unlockhub/` ထဲ ဖြည်ပြီး **Domains** မှာ Document Root ကို `/home/USER/unlockhub/public` ပြောင်း
   - **မပြောင်းနိုင်ရင်**: `public_html` ထဲ တိုက်ရိုက် ဖြည်လည်းရ — ပါလာတဲ့ root `.htaccess` က အကုန် `public/` ထဲ ပို့ပြီး `.env`, `vendor` စတာတွေ ဝင်ကြည့်လို့ မရအောင် ပိတ်ထားတယ်
4. **https://your-domain/** ဖွင့် → Installer
   - Database: Host `localhost`, Name/User က `cpuser_xxx` ပုံစံ
5. **Cron Jobs** → *Once Per Minute* → Installer ပြတဲ့ command ကို ကူးထည့်:
   ```
   cd /home/USER/unlockhub && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
   Queue (API order ပို့တာ) ကိုလည်း ဒီ cron ကပဲ run တယ် (`QUEUE_IN_SCHEDULER=true`)
6. **SSL/TLS Status → AutoSSL** နဲ့ HTTPS ဖွင့် (Push notification နဲ့ App install အတွက် လို)
7. `.env` ထဲ `MAIL_*` (SMTP) ဖြည့် — cPanel **Email Accounts** က mailbox သုံးလို့ရ

---

## ပြဿနာ ဖြေရှင်းနည်း

| ပြဿနာ | ဖြေရှင်းနည်း |
|---|---|
| 500 error / blank | `storage`, `bootstrap/cache` ကို Permission 755 ပေး; `storage/logs/laravel.log` ကြည့် |
| CSS မပေါ် | `public/build/` ပါလား စစ် (release zip သုံး) |
| Logo / avatar မပေါ် | `php artisan storage:link` (cPanel Terminal) သို့ Host ကို symlink ခွင့်တောင်း |
| ပြန် install ချင် | Database နဲ့ `.env` ကို backup ယူပြီး project directory အသစ်၊ database အသစ်မှာ install လုပ်ပါ။ `storage/installed` ဖျက်ရုံနဲ့ configured site ကို ပြန်ဖွင့်မပေးပါ |
| Install မပြီးဘဲ lock ကျသွား | Server administrator က local CLI ဖြင့် error ကို ပြင်ပြီး migration ဆက်လုပ်ပါ သို့မဟုတ် backup ပြန်တင်ပါ။ DB error ဖြစ်လည်း web installer ကို ပြန်မဖွင့်ပါ |
| Installer ကို ဖျက်ချင် | Install ပြီးရင် `public/install.php` ဖျက်လို့ရ (lock ထားပြီးသား) |
