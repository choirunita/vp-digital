<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PdfController extends Controller
{
    public function show(Request $request, int $id): Response
    {
        $vp = DB::table('vp as v')
            ->leftJoin('pcs_accounts as p', 'p.id', '=', 'v.pcs_account_id')
            ->leftJoin('users as maker', 'maker.id', '=', 'v.maker_id')
            ->leftJoin('users as checker', 'checker.id', '=', 'v.checker_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'v.approver1_id')
            ->select(
                'v.*',
                'p.bank as pcs_bank',
                'p.account_no as pcs_account_no',
                'p.account_name as pcs_account_name',
                'maker.full_name as maker_name',
                'checker.full_name as checker_name',
                'approver.full_name as approver_name'
            )
            ->where('v.id', $id)
            ->first();

        abort_unless($vp, 404);
        abort_if($request->user()->role === 'maker' && $vp->maker_id !== $request->user()->id, 403);

        $lines = DB::table('vp_lines')->where('vp_id', $id)->orderBy('id')->get();
        $isDraft = ! in_array($vp->status, ['DISETUJUI', 'DICETAK', 'SELESAI'], true);

        DB::table('audit_log')->insert([
            'at' => now(),
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => $isDraft ? 'VP_PDF_PRATINJAU' : 'VP_PDF_PRATINJAU_FINAL',
            'entity' => 'vp',
            'entity_id' => $vp->id,
            'detail' => $vp->reg_no ?: 'Draft #'.$vp->id,
            'ip' => $request->ip(),
        ]);

        $filename = str_replace('/', '-', $vp->reg_no ?: 'draft-vp-'.$vp->id).'.pdf';
        $logoPath = public_path('img/logo-pcs.png');
        $logo = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;

        return Pdf::loadView('pdf.vp', compact('vp', 'lines', 'isDraft', 'logo'))
            ->setPaper('a4', 'portrait')
            ->stream($filename);
    }
}
