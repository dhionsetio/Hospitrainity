<?php

namespace App\Http\Controllers\Auth;

use App\Enums\WorkContextRole;
use App\Http\Controllers\Controller;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordConfirmationController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.confirm-password', [
            'confirmationContext' => $this->destination($request)['context'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $request->user()->password)) {
            return back()->withErrors([
                'password' => __('auth.password'),
            ]);
        }

        $destination = $this->destination($request);
        $request->session()->passwordConfirmed();
        $request->session()->forget('url.intended');

        return redirect($destination['url'])
            ->with('status', __("admin.password_confirmed.{$destination['context']}"));
    }

    /**
     * Resolve only the privileged destinations that deliberately share this
     * confirmation screen. Never trust a pre-authentication intended URL.
     *
     * @return array{context: 'audit'|'users'|'institution_roles'|'privacy'|'privacy_admin'|'security', url: string}
     */
    private function destination(Request $request): array
    {
        $workRole = app(WorkContext::class)->current($request, $request->user());
        $fallback = $workRole === WorkContextRole::InstitutionAdmin
            ? [
                'context' => 'institution_roles',
                'url' => route('supervisor.institution-roles.index', absolute: false),
            ]
            : [
                'context' => 'users',
                'url' => $workRole === WorkContextRole::SystemAdmin
                    ? route('superadmin.users.index', absolute: false)
                    : route($workRole->landingRoute(), absolute: false),
            ];
        $intended = $request->session()->get('url.intended');

        if (! is_string($intended) || $intended === '' || strlen($intended) > 2048) {
            return $fallback;
        }

        $parts = parse_url($intended);

        if (! is_array($parts)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return $fallback;
        }

        $intendedOrigin = strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));

        if (! hash_equals(strtolower($request->getSchemeAndHttpHost()), $intendedOrigin)) {
            return $fallback;
        }

        $destinations = [
            route('security.confirm', absolute: false) => [
                'context' => 'security',
                'filters' => [],
                'destination' => route('security.index', absolute: false),
            ],
            route('superadmin.audit.index', absolute: false) => [
                'context' => 'audit',
                'filters' => [
                    'category' => 20,
                    'event' => 80,
                    'actor' => 100,
                    'from' => 10,
                    'to' => 10,
                ],
            ],
            route('superadmin.users.index', absolute: false) => [
                'context' => 'users',
                'filters' => [
                    'q' => 100,
                    'role' => 20,
                    'institution' => 255,
                    'verification' => 20,
                ],
            ],
            route('supervisor.institution-roles.index', absolute: false) => [
                'context' => 'institution_roles',
                'filters' => [],
            ],
            route('superadmin.institution-roles.index', absolute: false) => [
                'context' => 'institution_roles',
                'filters' => [],
            ],
        ];
        $target = $destinations[$parts['path']] ?? null;

        if (preg_match('#\A/privacy/requests/sensitive/(access-export|deletion)\z#', $parts['path']) === 1) {
            $target = ['context' => 'privacy', 'filters' => []];
        }
        if (preg_match('#\A/superadmin/privacy-requests(?:/[0-9a-fA-F-]{36})?\z#', $parts['path']) === 1) {
            $target = ['context' => 'privacy_admin', 'filters' => ['status' => 30]];
        }

        if ($target === null) {
            return $fallback;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $submittedQuery);

        foreach ($target['filters'] as $filter => $maximumLength) {
            $value = $submittedQuery[$filter] ?? null;

            if (is_string($value) && strlen($value) <= $maximumLength) {
                $query[$filter] = $value;
            }
        }

        return [
            'context' => $target['context'],
            'url' => ($target['destination'] ?? $parts['path']).($query === [] ? '' : '?'.http_build_query($query)),
        ];
    }
}
