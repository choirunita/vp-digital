<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $rows = DB::table('audit_log')
            ->when($query !== '', fn ($builder) => $builder->where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")->orWhere('action', 'like', "%{$query}%")->orWhere('detail', 'like', "%{$query}%");
            }))
            ->orderByDesc('id')->limit(500)->get();
        return view('audit.index', compact('rows', 'query'));
    }
}
