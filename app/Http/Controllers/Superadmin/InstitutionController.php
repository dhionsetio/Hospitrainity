<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstitutionController extends Controller
{
    public function index(Request $request): View
    {
        $institutions = Institution::query()
            ->withCount('memberships')
            ->orderBy('name_id')
            ->paginate(15);

        return view('superadmin.institutions.index', compact('institutions'));
    }
}
