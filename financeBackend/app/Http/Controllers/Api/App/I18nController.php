<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Support\BusinessDictionary;
use Illuminate\Http\Request;

class I18nController extends Controller
{
    public function __invoke(Request $request, BusinessDictionary $dictionary)
    {
        return response()->json($dictionary->catalog(
            $request->header('X-Language', $request->query('lang', BusinessDictionary::DEFAULT_LANGUAGE))
        ));
    }
}
