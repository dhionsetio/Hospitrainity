<?php

namespace App\Http\Controllers;

use App\Services\PolicyDocumentRegistry;
use Illuminate\View\View;

class PublicPolicyController extends Controller
{
    public function show(string $type, PolicyDocumentRegistry $policies): View
    {
        abort_unless(in_array($type, PolicyDocumentRegistry::TYPES, true), 404);

        return view('policies.show', ['document' => $policies->document($type)]);
    }
}
