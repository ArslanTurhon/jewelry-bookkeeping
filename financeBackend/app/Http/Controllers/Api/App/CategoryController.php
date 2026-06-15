<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::query()->orderBy('type')->orderBy('id');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return response()->json($query->get());
    }
}
