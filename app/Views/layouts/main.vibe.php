<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <!-- Google Fonts: Urbanist ve Yardımcı Font Sans-Serif -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #020408 100%);
            --card-bg: rgba(233, 91, 13, 0.02);
            --card-border: rgba(233, 91, 13, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #e95b0d; /* Kurumsal Markuvo Turuncusu */
            --accent-gradient: linear-gradient(90deg, #e95b0d 0%, #ff7a29 100%);
        }

        body {
            font-family: 'Urbanist', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            letter-spacing: 0.5px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
            width: 100%;
            box-sizing: border-box;
        }

        header {
            text-align: center;
            margin-bottom: 60px;
        }

        header .logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px rgba(233, 91, 13, 0.3));
        }

        header h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        header p {
            color: var(--text-muted);
            font-size: 1.4rem;
            font-weight: 500;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        main {
            flex-grow: 1;
        }

        footer {
            text-align: center;
            padding: 40px 0;
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 500;
            border-top: 1px solid var(--card-border);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        footer a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        footer a:hover {
            color: #ff7a29;
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <!-- Kurumsal Logo Entegrasyonu -->
            <img src="https://markuvo.com/images/logoB.png" alt="Markuvo Logo" class="logo">
            <h1>Markuvo</h1>
            <p>{{ $welcome }}</p>
            <span>{{ $description }}</span>
        </header>
        <main>
            @yield('content')
        </main>
    </div>

    <footer>
        <p>Markuvo v1.0.0 (PHP {{ phpversion() }}) • <a href="#">Dökümantasyon</a></p>
    </footer>

</body>
</html>
