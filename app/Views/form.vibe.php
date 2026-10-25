<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Markuvo Form' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #020408 100%);
            --card-bg: rgba(233, 91, 13, 0.02);
            --card-border: rgba(233, 91, 13, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #e95b0d;
            --accent-gradient: linear-gradient(90deg, #e95b0d 0%, #ff7a29 100%);
            --error-bg: rgba(248, 113, 113, 0.05);
            --error-border: rgba(248, 113, 113, 0.2);
        }
        body { font-family: 'Urbanist', sans-serif; background: var(--bg-gradient); color: var(--text-main); margin: 0; padding: 60px 20px; min-height: 100vh; box-sizing: border-box; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .form-box { max-width: 450px; width: 100%; background: var(--card-bg); border: 1px solid var(--card-border); padding: 40px; border-radius: 16px; backdrop-filter: blur(10px); box-sizing: border-box; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input { width: 100%; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); border-radius: 8px; color: #fff; font-size: 1rem; box-sizing: border-box; font-family: inherit; transition: border-color 0.2s; }
        .form-group input:focus { border-color: var(--accent); outline: none; }
        .form-group input.has-error-border { border-color: rgba(248, 113, 113, 0.4); }
        .btn { width: 100%; padding: 14px; background: var(--accent-gradient); border: none; border-radius: 8px; color: #fff; font-size: 1.2rem; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; font-family: inherit; transition: opacity 0.2s; }
        .btn:hover { opacity: 0.9; }
        .premium-error { background: var(--error-bg); border: 1px solid var(--error-border); color: #f87171; padding: 10px 14px; border-radius: 8px; font-size: 0.95rem; font-weight: 500; margin-top: 8px; letter-spacing: 0.3px; display: flex; align-items: center; gap: 6px; }
    </style>
</head>
<body>

    <div style="text-align: center; margin-bottom: 30px;">
        <img src="https://markuvo.com/images/logoB.png" alt="Markuvo Logo" style="max-width: 120px; height: auto;">
        <h1 style="color: var(--accent); margin: 10px 0 0 0; text-transform: uppercase; letter-spacing: 1px; font-size: 2.5rem;">Markuvo</h1>
    </div>

    <div class="form-box">
        <h2 style="text-align: center; margin-top: 0; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px;">Giriş Yap</h2>

        <form action="{{ url('/login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>E-Posta Adresi</label>
                <input type="text" name="email" class="{{ has_error('email') ? 'has-error-border' : '' }}" value="{{ old('email') }}" placeholder="example@markuvo.com">
                @error('email')
                    <div class="premium-error">⚠️ {{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="password" class="{{ has_error('password') ? 'has-error-border' : '' }}" placeholder="••••••••">
                @error('password')
                    <div class="premium-error">⚠️ {{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn">Giriş Yap</button>
        </form>
    </div>

</body>
</html>
