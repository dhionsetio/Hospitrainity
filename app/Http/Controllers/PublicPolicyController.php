<?php

namespace App\Http\Controllers;

use App\Services\PolicyDocumentRegistry;
use App\Services\RoleLandingResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPolicyController extends Controller
{
    public function show(
        Request $request,
        string $type,
        PolicyDocumentRegistry $policies,
        RoleLandingResolver $landing,
    ): View {
        abort_unless(in_array($type, PolicyDocumentRegistry::TYPES, true), 404);

        return view('policies.show', [
            'document' => $policies->document($type),
            'returnUrl' => $request->user() === null ? url('/') : $landing->url($request->user()),
            'returnLabel' => $request->user() === null ? __('Back to Hospitrainity') : __('Return to current dashboard'),
        ]);
    }
}
