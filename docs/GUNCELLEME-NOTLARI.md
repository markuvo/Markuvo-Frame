# Markuvo 3.0 — Güncelleme Notları

Bu sürüm, ROADMAP'teki 8 adımın hepsini içerir. Aşağıda **önce çalıştırmanız gerekenler**, sonra **eski koddan farklar** var.

## 1. Kurulum (5 dakika)

```bash
cp .env.example .env          # sonra .env içindeki DB_* ayarlarını düzenleyin
php markuvo migrate           # users tablosunu oluşturur
php markuvo db:seed           # admin@markuvo.com / 123456  (+ 10 test kullanıcısı)
php markuvo serve             # http://127.0.0.1:8000
php markuvo test              # 150+ testi çalıştırır
```

**Sunucu kurmadan denemek için (SQLite):** `.env` içinde
`DB_CONNECTION=sqlite` ve `DB_DATABASE=database/database.sqlite` yazın.

**XAMPP (MySQL):** phpMyAdmin'den boş bir veritabanı açın, adını `DB_DATABASE`'e yazın. XAMPP'te
`DB_USERNAME=root`, `DB_PASSWORD=` (boş) varsayılandır. Projeyi `htdocs/Yeni_Versiyon` altında
`http://localhost/Yeni_Versiyon/` ile açabilirsiniz (alt klasör otomatik algılanır).

> Admin şifresi (`123456`) yalnızca geliştirme içindir. Canlıya çıkmadan önce değiştirin.

## 2. Yeni özellikler

| Alan | Ne var |
|---|---|
| Veritabanı | MySQL/MariaDB, PostgreSQL, SQLite (tek `.env` ayarıyla). Hazırlanmış ifadeler, tanımlayıcı/operatör beyaz listesi |
| ORM | `Model::where()->orderBy()->get()` (Collection döner), `find`, `findOrFail`, `create`, `save`, `delete`, `paginate`, `$fillable`, `$hidden`, accessor/mutator, otomatik `created_at/updated_at` |
| Migration | `Schema::create/table/drop`, `Blueprint`, `php markuvo migrate / migrate:rollback / migrate:status / migrate:reset`, `db:seed` |
| İstek | `Request` (ham girdi, JSON gövde, `_method` taklidi, `getBasePath`), `$request->validate([...])` + Türkçe mesajlar |
| Rotalar | `{id}` ve `:id`, `->name()`, `->where()`, `->middleware()`, grup/prefix, `Class@method`, 404/405, `route('ad')` |
| Container | Otomatik bağımlılık enjeksiyonu; `{user}` parametresi + `User $user` tipi = otomatik model bağlama (yoksa 404) |
| Güvenlik | CSRF (`@csrf`), güvenli oturum çerezi (HttpOnly, SameSite), giriş sonrası oturum yenileme, `throttle:5,1`, çıkışta kaçışlama (`{{ }}`) |
| Auth | `auth()->attempt()`, `->user()`, `->logout()`, `auth` ve `guest` middleware |
| Görünüm | Derlenmiş önbellek (`storage/cache/views`), `eval` yok; `@extends @section @yield @include @push @if @foreach @forelse @auth @error @csrf @json …` |
| Önbellek | `file`, `array`, `redis` (`cache()`, `Cache::remember`) |
| Hatalar | `storage/logs/app.log`, `storage/logs/db_errors.log`; canlıda ayrıntı gizlenir, `APP_DEBUG=true` iken görünür |

## 3. Eski koddan farklar (kırılan davranışlar)

- `Model::find()` / `where()->first()` artık dizi değil **Model nesnesi** döner; `get()` bir **Collection** döner (`foreach`, `->first()`, `->pluck()` çalışır).
- Toplu atama için modelde `$fillable` tanımlayın; aksi hâlde `create([...])` `MassAssignmentException` atar. Doğrudan `$user->alan = ...; $user->save();` her zaman çalışır.
- `$request->validate()` artık **bool değil**, doğrulanmış diziyi döner; hata olursa kendisi geri yönlendirir (JSON isteklerinde 422).
- `view()` bir **string** döner; Controller içinde `return $this->view('ad')` kullanın. `Controller::json()` artık `exit` etmez, `Response` döner.
- Şablonlarda ham PHP yerine `{{ }}` kullanın. `{{ }}` otomatik HTML-kaçışlıdır; ham HTML için `{!! !!}`.
- Tüm POST formlarına `@csrf` ekleyin (yoksa 419).
- `/admin/...` rotaları artık `auth` middleware ile korunuyor (önce giriş yapın).
- Girdi artık girişte "temizlenmez" (veri bozulmasın diye); güvenlik çıkışta `e()` / `{{ }}` ile ve sorgularda hazırlanmış ifadelerle sağlanır.
- Controller klasörü `app/Controllers`, görünümler `app/Views` (büyük harf). 

## 4. Git: büyük/küçük harf klasör adı (macOS)

GitHub'daki depoda hâlâ `app/controllers` ve `app/views` (küçük harf) olabilir. macOS büyük/küçük harfi ayırt etmediği için iki adımda taşıyın:

```bash
git config core.ignorecase false
git mv app/controllers app/Controllers_tmp && git mv app/Controllers_tmp app/Controllers
git mv app/views app/Views_tmp && git mv app/Views_tmp app/Views
```

Ayrıca **gizli dosyaları da** commit'leyin: `.gitignore`, `.htaccess`, `public/.htaccess`, `.env.example`, `storage/**/.gitkeep`.
`.env` ASLA commit edilmez.

## 5. Dürüst notlar (neyi test ettim, neyi edemedim)

- **Test edildi:** 155 test (SQLite gerçek veritabanı; PostgreSQL 16 gerçek sunucuda entegrasyon; Redis gerçek sunucuda; `php -S` ile gerçek HTTP akışı: giriş, CSRF, çıkış, 404, yetki).
- **Test edilemedi:** Apache `.htaccess` kuralları (ortamda Apache yoktu) ve **gerçek MySQL/MariaDB sunucusu** — yalnızca üretilen SQL metinleri test edildi. XAMPP'inizde şunu çalıştırın:
  `TEST_DB=mysql DB_DATABASE=markuvo_test DB_USERNAME=root php tests/runner.php Real`
  (boş bir test veritabanı kullanın; `mk_` önekli tabloları oluşturup siler).
- Testler PHPUnit uyumlu yazıldı; paket indirilemediği için küçük bir dahili çalıştırıcıyla (`tests/runner.php`) koştu. PHPUnit kurarsanız (`composer require --dev phpunit/phpunit`) aynı testler `vendor/bin/phpunit` ile de çalışır — bunu denemedim.
- Önbelleğe nesne koymayın; yalnızca skaler/dizi (güvenlik için nesneler geri yüklenmez).

## 6. Sonraki adımlar (öneri)

`vendor/` klasörünü Git'ten çıkarıp sunucuda `composer install` kullanmak; `README.md`; e-posta/kuyruk; dosya yükleme; `Request` için kural sınıfları.
