@extends('layouts.app')

@section('content')
<div class="head">
    <div class="t">
        <span class="crumb"><a href="{{ route('vp.index') }}">VP saya</a> / {{ $vp->reg_no ?: 'Draft #'.$vp->id }}</span>
        <h1>Buat voucher pembayaran</h1>
    </div>
    <span class="pill st-draft">{{ $vp->status === 'DIKEMBALIKAN' ? 'Dikembalikan · perlu diperbaiki' : 'Draft · nomor terbit saat diajukan' }}</span>
</div>

<section class="card vp-payment-card">
    <div class="head">
        <h2>Data pembayaran</h2>
        <div class="seg" role="group" aria-label="Jenis voucher pembayaran">
            <label><input type="radio" form="payment-form" name="kind" value="SINGLE" @checked($vp->kind === 'SINGLE')> Non massal · 1 penerima</label>
            <label><input type="radio" form="payment-form" name="kind" value="MASS" @checked($vp->kind === 'MASS')> Massal · banyak penerima</label>
        </div>
    </div>
    <form id="payment-form" method="post" action="{{ route('vp.update', $vp->id) }}" class="vp-payment-form">
        @csrf
        <div class="grid vp-payment-grid">
            <label class="field">Bank pembayar (rekening PCS)<select name="pcs_account_id"><option value="">Pilih rekening PCS</option>@foreach ($pcsAccounts as $p)<option value="{{ $p->id }}" @selected($vp->pcs_account_id == $p->id)>{{ $p->bank }} · {{ $p->account_no }}</option>@endforeach</select></label>
            <label class="field">Nomor referensi bank<input name="ref_no" value="{{ old('ref_no', $vp->ref_no) }}" placeholder="Nomor referensi bank"></label>
            <label class="field">Tanggal pembayaran<input type="date" name="pay_date" value="{{ old('pay_date', $vp->pay_date) }}"></label>
        </div>
        <label class="field">Uraian pembayaran<textarea name="description" rows="2" required placeholder="Contoh: Pembayaran jasa sewa alat berat Oktober 2026">{{ old('description', $vp->description) }}</textarea></label>
        <div class="actions"><button class="btn pri">Simpan data pembayaran</button><span class="muted">Ubah jenis lalu simpan untuk berpindah antara non massal dan massal.</span></div>
    </form>
</section>

<section class="card">
    <div class="head"><h2>Penerima</h2><a href="{{ route('vendors.create') }}">+ Daftarkan rekening baru</a></div>
    @if ($lines->isEmpty())
        <p class="muted">Belum ada penerima.</p>
    @else
        <div class="tbl-wrap"><table class="tbl stack"><thead><tr><th>Penerima</th><th>Bank · rekening</th><th>Keterangan</th><th class="r">Nominal (Rp)</th><th></th></tr></thead><tbody>
        @foreach ($lines as $line)
            <tr><td data-l="Penerima"><b>{{ $line->name }}</b><div class="small muted">{{ $line->account_name }}</div></td><td data-l="Bank · rekening">{{ $line->bank }} · {{ $line->account_no }}</td><td data-l="Keterangan">{{ $line->note ?: '-' }}</td><td data-l="Nominal" class="r"><b>{{ number_format($line->amount, 0, ',', '.') }}</b></td><td data-l=""><form method="post" action="{{ route('vp.lines.destroy', [$vp->id, $line->id]) }}">@csrf<button class="btn danger sm">Hapus</button></form></td></tr>
        @endforeach
        </tbody></table></div>
    @endif

    <form method="post" action="{{ route('vp.lines.store', $vp->id) }}" class="vp-recipient-form">
        @csrf
        <label class="field vp-vendor-search">Pilih penerima dari rekening terdaftar<input type="search" id="vendor-search" placeholder="Cari nama, bank, atau nomor rekening..."><select name="vendor_id" id="vendor-select" required><option value="">Pilih rekening aktif</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" data-search="{{ strtolower($v->name.' '.$v->bank.' '.$v->account_no.' '.$v->account_name) }}">{{ $v->name }} · {{ $v->bank }} · {{ $v->account_no }}</option>@endforeach</select></label>
        <label class="field">Nominal (Rp)<input name="amount" type="number" min="1" step="1" placeholder="Contoh: 79000000" required></label>
        <label class="field">Keterangan baris (opsional)<input name="note" placeholder="Keterangan pembayaran"></label>
        <div class="field"><span>&nbsp;</span><button class="btn">Simpan penerima</button></div>
    </form>
</section>

<section class="bar">
    <div><span class="small muted">Total pembayaran · {{ $lines->count() }} penerima</span><div class="tot">Rp {{ number_format($vp->total, 0, ',', '.') }}</div><em class="small muted">{{ $vp->total == 0 ? 'Nol rupiah' : 'Total mengikuti nominal penerima' }}</em></div>
    <div class="actions">
        <a class="btn" href="{{ route('vp.pdf', $vp->id) }}" target="_blank" rel="noopener">Pratinjau PDF</a>
        <form method="post" action="{{ route('vp.destroy', $vp->id) }}" onsubmit="return confirm('Hapus draft VP ini? Tindakan ini tidak dapat dibatalkan.');">@csrf<button class="btn" type="submit">Hapus draft</button></form>
        <form method="post" action="{{ route('vp.submit', $vp->id) }}">@csrf<button class="btn gold" type="submit">Ajukan ke Checker</button></form>
    </div>
</section>

<script>
document.getElementById('vendor-search')?.addEventListener('input', function () {
    const keyword = this.value.toLowerCase().trim();
    document.querySelectorAll('#vendor-select option[data-search]').forEach(function (option) {
        option.hidden = keyword !== '' && !option.dataset.search.includes(keyword);
    });
});
</script>
@endsection
