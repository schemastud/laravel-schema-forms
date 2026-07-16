<?php

use Illuminate\Support\Facades\Route;
use Splicewire\SchemaForms\Http\SchemaFormController;

Route::post('schema-forms/{form}', SchemaFormController::class)
    ->middleware('throttle:5,1')
    ->name('schema-forms.submit');
