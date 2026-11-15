<?php
/**
 * UnlockHub — fast web installer (XAMPP, cPanel, any Apache/Nginx host).
 *
 * Plain PHP until the last step, so it works before .env / APP_KEY / database exist.
 * Steps: 1 requirements → 2 database → 3 site & admin → 4 install → done.
 * Locks itself (storage/installed) when finished, or when the app is already installed.
 */
declare(strict_types=1);

const MIN_PHP = '8.3.0';
$ROOT = dirname(__DIR__);
$LOCK = $ROOT.'/storage/installed';
$ENV = $ROOT.'/.env';
require_once $ROOT.'/app/Support/InstallerState.php';

session_name('uh_installer');
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// ---------------------------------------------------------------- helpers
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function envValue(string $file, string $key): ?string
{
    if (! is_file($file)) {
        return null;
    }
    if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($file), $m)) {
        return trim($m[1], " \t\"'\r");
    }

    return null;
}

function envQuote(string $v): string
{
    return $v === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-]+$/', $v) ? $v : '"'.addcslashes($v, '"\\$').'"';
}

function pdoFor(array $db, bool $withDb = true): PDO
{
    $dsn = 'mysql:host='.$db['host'].';port='.(int) $db['port'].($withDb ? ';dbname='.$db['name'] : '').';charset=utf8mb4';

    return new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
}

/** Installation state must not depend on database availability. */
function alreadyInstalled(string $LOCK, string $ENV): bool
{
    return \App\Support\InstallerState::isLocked($LOCK, $ENV);
}

function baseUrl(bool $stripPublic): string
{
    $https = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($stripPublic && str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }

    return ($https ? 'https' : 'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').$dir;
}

