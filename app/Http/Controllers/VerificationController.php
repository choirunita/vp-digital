<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function show(string $code): View
    {
        $vp = DB::table('vp as v')->leftJoin('users as checker', 'checker.id', '=', 'v.checker_id')->leftJoin('users as approver', 'approver.id', '=', 'v.approver1_id')->select('v.*', 'checker.full_name as checker_name', 'approver.full_name as approver_name')->where('v.verify_code', $code)->first();
        if (!$vp) return view('verify', ['vp' => null, 'match' => false]);
        $lines = DB::table('vp_lines')->where('vp_id', $vp->id)->orderBy('id')->get(['name','bank','account_no','account_name','amount']);
        $hash = hash('sha256', json_encode(['vp' => $vp->reg_no, 'revision' => $vp->revision, 'total' => $vp->total, 'lines' => $lines], JSON_UNESCAPED_UNICODE));
        $vp->recipient_count = $lines->count();
        return view('verify', ['vp' => $vp, 'match' => hash_equals((string) $vp->doc_hash, $hash)]);
    }
}
