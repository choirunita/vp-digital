@extends('layouts.app')

@section('content')
@php($n = fn ($status) => $stats->get($status)->count ?? 0)
@php($t = fn ($status) => $stats->get($status)->total ?? 0)
@php($statusLabels = ['DRAFT' => 'Draft', 'DIAJUKAN' => 'Menunggu Checker', 'DIPERIKSA' => 'Menunggu Approver 1', 'DIKEMBALIKAN' => 'Dikembalikan', 'DISETUJUI' => 'Disetujui · siap cetak', 'DICETAK' => 'Dicetak', 'SELESAI' => 'Selesai', 'DITOLAK' => 'Ditolak'])
@php($statusClasses = ['DRAFT' => 'st-draft', 'DIAJUKAN' => 'st-wait', 'DIPERIKSA' => 'st-wait', 'DIKEMBALIKAN' => 'st-return', 'DISETUJUI' => 'st-ok', 'DICETAK' => 'st-done', 'SELESAI' => 'st-done', 'DITOLAK' => 'st-reject'])

<div class="head">
    <div class="t">
        <span class="crumb">Beranda</span>
        <h1>Selamat datang, {{ $user->full_name }}</h1>
    </div>
    @if ($user->role === 'maker')
        <form method="post" action="{{ route('vp.create') }}" class="actions">
            @csrf
            <button class="btn" name="kind" value="MASS">+ VP massal</button>
            <button class="btn pri" name="kind" value="SINGLE">+ VP non massal</button>
        </form>
    @endif
</div>

<section class="kpis">
    <a class="kpi" href="{{ route('vp.index', ['status' => 'DIAJUKAN']) }}">
        <span class="small muted">Menunggu Checker</span>
        <span class="v">{{ $n('DIAJUKAN') }} VP</span>
        <span class="small muted num">Rp {{ number_format($t('DIAJUKAN'), 0, ',', '.') }}</span>
    </a>
    <a class="kpi" href="{{ route('vp.index', ['status' => 'DIPERIKSA']) }}">
        <span class="small muted">Menunggu Approver 1</span>
        <span class="v">{{ $n('DIPERIKSA') }} VP</span>
        <span class="small muted num">Rp {{ number_format($t('DIPERIKSA'), 0, ',', '.') }}</span>
    </a>
    <a class="kpi" href="{{ route('vp.index', ['status' => 'DISETUJUI']) }}">
        <span class="small muted">Disetujui, siap cetak</span>
        <span class="v">{{ $n('DISETUJUI') }} VP</span>
        <span class="small muted num">Rp {{ number_format($t('DISETUJUI'), 0, ',', '.') }}</span>
    </a>
    <a class="kpi dark" href="{{ route('vp.index', ['status' => 'DIKEMBALIKAN']) }}">
        <span class="small muted">Dikembalikan ke Maker</span>
        <span class="v">{{ $n('DIKEMBALIKAN') }} VP</span>
        <span class="small muted">Draft: {{ $n('DRAFT') }}</span>
    </a>
</section>

@if ($user->role === 'maker')
    <section class="card flush">
        <div style="padding:16px 20px 0" class="head">
            <h2>Draft dan VP dikembalikan</h2>
            <a href="{{ route('vp.index', ['status' => 'DRAFT']) }}">Buka semua</a>
        </div>
        @include('dashboard._vp-table', ['rows' => $drafts, 'empty' => 'Tidak ada draft atau VP yang dikembalikan.'])
    </section>
@endif

<section class="card flush">
    <div style="padding:16px 20px 0" class="head">
        <h2>{{ $user->role === 'maker' ? 'VP terbaru Anda' : 'VP terbaru' }}</h2>
        <a href="{{ route('vp.index') }}">Lihat daftar</a>
    </div>
    @if ($user->role === 'maker')
        @include('dashboard._vp-table', ['rows' => $recent, 'empty' => 'Belum ada VP.'])
    @else
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>No. registrasi</th><th>Referensi</th><th>Uraian</th><th>Jenis</th><th class="r">Nominal (Rp)</th><th>Status</th></tr></thead><tbody>
        @forelse ($recent as $vp)
            <tr><td><a href="{{ route('vp.show', $vp->id) }}"><b>{{ $vp->reg_no ?: 'Draft #'.$vp->id }}</b></a></td><td>{{ $vp->ref_no ?: '-' }}</td><td>{{ $vp->description ?: '-' }}</td><td><span class="pill">{{ $vp->kind === 'MASS' ? 'Massal' : 'Non massal' }}</span></td><td class="r"><b>{{ number_format($vp->total, 0, ',', '.') }}</b></td><td><span class="pill">{{ $statusLabels[$vp->status] ?? $vp->status }}</span></td></tr>
        @empty
            <tr><td colspan="6" class="muted">Belum ada VP.</td></tr>
        @endforelse
        </tbody></table></div>
    @endif
</section>
@endsection
