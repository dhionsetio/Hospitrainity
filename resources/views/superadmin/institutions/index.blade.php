@extends('layouts.app')

@section('title', __('Institutions - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('Institutions') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Overview of registered partner institutions, verification methods, and active member counts.') }}</p>
            </header>

            <section class="rounded-lg bg-white p-6 shadow-md" aria-labelledby="institutions-table-heading">
                <h2 id="institutions-table-heading" class="sr-only">{{ __('Institutions list') }}</h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-neutral-600">
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-700">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Institution Name') }}</th>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Identifier Key') }}</th>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Active Members') }}</th>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Verification Method') }}</th>
                                <th scope="col" class="px-6 py-3 font-semibold">{{ __('Registered') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($institutions as $institution)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-6 py-4 font-bold text-neutral-900">
                                        {{ $institution->displayName(app()->getLocale()) }}
                                    </td>
                                    <td class="px-6 py-4 font-mono text-xs text-neutral-600">
                                        {{ $institution->key }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">
                                            {{ ucfirst($institution->status->value ?? (string) $institution->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-neutral-800">
                                        {{ $institution->memberships_count }}
                                    </td>
                                    <td class="px-6 py-4 text-neutral-600">
                                        {{ $institution->verification_method ?? __('Manual') }}
                                    </td>
                                    <td class="px-6 py-4 text-xs text-neutral-500">
                                        {{ $institution->created_at?->format('Y-m-d') ?? 'N/A' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-neutral-500">
                                        {{ __('No institutions registered yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($institutions->hasPages())
                    <div class="mt-6">
                        {{ $institutions->links() }}
                    </div>
                @endif
            </section>
        </main>
    </div>
@endsection
