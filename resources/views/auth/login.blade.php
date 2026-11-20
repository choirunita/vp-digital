<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · VP Digital PCS</title>
    <link rel="icon" href="{{ asset('img/logo-mark.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="login"><section class="box">
    <div class="top">
        <img src="{{ asset('img/logo-pcs.png') }}" alt="Petrokopindo Cipta Selaras">
        <div><h1>VP Digital</h1><p class="muted" style="margin:6px 0 0">Pengajuan, pemeriksaan, dan persetujuan voucher pembayaran.</p></div>
    </div>
    <form method="post" action="{{ route('login.store') }}" autocomplete="on">
        @csrf
        @if ($errors->any())<div class="flash err" role="alert">{{ $errors->first() }}</div>@endif
        <label class="field" for="username">Nama pengguna
            <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus>
        </label>
        <label class="field" for="password">Kata sandi
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="btn pri" type="submit" style="height:48px">Masuk</button>
        <p class="small muted" style="margin:0">Semua aktivitas dicatat dalam jejak audit. Lupa kata sandi? Hubungi Admin.</p>
    </form>
</section></main>
</body>
</html>
