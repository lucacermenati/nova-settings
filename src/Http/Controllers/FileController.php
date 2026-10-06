<?php

namespace LucaCermenati\NovaSettings\Http\Controllers;

use Illuminate\Http\Response;
use Laravel\Nova\Http\Requests\NovaRequest;
use LucaCermenati\NovaSettings\Form\FormBuilder;

class FileController
{
    public function destroy(NovaRequest $request, FormBuilder $builder, string $key): Response
    {
        $builder->deleteFile($request, $key);

        return response()->noContent(200);
    }
}
