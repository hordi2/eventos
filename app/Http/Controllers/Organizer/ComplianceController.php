<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\GetProcessingRegister;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ComplianceController extends Controller
{
    public function register(GetProcessingRegister $action): InertiaResponse
    {
        return Inertia::render('Compliance/Register', ['activities' => $action->handle()]);
    }

    public function exportRegisterPdf(GetProcessingRegister $action): Response
    {
        $pdf = Pdf::loadView('compliance.register-pdf', ['activities' => $action->handle()])->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="registre-des-traitements.pdf"',
        ]);
    }
}
