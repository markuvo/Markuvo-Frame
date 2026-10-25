@extends('layouts.main')

@section('content')
<div style="max-width:600px;margin:0 auto;text-align:center">
    <h2>Hoş geldin, {{ $user->username }} 👋</h2>
    <p style="color:#94a3b8">{{ $user->email }} olarak giriş yaptın.</p>

    <form action="{{ url('/logout') }}" method="POST">
        @csrf
        <button type="submit" style="padding:12px 28px;border:none;border-radius:8px;background:#e95b0d;color:#fff;font-weight:700;cursor:pointer">Çıkış Yap</button>
    </form>
</div>
@endsection
