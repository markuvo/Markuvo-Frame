<?php $__env->extend('layouts.main'); ?>

<?php $__env->startSection('content'); ?>
<style>
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }

    .card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 12px;
        padding: 30px;
        backdrop-filter: blur(10px);
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), border-color 0.3s ease, box-shadow 0.3s ease;
    }

    .card:hover {
        transform: translateY(-8px);
        border-color: rgba(233, 91, 13, 0.5);
        box-shadow: 0 10px 20px rgba(233, 91, 13, 0.05);
    }

    .card h3 {
        margin: 0 0 15px 0;
        font-size: 1.6rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .card p {
        margin: 0;
        color: var(--text-muted);
        line-height: 1.5;
        font-size: 1.1rem;
        font-weight: 500;
        font-family: sans-serif; /* Okunurluk için açıklama metinleri standart kalsın */
    }

    .badge {
        background: rgba(233, 91, 13, 0.15);
        color: #ff9d66;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        border: 1px solid rgba(233, 91, 13, 0.2);
    }
</style>

<div class="grid">
    <div class="card">
        <h3>⚡ Rota Motoru <span class="badge">Aktif</span></h3>
        <p>{id} ve :id parametreleri, isimli rotalar, where kısıtları, grup/prefix, middleware ve otomatik model bağlama. Yanlış yöntemde 405, bulunamazsa 404.</p>
    </div>

    <div class="card">
        <h3>🗄️ ORM &amp; Veritabanı <span class="badge">Yeni</span></h3>
        <p>MySQL, PostgreSQL ve SQLite tek .env ayarıyla. Model::where()-&gt;get(), find, create, save, paginate. Hazırlanmış sorgular ile SQL enjeksiyonuna karşı koruma.</p>
    </div>

    <div class="card">
        <h3>🧱 Migration &amp; Seeder <span class="badge">Yeni</span></h3>
        <p>Schema::create ile tablo tanımı; php markuvo migrate, migrate:rollback, migrate:status ve db:seed komutları.</p>
    </div>

    <div class="card">
        <h3>✅ Doğrulama <span class="badge">Yeni</span></h3>
        <p>$request-&gt;validate() ile required, email, min, unique, confirmed gibi kurallar; Türkçe hata mesajları, eski girdiyi koruma, JSON isteklerde 422.</p>
    </div>

    <div class="card">
        <h3>🛡️ Güvenlik <span class="badge">Yeni</span></h3>
        <p>CSRF koruması (@csrf), güvenli oturum çerezi, giriş sonrası oturum yenileme, istek sınırlama (throttle), çıktıda otomatik HTML kaçışlama.</p>
    </div>

    <div class="card">
        <h3>🔐 Kimlik Doğrulama <span class="badge">Yeni</span></h3>
        <p>auth()-&gt;attempt(), user(), logout(); auth ve guest middleware. Deneyin: <a href="<?php echo e(url('/login')); ?>" style="color:#ff9d66">/login</a></p>
    </div>

    <div class="card">
        <h3>🎨 Vibe Motoru <span class="badge">Geliştirildi</span></h3>
        <p>@extends, @section, @yield, @include, @foreach, @forelse, @auth, @error, @csrf... Derlenmiş önbellek ile hızlı, eval kullanmaz.</p>
    </div>

    <div class="card">
        <h3>🚀 Önbellek <span class="badge">Yeni</span></h3>
        <p>File, array ve Redis sürücüleri; Cache::remember(). Hata ve veritabanı günlükleri storage/logs altında.</p>
    </div>

    <div class="card">
        <h3>🧪 Test &amp; Konsole <span class="badge">Yeni</span></h3>
        <p>php markuvo test ile 150+ test; make:controller, make:model --migration, route:list, cache:clear.</p>
    </div>
</div>

<?php if (config('app.debug')): ?>
<style>
    .deploy { margin-top: 40px; border: 1px dashed rgba(233, 91, 13, 0.4); border-radius: 12px; padding: 24px 30px; background: rgba(233, 91, 13, 0.03); }
    .deploy summary { cursor: pointer; font-weight: 700; font-size: 1.2rem; color: #ff9d66; text-transform: uppercase; letter-spacing: 1px; }
    .deploy h4 { margin: 20px 0 6px; color: #fff; }
    .deploy ul { margin: 0; padding-left: 20px; color: var(--text-muted); line-height: 1.8; font-family: sans-serif; }
    .deploy code { color: #ff9d66; }
</style>

<details class="deploy">
    <summary>📦 Canlıya geçerken (geliştirici notu)</summary>
    <p style="color:var(--text-muted);font-family:sans-serif">Bu kutu yalnızca <code>APP_DEBUG=true</code> iken görünür; canlıda kendiliğinden kaybolur.</p>

    <h4>Sunucuya yüklemeyin / silebilirsiniz</h4>
    <ul>
        <li><code>tests/</code> ve <code>phpunit.xml</code> (yalnızca geliştirme)</li>
        <li><code>docs/</code> (notlar ve yol haritası)</li>
        <li><code>.git/</code>, <code>.gitignore</code>, <code>.gitattributes</code>, <code>.DS_Store</code></li>
        <li><code>.env.example</code> (sunucuda gerçek <code>.env</code> oluşturun)</li>
        <li><code>storage/logs/*.log</code> ve yerel <code>database/*.sqlite</code></li>
    </ul>

    <h4>Mutlaka kalmalı</h4>
    <ul>
        <li><code>Core/</code>, <code>app/</code>, <code>config/</code>, <code>routes/</code>, <code>public/</code>, <code>vendor/</code>, <code>.htaccess</code> dosyaları</li>
        <li><code>storage/</code> (yazılabilir olmalı)</li>
    </ul>

    <h4>Canlı kontrol listesi</h4>
    <ul>
        <li><code>.env</code> içinde <code>APP_DEBUG=false</code></li>
        <li>DocumentRoot mümkünse <code>public/</code> klasörü olsun</li>
        <li>Seeder'daki <code>admin / 123456</code> hesabını canlıda kullanmayın</li>
    </ul>
</details>
<?php endif; ?>
<?php $__env->stopSection(); ?>
