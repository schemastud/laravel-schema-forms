<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Rushing\FormSubmissions\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('.');
