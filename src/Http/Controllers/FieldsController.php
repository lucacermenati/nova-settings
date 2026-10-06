<?php

namespace LucaCermenati\NovaSettings\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Laravel\Nova\Http\Requests\NovaRequest;
use LucaCermenati\NovaSettings\Form\FormBuilder;

class FieldsController
{
    public function index(NovaRequest $request, FormBuilder $builder): JsonResponse
    {
        return response()->json($builder->serialize($request));
    }

    public function store(NovaRequest $request, FormBuilder $builder): Response
    {
        $builder->save($request);

        return response()->noContent();
    }
}
