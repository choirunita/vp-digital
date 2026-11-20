<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title ?? 'VP Digital' }} · VP Digital PCS</title><link rel="icon" href="{{ asset('img/logo-mark.png') }}"><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head><body>
<div class="app"><aside class="side">
  <a href="{{ route('dashboard') }}"><img class="logo" src="{{ asset('img/logo-pcs.png') }}" alt="Petrokopindo Cipta Selaras"></a>
  <nav aria-label="Menu utama">
    <span class="lbl">VP Digital</span>
    <a class="{{ request()->routeIs('dashboard') ? 'on' : '' }}" href="{{ route('dashboard') }}">Beranda</a>
    <a class="{{ request()->routeIs('vp.*') ? 'on' : '' }}" href="{{ route('vp.index') }}">{{ auth()->user()->role === 'maker' ? 'VP saya' : 'Daftar & tracking VP' }}</a>
    @if (in_array(auth()->user()->role, ['checker', 'approver1']))
      <a class="{{ request()->routeIs('approvals.*') ? 'on' : '' }}" href="{{ route('approvals.index') }}">{{ auth()->user()->role === 'checker' ? 'Pemeriksaan VP' : 'Persetujuan VP' }}</a>
    @endif
    @if (in_array(auth()->user()->role, ['sesdir', 'admin', 'approver1']))
      <a class="{{ request()->routeIs('print.*') ? 'on' : '' }}" href="{{ route('print.index') }}">Cetak VP</a>
    @endif
    <a class="{{ request()->routeIs('vendors.*') ? 'on' : '' }}" href="{{ route('vendors.index') }}">Rekening penerima
      @if ($pendingVendorCount > 0)<span class="badge">{{ $pendingVendorCount }}</span>@endif
    </a>
    @if (in_array(auth()->user()->role, ['admin', 'checker', 'approver1']))
      <a class="{{ request()->routeIs('audit.*') ? 'on' : '' }}" href="{{ route('audit.index') }}">Jejak audit</a>
    @endif
    @if (auth()->user()->role === 'admin')
      <span class="lbl">Administrasi</span>
      <a class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}" href="{{ route('admin.users.index') }}">Pengguna &amp; peran</a>
      <a class="{{ request()->routeIs('pcs.*') ? 'on' : '' }}" href="{{ route('pcs.index') }}">Rekening bank PCS</a>
    @endif
    <a class="{{ request()->routeIs('alur') ? 'on' : '' }}" href="{{ route('alur') }}">Alur &amp; panduan</a>
    <a class="{{ request()->routeIs('profile.*') ? 'on' : '' }}" href="{{ route('profile.index') }}">Profil saya</a>
  </nav>
  <div class="me"><span class="l hide-m">Masuk sebagai</span><b>{{ auth()->user()->full_name }}</b><span class="r">{{ ucfirst(auth()->user()->role) }} · {{ auth()->user()->title }}</span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Keluar</button></form></div>
</aside>
<main class="main">@if(session('ok'))<p class="flash ok">{{ session('ok') }}</p>@endif @if($errors->any())<p class="flash err">{{ $errors->first() }}</p>@endif @yield('content')</main></div></body></html>
