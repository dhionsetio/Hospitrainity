<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\Vocabulary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegacyEvidenceController extends Controller
{
    public function index(Request $request): View
    {
        foreach ([Module::class, Lesson::class, Vocabulary::class, Material::class, Exercise::class] as $model) {
            $this->authorize('viewAny', $model);
        }

        return view('superadmin.legacy-evidence.index', [
            'counts' => [
                'modules' => Module::query()->count(),
                'lessons' => Lesson::query()->count(),
                'vocabularies' => Vocabulary::query()->count(),
                'materials' => Material::query()->count(),
                'exercises' => Exercise::query()->count(),
            ],
            'routePrefix' => $request->user()->isSuperAdmin() ? 'superadmin' : 'admin',
        ]);
    }
}
