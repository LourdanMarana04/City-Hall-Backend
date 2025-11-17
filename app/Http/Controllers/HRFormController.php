<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mpdf\Mpdf;

class HRFormController extends Controller
{
    public function generateChecklistPdf(Request $request)
    {
        $data = $request->all();
        $html = view('pdf.checklist_form', $data)->render();
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);
        return response($mpdf->Output('', 'S'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="checklist_form.pdf"');
    }
}