function requirements(string $ROOT): array
{
    $ext = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo', 'curl', 'dom', 'zip', 'gd'];
    $rows = [['PHP '.MIN_PHP.'+ (you have '.PHP_VERSION.')', version_compare(PHP_VERSION, MIN_PHP, '>='), true]];
    foreach ($ext as $e) {
        $rows[] = ["PHP extension: {$e}", extension_loaded($e), ! in_array($e, ['gd', 'zip'], true)];
    }
    $rows[] = ['vendor/ folder (Composer packages)', is_file($ROOT.'/vendor/autoload.php'), true];
    $rows[] = ['public/build/ (compiled CSS/JS)', is_file($ROOT.'/public/build/manifest.json'), true];
    foreach (['storage', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $d) {
        @mkdir($ROOT.'/'.$d, 0775, true);
        $rows[] = ["Writable: {$d}/", is_writable($ROOT.'/'.$d), true];
    }
    $rows[] = ['Writable: .env (project folder)', is_file($ROOT.'/.env') ? is_writable($ROOT.'/.env') : is_writable($ROOT), true];

    return $rows;
}

function bootLaravel(string $ROOT)
{
    require_once $ROOT.'/vendor/autoload.php';
    $app = require $ROOT.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return $app;
}

// ---------------------------------------------------------------- routing
$step = (int) ($_GET['step'] ?? 1);
$errors = [];
$notes = [];

// Only the read-only completion screen may be revisited after installation.
if (alreadyInstalled($LOCK, $ENV) && !($step === 5 && ($_SESSION['done'] ?? false) === true)) {
    $step = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step > 0) {
    if (! hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'Session expired — please try again.';
    } elseif ($step === 2) {
        $db = [
            'host' => trim((string) ($_POST['host'] ?? '127.0.0.1')),
            'port' => trim((string) ($_POST['port'] ?? '3306')) ?: '3306',
            'name' => trim((string) ($_POST['name'] ?? '')),
            'user' => trim((string) ($_POST['user'] ?? '')),
            'pass' => (string) ($_POST['pass'] ?? ''),
        ];
        $_SESSION['db'] = $db;
        $_SESSION['app_url'] = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');

        if (! preg_match('/^[A-Za-z0-9_\-$]{1,64}$/', $db['name'])) {
            $errors[] = 'Database name: letters, numbers, _ and - only.';
        } elseif (! filter_var($_SESSION['app_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Site address must be a full URL, e.g. https://example.com';
        } else {
            try {
                try {
                    pdoFor($db);
                } catch (PDOException $e) {
                    // XAMPP: database may not exist yet → try to create it (cPanel users create it in cPanel)
                    if (str_contains($e->getMessage(), 'Unknown database')) {
                        pdoFor($db, false)->exec('CREATE DATABASE `'.$db['name'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                        $notes[] = "Database {$db['name']} created.";
                    } else {
                        throw $e;
                    }
                }
                $version = pdoFor($db)->query('SELECT VERSION()')->fetchColumn();
                $_SESSION['db_ok'] = true;
                $_SESSION['db_version'] = $version;
                header('Location: ?step=3');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Could not connect: '.$e->getMessage();
            }
        }
    } elseif ($step === 3) {
        $site = [
            'site_name' => trim((string) ($_POST['site_name'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'username' => strtolower(trim((string) ($_POST['username'] ?? ''))),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'demo' => ! empty($_POST['demo']),
            'license' => strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) ($_POST['license'] ?? ''))),
        ];
        $pass = (string) ($_POST['password'] ?? '');
        if ($site['site_name'] === '' || $site['name'] === '') {
            $errors[] = 'Site name and your name are required.';
        }
        if (! preg_match('/^[a-z0-9_.]{3,30}$/', $site['username'])) {
            $errors[] = 'Username: 3–30 letters, numbers, _ or .';
        }
        if (! filter_var($site['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email.';
        }
        if (strlen($pass) < 10 || ! preg_match('/[A-Za-z]/', $pass) || ! preg_match('/\d/', $pass)) {
            $errors[] = 'Password: at least 10 characters with letters and numbers.';
        } elseif ($pass !== (string) ($_POST['password_confirmation'] ?? '')) {
            $errors[] = 'Passwords do not match.';
        }
        $_SESSION['site'] = $site;
        if (! $errors) {
            $_SESSION['admin_password'] = $pass;
            header('Location: ?step=4');
            exit;
        }
    } elseif ($step === 4) {
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        $db = $_SESSION['db'] ?? null;
        $site = $_SESSION['site'] ?? null;
        $pass = $_SESSION['admin_password'] ?? null;

        if (! $db || ! $site || ! $pass || empty($_SESSION['db_ok'])) {
            header('Location: ?step=2');
            exit;
        }

        try {
            // Claim setup atomically BEFORE writing configuration or creating an owner.
            // A failed partial install stays locked and needs local operator recovery.
            \App\Support\InstallerState::claim($LOCK);

            // 1. .env
            $template = is_file($ROOT.'/.env.example') ? (string) file_get_contents($ROOT.'/.env.example') : '';
            $values = [
                'APP_NAME' => $site['site_name'],
                'APP_ENV' => 'production',
                'APP_KEY' => envValue($ENV, 'APP_KEY') ?: 'base64:'.base64_encode(random_bytes(32)),
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'Asia/Yangon',
                'APP_URL' => $_SESSION['app_url'],
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => $db['host'],
                'DB_PORT' => $db['port'],
                'DB_DATABASE' => $db['name'],
                'DB_USERNAME' => $db['user'],
                'DB_PASSWORD' => $db['pass'],
                'SESSION_DRIVER' => 'database',
                'SESSION_SECURE_COOKIE' => str_starts_with((string) $_SESSION['app_url'], 'https://') ? 'true' : 'false',
                'QUEUE_CONNECTION' => 'database',
                'CACHE_STORE' => 'database',
                'QUEUE_IN_SCHEDULER' => 'true',
                'LOG_LEVEL' => 'error',
                'LICENSE_KEY' => $site['license'] ?? '',
                'ADMIN_EMAIL' => '',
                'ADMIN_PASSWORD' => '',
            ];
            $content = $template;
            foreach ($values as $k => $v) {
                $line = $k.'='.envQuote((string) $v);
                $content = preg_match('/^'.$k.'=.*$/m', $content)
                    ? preg_replace('/^'.$k.'=.*$/m', str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                    : rtrim($content)."\n".$line."\n";
            }
            if (file_put_contents($ENV, $content) === false) {
                throw new RuntimeException('Could not write .env — make the project folder writable or create .env yourself.');
            }
            foreach (['bootstrap/cache/config.php', 'bootstrap/cache/routes-v7.php', 'bootstrap/cache/services.php', 'bootstrap/cache/packages.php'] as $f) {
                @unlink($ROOT.'/'.$f); // stale caches from the developer's machine
            }

            // 2. Laravel: migrate, seed, admin, storage link
            $app = bootLaravel($ROOT);
            $artisan = $app->make(Illuminate\Contracts\Console\Kernel::class);

            $artisan->call('migrate', ['--force' => true]);
            $notes[] = 'Database tables created.';
            $demo = $site['demo'] && App\Models\ServiceCategory::count() === 0; // demo data only into an empty shop
            foreach (['SettingSeeder', 'UserGroupSeeder', 'PaymentMethodSeeder'] + ($demo ? [3 => 'CatalogSeeder'] : []) as $seeder) {
                $artisan->call('db:seed', ['--class' => 'Database\\Seeders\\'.$seeder, '--force' => true]);
            }
            $notes[] = 'Default settings, customer groups and payment methods added'.($site['demo'] ? ' + demo services' : '').'.';

            $user = App\Models\User::where('email', $site['email'])->orWhere('username', $site['username'])->first() ?? new App\Models\User;
            $user->fill(['name' => $site['name'], 'username' => $site['username'], 'email' => $site['email'], 'password' => $pass]);
            $user->forceFill(['role' => 'admin', 'staff_role' => 'owner', 'status' => 'active', 'email_verified_at' => now()])->save();
            App\Models\Setting::put('site.name', $site['site_name']);
            $notes[] = "Owner account {$site['username']} ready.";

            try {
                if (! file_exists(__DIR__.'/storage')) {
                    $artisan->call('storage:link');
                }
                $notes[] = 'Public storage linked (logos, avatars).';
            } catch (Throwable) {
                $notes[] = '⚠ Could not create public/storage link — ask your host, or run "php artisan storage:link".';
            }

            $artisan->call('optimize:clear');
            file_put_contents($LOCK, 'installed '.date('c')."\n");

            unset($_SESSION['admin_password']);
            $_SESSION['done'] = true;
            $_SESSION['notes'] = $notes;
            header('Location: ?step=5');
            exit;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($step === 5 && empty($_SESSION['done'])) {
    $step = 1;
}
// Don't skip steps
if ($step === 3 && empty($_SESSION['db_ok'])) {
    header('Location: ?step=2');
    exit;
}
if ($step === 4 && empty($_SESSION['site'])) {
    header('Location: '.(empty($_SESSION['db_ok']) ? '?step=2' : '?step=3'));
    exit;
}

$reqs = requirements($ROOT);
$reqOk = ! array_filter($reqs, fn ($r) => ! $r[1] && $r[2]);
$isWindows = DIRECTORY_SEPARATOR === '\\';
// Under Apache / FPM, PHP_BINARY is the web server; find the command-line php next to it
$php = PHP_BINARY ?: 'php';
if (! preg_match('/^php[\d.]*(\.exe)?$/i', basename($php))) {
    $guess = PHP_BINDIR.DIRECTORY_SEPARATOR.'php'.($isWindows ? '.exe' : '');
    $php = is_file($guess) ? $guess : ($isWindows ? 'C:\\xampp\\php\\php.exe' : '/usr/local/bin/php');
}
$isXampp = $isWindows || str_contains(strtolower($ROOT), 'xampp');
$token = h($_SESSION['csrf']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Install UnlockHub</title>
<style>
  *{box-sizing:border-box} body{margin:0;font-family:system-ui,-apple-system,"Segoe UI","Noto Sans Myanmar",sans-serif;background:#F4F6FA;color:#0F172A;line-height:1.5}
  .wrap{max-width:760px;margin:32px auto;padding:0 16px} .card{background:#fff;border:1px solid #E2E8F0;border-radius:16px;padding:24px;margin-bottom:16px}
  h1{font-size:24px;margin:0 0 4px} h2{font-size:18px;margin:0 0 12px} .muted{color:#556070;font-size:14px} .steps{display:flex;gap:6px;margin:16px 0;flex-wrap:wrap}
  .steps span{flex:1;min-width:110px;text-align:center;font-size:13px;font-weight:600;padding:8px;border-radius:10px;background:#EEF2F7;color:#556070} .steps .on{background:#2459E8;color:#fff} .steps .done{background:#E3F6EA;color:#15803D}
  label{display:block;font-weight:600;font-size:14px;margin:12px 0 6px} input[type=text],input[type=password],input[type=email],input[type=url],input[type=number]{width:100%;height:46px;padding:0 12px;border:1px solid #E2E8F0;border-radius:10px;font-size:16px;background:#F4F6FA}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0 12px} .btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 20px;border-radius:10px;border:1px solid #E2E8F0;background:#fff;font-weight:600;font-size:15px;color:#0F172A;text-decoration:none;cursor:pointer}
  .btn-primary{background:#2459E8;border-color:#2459E8;color:#fff} .btn[disabled]{opacity:.5;cursor:not-allowed} .row{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;align-items:center}
  table{width:100%;border-collapse:collapse;font-size:14px} td{padding:8px 4px;border-bottom:1px solid #EEF2F7} .ok{color:#15803D;font-weight:700} .bad{color:#B91C1C;font-weight:700} .warn{color:#A15C07;font-weight:700}
  .alert{border-radius:12px;padding:12px 14px;margin-bottom:12px;font-size:14px} .alert-bad{background:#FDE6E6;color:#B91C1C} .alert-ok{background:#E3F6EA;color:#15803D} .alert-info{background:#E8EEFF;color:#1E3A8A}
  code,pre{font-family:ui-monospace,Consolas,monospace;font-size:13px} pre{background:#0F172A;color:#E8EDF6;padding:12px;border-radius:10px;overflow-x:auto;white-space:pre-wrap;word-break:break-all}
  .tabs{display:flex;gap:6px;margin:8px 0} details{margin-top:8px} summary{cursor:pointer;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>🔓 UnlockHub installer</h1>
    <div class="muted">XAMPP · cPanel · VPS — install in a few minutes. <span lang="my">အဆင့် ၄ ဆင့်နဲ့ တပ်ဆင်ပါ။</span></div>
    <?php if ($step > 0): ?>
    <div class="steps">
      <?php foreach ([1 => '1. Check', 2 => '2. Database', 3 => '3. Site & admin', 4 => '4. Install'] as $n => $l): ?>
        <span class="<?= $step === $n ? 'on' : ($step > $n ? 'done' : '') ?>"><?= h($l) ?></span>
      <?php endforeach ?>
    </div>
    <?php endif ?>
  </div>

  <?php foreach ($errors as $e): ?><div class="alert alert-bad"><?= h($e) ?></div><?php endforeach ?>

  <?php if ($step === 0): ?>
    <div class="card">
      <h2>Setup is locked</h2>
      <p class="muted">This site is configured or setup has already started. The installer stays locked even if the database is unavailable. For an incomplete installation, contact the server administrator for local recovery. Removing the lock file alone does not unlock a configured site.</p>
      <div class="row"><a class="btn btn-primary" href="<?= h(baseUrl(is_file($ROOT.'/.htaccess'))) ?>/">Open the site</a><a class="btn" href="<?= h(baseUrl(is_file($ROOT.'/.htaccess'))) ?>/login">Log in</a></div>
    </div>

  <?php elseif ($step === 1): ?>
    <div class="card">
      <h2>Server check</h2>
      <table>
        <?php foreach ($reqs as [$label, $pass, $required]): ?>
          <tr><td><?= h($label) ?></td><td style="text-align:right" class="<?= $pass ? 'ok' : ($required ? 'bad' : 'warn') ?>"><?= $pass ? '✓ OK' : ($required ? '✗ Missing' : '! Recommended') ?></td></tr>
        <?php endforeach ?>
      </table>
      <?php if (! $reqOk): ?>
        <details open><summary>How to fix</summary>
          <ul class="muted">
            <li><b>PHP version / extensions</b> — XAMPP: edit <code>C:\xampp\php\php.ini</code>, remove <code>;</code> in front of <code>extension=zip</code>, <code>extension=gd</code>, <code>extension=fileinfo</code>, <code>extension=pdo_mysql</code>, restart Apache. cPanel: <i>Select PHP Version</i> / <i>MultiPHP Manager</i> → PHP 8.3+, tick the extensions.</li>
            <li><b>vendor/ or public/build/ missing</b> — upload the <i>release zip</i> (made with <code>scripts\build-release.ps1</code>), or run <code>composer install --no-dev</code> and <code>npm ci &amp;&amp; npm run build</code>.</li>
            <li><b>Not writable</b> — cPanel File Manager → right-click the folder → Permissions <code>755</code> (files 644). XAMPP is normally fine.</li>
          </ul>
        </details>
      <?php endif ?>
      <div class="row"><a class="btn btn-primary" href="?step=2" <?= $reqOk ? '' : 'onclick="return false" disabled' ?>>Next: database →</a><a class="btn" href="?step=1">Check again</a></div>
    </div>

  <?php elseif ($step === 2): ?>
    <?php $d = $_SESSION['db'] ?? ['host' => '127.0.0.1', 'port' => '3306', 'name' => $isXampp ? 'unlockhub' : '', 'user' => $isXampp ? 'root' : '', 'pass' => '']; ?>
    <form method="post" class="card">
      <input type="hidden" name="csrf" value="<?= $token ?>">
      <h2>Database (MySQL / MariaDB)</h2>
      <div class="alert alert-info">
        <b>XAMPP:</b> start MySQL in the XAMPP Control Panel; host <code>127.0.0.1</code>, user <code>root</code>, empty password — the database is created for you.<br>
        <b>cPanel:</b> first create a database + user in <i>MySQL® Databases</i> (or <i>Database Wizard</i>), give the user <b>ALL PRIVILEGES</b>. Names look like <code>cpuser_unlockhub</code>. Host is usually <code>localhost</code>.
      </div>
      <div class="grid">
        <div><label for="host">Host</label><input id="host" name="host" type="text" value="<?= h($d['host']) ?>" required></div>
        <div><label for="port">Port</label><input id="port" name="port" type="number" value="<?= h($d['port']) ?>"></div>
      </div>
      <label for="name">Database name</label><input id="name" name="name" type="text" value="<?= h($d['name']) ?>" required>
      <div class="grid">
        <div><label for="user">Username</label><input id="user" name="user" type="text" value="<?= h($d['user']) ?>" required autocomplete="off"></div>
        <div><label for="pass">Password</label><input id="pass" name="pass" type="password" value="" autocomplete="new-password"></div>
      </div>
      <label for="app_url">Site address</label>
      <input id="app_url" name="app_url" type="url" value="<?= h($_SESSION['app_url'] ?? baseUrl(is_file($ROOT.'/.htaccess'))) ?>" required>
      <div class="muted">The address people type. Use <code>https://</code> on a live site (push & app install need it).</div>
      <div class="row"><a class="btn" href="?step=1">← Back</a><button class="btn btn-primary">Test &amp; continue →</button></div>
    </form>

  <?php elseif ($step === 3): ?>
    <?php $s = $_SESSION['site'] ?? ['site_name' => 'UnlockHub', 'name' => '', 'username' => 'admin', 'email' => '', 'demo' => false]; ?>
    <form method="post" class="card">
      <input type="hidden" name="csrf" value="<?= $token ?>">
      <div class="alert alert-ok">Connected to MySQL <?= h((string) ($_SESSION['db_version'] ?? '')) ?> ✓</div>
      <h2>Site &amp; owner account</h2>
      <label for="site_name">Site name</label><input id="site_name" name="site_name" type="text" value="<?= h($s['site_name']) ?>" required maxlength="60">
      <div class="grid">
        <div><label for="aname">Your name</label><input id="aname" name="name" type="text" value="<?= h($s['name']) ?>" required></div>
        <div><label for="username">Username</label><input id="username" name="username" type="text" value="<?= h($s['username']) ?>" required></div>
      </div>
      <label for="email">Email</label><input id="email" name="email" type="email" value="<?= h($s['email']) ?>" required>
      <div class="grid">
        <div><label for="password">Password</label><input id="password" name="password" type="password" required minlength="10" autocomplete="new-password"></div>
        <div><label for="password_confirmation">Repeat password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
      </div>
      <label for="license">Serial key <span class="muted" style="font-weight:400">(from the seller — you can also enter it later in Admin › License)</span></label>
      <input id="license" name="license" type="text" value="<?= h($s['license'] ?? '') ?>" placeholder="UHUB-XXXX-XXXX-XXXX-XXXX" autocomplete="off" style="font-family:ui-monospace,monospace;text-transform:uppercase">
      <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="demo" value="1" <?= $s['demo'] ? 'checked' : '' ?>> Add demo services (you can delete them later)</label>
      <div class="row"><a class="btn" href="?step=2">← Back</a><button class="btn btn-primary">Next →</button></div>
    </form>

  <?php elseif ($step === 4): ?>
    <form method="post" class="card" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Installing… (up to a minute)'">
      <input type="hidden" name="csrf" value="<?= $token ?>">
      <h2>Ready to install</h2>
      <ul class="muted">
        <li>Writes <code>.env</code> (production mode, new secret key)</li>
        <li>Creates all tables in <b><?= h($_SESSION['db']['name'] ?? '') ?></b></li>
        <li>Adds default settings, groups, payment methods<?= ! empty($_SESSION['site']['demo']) ? ' and demo services' : '' ?></li>
        <li>Creates owner <b><?= h($_SESSION['site']['username'] ?? '') ?></b></li>
      </ul>
      <div class="row"><a class="btn" href="?step=3">← Back</a><button class="btn btn-primary">Install now</button></div>
    </form>

  <?php elseif ($step === 5): ?>
    <?php $url = rtrim((string) ($_SESSION['app_url'] ?? baseUrl(true)), '/'); ?>
    <div class="card">
      <div class="alert alert-ok"><b>Installed!</b> 🎉</div>
      <ul class="muted"><?php foreach ($_SESSION['notes'] ?? [] as $n): ?><li><?= h($n) ?></li><?php endforeach ?></ul>
      <div class="row"><a class="btn btn-primary" href="<?= h($url) ?>/login">Log in as owner</a><a class="btn" href="<?= h($url) ?>/">Open the site</a></div>
    </div>
    <div class="card">
      <h2>Last step: background jobs (1 minute)</h2>
      <p class="muted">Sends API orders, checks supplier results, daily checks, monthly statements. One line runs everything (queue included).</p>
      <?php if ($isXampp): ?>
        <b>XAMPP (Windows)</b>
        <p class="muted">Easiest while testing: open a terminal in the project folder and leave it running:</p>
        <pre>cd "<?= h($ROOT) ?>"
"<?= h($php) ?>" artisan schedule:work</pre>
        <p class="muted">Permanent: Task Scheduler → Create Task → Trigger <i>Daily, repeat every 1 minute indefinitely</i> → Action:</p>
        <pre>Program:   <?= h($php) ?>

Arguments: "<?= h($ROOT) ?>\artisan" schedule:run</pre>
      <?php endif ?>
      <b>cPanel → Cron Jobs → Once Per Minute</b>
      <pre>cd <?= h($isWindows ? '/home/USER/unlockhub' : $ROOT) ?> &amp;&amp; <?= h($isWindows ? '/usr/local/bin/php' : $php) ?> artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</pre>
      <p class="muted">If cron says "php: command not found", use your host's PHP path (e.g. <code>/opt/cpanel/ea-php83/root/usr/bin/php</code>).</p>
      <details><summary>Security checklist</summary>
        <ul class="muted">
          <li>Turn on Google Authenticator for the owner: My account › Settings &amp; 2FA.</li>
          <li>Use HTTPS (cPanel: SSL/TLS Status → AutoSSL).</li>
          <li>The installer is now locked (<code>storage/installed</code>). You may also delete <code>public/install.php</code>.</li>
          <li>Set mail (SMTP) in <code>.env</code> → <code>MAIL_*</code> so customers get emails.</li>
        </ul>
      </details>
    </div>
    <?php $_SESSION = []; session_destroy(); ?>
  <?php endif ?>
</div>
</body>
</html>
