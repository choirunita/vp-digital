<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 38px 40px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172019; font-size: 10px; }
        table { border-collapse: collapse; width: 100%; }
        .head td { vertical-align: middle; }
        .logo { width: 235px; max-height: 82px; object-fit: contain; object-position: left center; }
        .doc-title { color: #254f31; font-size: 23px; font-weight: bold; line-height: 1.15; text-align: right; }
        .doc-number { font-size: 14px; font-weight: bold; text-align: right; margin-top: 3px; }
        .doc-date { color: #59635a; font-size: 11px; text-align: right; margin-top: 4px; }
        .rule { height: 3px; background: #295536; margin: 12px 0 11px; }
        .details td { padding: 3px 2px; vertical-align: top; }
        .details .label { width: 17%; color: #576158; }
        .details .value { width: 33%; font-weight: bold; }
        .items { margin-top: 18px; }
        .items th { color: #fff; background: #295536; font-size: 10px; font-weight: bold; padding: 8px 6px; text-align: left; }
        .items th.amount, .items td.amount { text-align: right; }
        .items td { border-bottom: 1px solid #d9e0d9; padding: 7px 6px; vertical-align: top; }
        .items .empty td { height: 23px; }
        .items .total td { background: #edf3ed; border: 0; font-size: 12px; font-weight: bold; padding: 8px 6px; }
        .spellout { margin: 7px 2px 0; font-style: italic; font-size: 10px; }
        .signatures { margin-top: 21px; table-layout: fixed; }
        .signatures td { border: 1px solid #afbdaf; height: 148px; padding: 8px; text-align: center; vertical-align: top; }
        .signatures td.last { background: #fffdf3; }
        .sign-title { color: #295536; font-size: 11px; font-weight: bold; }
        .sign-space { height: 75px; }
        .sign-line { border-top: 1px solid #243026; padding-top: 4px; font-weight: bold; }
        .sign-role { color: #59635a; font-size: 9px; margin-top: 7px; }
        .wet-note { color: #9e8a4b; font-size: 9px; font-style: italic; margin-top: 35px; }
        .draft-mark { position: fixed; left: 60px; top: 440px; width: 480px; text-align: center; color: #e8ddbd; font-size: 48px; font-weight: bold; line-height: 1.45; transform: rotate(-32deg); }
        .footer { border-top: 1px solid #d9e0d9; color: #69746b; font-size: 7px; margin-top: 16px; padding-top: 5px; }
    </style>
</head>
<body>
    @if ($isDraft)
        <div class="draft-mark">DRAF · BELUM<br>DISETUJUI</div>
    @endif

    <table class="head">
        <tr>
            <td style="width:48%">
                @if ($logo)<img class="logo" src="{{ $logo }}" alt="Petrokopindo Cipta Selaras">@endif
            </td>
            <td style="width:52%">
                <div class="doc-title">VOUCHER PEMBAYARAN</div>
                <div class="doc-number">{{ $vp->reg_no ?: 'DRAF (belum bernomor)' }}</div>
                <div class="doc-date">Tanggal pembayaran {{ $vp->pay_date ? \Carbon\Carbon::parse($vp->pay_date)->locale('id')->translatedFormat('d F Y') : '-' }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="details">
        <tr>
            <td class="label">Bank pembayar</td><td class="value">{{ $vp->pcs_bank ?: '-' }} · {{ $vp->pcs_account_no ?: '-' }}</td>
            <td class="label">No. referensi</td><td class="value">{{ $vp->ref_no ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Jenis</td><td class="value">{{ $vp->kind === 'MASS' ? 'Massal' : 'Non massal' }} · {{ $lines->count() }} penerima</td>
            <td class="label">Uraian</td><td class="value">{{ $vp->description ?: '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th><th style="width:27%">Nama penerima</th><th style="width:11%">Bank</th><th style="width:18%">No. rekening</th><th style="width:22%">Atas nama</th><th class="amount" style="width:17%">Nominal (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $index => $line)
                <tr><td>{{ $index + 1 }}</td><td>{{ $line->name }}</td><td>{{ $line->bank }}</td><td>{{ $line->account_no }}</td><td>{{ $line->account_name }}</td><td class="amount">{{ number_format($line->amount, 0, ',', '.') }}</td></tr>
            @empty
                <tr class="empty"><td colspan="6"></td></tr>
            @endforelse
        </tbody>
        <tfoot class="total"><tr><td colspan="5">TOTAL</td><td class="amount">{{ number_format($vp->total, 0, ',', '.') }}</td></tr></tfoot>
    </table>

    <p class="spellout">Terbilang: {{ $vp->total == 0 ? 'Nol rupiah' : '—' }}</p>

    <table class="signatures">
        <tr>
            <td><div class="sign-title">Dibuat</div><div class="sign-space"></div><div class="sign-line">{{ $vp->maker_name ?: '-' }}</div><div class="sign-role">Maker</div></td>
            <td><div class="sign-title">Diperiksa</div><div class="sign-space"></div><div class="sign-line">{{ $vp->checker_name ?: '' }}</div><div class="sign-role">Checker</div></td>
            <td><div class="sign-title">Disetujui</div><div class="sign-space"></div><div class="sign-line">{{ $vp->approver_name ?: '' }}</div><div class="sign-role">Approver 1</div></td>
            <td class="last"><div class="sign-title">Disahkan</div><div class="wet-note">tanda tangan basah</div><div class="sign-space" style="height:45px"></div><div class="sign-line">Direktur Utama</div><div class="sign-role">Tgl: ........................</div></td>
        </tr>
    </table>

    <div class="footer">Dokumen dibuat melalui VP Digital · {{ now()->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB</div>
</body>
</html>
