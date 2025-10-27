<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Checklist of Request</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .title { text-align: center; font-weight: bold; margin-bottom: 20px; color: #e3342f; font-size: 24px; letter-spacing: 1px; }
        .section { margin-bottom: 10px; }
        .label { display: inline-block; width: 120px; font-weight: bold; }
        .checkbox { margin-right: 10px; }
        .signature { margin-top: 40px; text-align: right; }
        .line { border-bottom: 1px solid #000; display: inline-block; width: 200px; }
    </style>
</head>
<body>
    <div class="title">CHECKLIST OF REQUEST</div>
    <div class="section">
        <span class="label">Date:</span> <span class="line">{{ $date ?? '_____________' }}</span><br>
        <span class="label">Name:</span> <span class="line">{{ $name ?? '_____________' }}</span><br>
        <span class="label">Position:</span> <span class="line">{{ $position ?? '_____________' }}</span><br>
        <span class="label">Department:</span> <span class="line">{{ $department ?? '_____________' }}</span>
    </div>
    <div class="section">
        Please Check appropriate box<br>
        <div><input type="checkbox" class="checkbox" {{ !empty($service_record) ? 'checked' : '' }}> Service Record</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($coe) ? 'checked' : '' }}> COE</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($codec) ? 'checked' : '' }}> CODEC</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($cert_leave_credits) ? 'checked' : '' }}> Cert. of Leave Credits</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($automated_dtr) ? 'checked' : '' }}> Automated DTR</div>
        <div style="margin-left: 20px;">Month: <span class="line">{{ $dtr_month ?? '________' }}</span></div>
        <div><input type="checkbox" class="checkbox" {{ !empty($payslip) ? 'checked' : '' }}> Payslip</div>
        <div style="margin-left: 20px;">Pay period: <span class="line">{{ $pay_period ?? '________' }}</span></div>
        <div><input type="checkbox" class="checkbox" {{ !empty($travel_order) ? 'checked' : '' }}> Travel Order (Pls. attach invitation)</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($itinerary_of_travel) ? 'checked' : '' }}> Itinerary of travel (Pls. attach: Travel Order or Trip Ticket, Cert. of Appearance & Attendance, Receipts, Summary of Travel Expenses)</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($terminal_pay) ? 'checked' : '' }}> Terminal Pay & Commutation of Leave Pay (Pls. attach Leave Credits, Service Record, NOSA, Appointment, Clearance form, Resignation Letter)</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($copy_201_file) ? 'checked' : '' }}> Copy of 201 File</div>
        <div style="margin-left: 20px;">Pls. specify type of document: <span class="line">{{ $type_of_document ?? '________' }}</span></div>
        <div><input type="checkbox" class="checkbox" {{ !empty($acceptance_resignation) ? 'checked' : '' }}> Acceptance of Resignation (Pls. attach resignation letter)</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($saln_blank) ? 'checked' : '' }}> SALN blank form</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($pds_form) ? 'checked' : '' }}> PDS Form</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($dtr_blank) ? 'checked' : '' }}> DTR Blank</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($cert_completion) ? 'checked' : '' }}> Cert. of Completion (OJT)</div>
        <div><input type="checkbox" class="checkbox" {{ !empty($others) ? 'checked' : '' }}> Others/pls. specify: <span class="line">{{ $others_specify ?? '________' }}</span></div>
    </div>
    <div class="signature">
        ___________________________<br>
        Employee's Signature
    </div>
</body>
</html>
