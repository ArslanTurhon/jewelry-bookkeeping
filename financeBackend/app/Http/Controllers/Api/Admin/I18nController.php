<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Language;
use App\Models\Translation;
use App\Support\BusinessDictionary;
use Illuminate\Http\Request;

class I18nController extends Controller
{
    public function catalog(Request $request, BusinessDictionary $dictionary)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json($dictionary->catalog(
            $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE))
        ));
    }

    public function languages(Request $request)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(Language::query()->orderBy('sort_order')->get());
    }

    public function saveLanguage(Request $request)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'enabled' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        return response()->json(Language::query()->updateOrCreate(
            ['code' => $data['code']],
            $data + ['enabled' => true, 'sort_order' => 0],
        ));
    }

    public function translations(Request $request)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(Translation::query()
            ->when($request->filled('language_code'), fn ($query) => $query->where('language_code', $request->string('language_code')))
            ->orderBy('translation_key')
            ->get());
    }

    public function saveTranslation(Request $request)
    {
        if (! $this->admin($request)) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'language_code' => ['required', 'exists:languages,code'],
            'translation_key' => ['required', 'string', 'max:255'],
            'translation_value' => ['required', 'string'],
        ]);

        return response()->json(Translation::query()->updateOrCreate(
            ['language_code' => $data['language_code'], 'translation_key' => $data['translation_key']],
            ['translation_value' => $data['translation_value']],
        ));
    }

    private function admin(Request $request): ?AdminUser
    {
        $token = $request->bearerToken();

        return $token ? AdminUser::query()->where('api_token', $token)->first() : null;
    }
}
