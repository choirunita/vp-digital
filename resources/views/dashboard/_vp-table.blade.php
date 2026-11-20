<div class="tbl-wrap">
    <table class="tbl stack">
        <thead><tr><th>No. registrasi</th><th>Bank · referensi</th><th>Penerima / uraian</th><th>Jenis</th><th class="r">Nominal (Rp)</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($rows as $vp)
            <tr>
                <td data-l="No. registrasi"><a href="{{ route('vp.show', $vp->id) }}"><b>{{ $vp->reg_no ?: 'Draft #'.$vp->id }}</b></a><div class="small muted">{{ \Carbon\Carbon::parse($vp->created_at)->timezone('Asia/Jakarta')->format('d M Y, H:i') }}</div></td>
                <td data-l="Bank · referensi"><b>{{ $vp->pcs_bank ?: '-' }}</b><div class="small muted">{{ $vp->ref_no ?: '' }}</div></td>
                <td data-l="Penerima / uraian">{{ $vp->kind === 'MASS' ? $vp->n_lines.' penerima' : ($vp->first_name ?: '-') }}<div class="small muted">{{ $vp->description ?: '-' }}</div></td>
                <td data-l="Jenis"><span class="pill {{ $vp->kind === 'MASS' ? 'st-wait' : 'st-draft' }}">{{ $vp->kind === 'MASS' ? 'Massal' : 'Non massal' }}</span></td>
                <td data-l="Nominal" class="r"><b>{{ number_format($vp->total, 0, ',', '.') }}</b></td>
                <td data-l="Status"><span class="pill {{ $statusClasses[$vp->status] ?? '' }}">{{ $statusLabels[$vp->status] ?? $vp->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">{{ $empty }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
