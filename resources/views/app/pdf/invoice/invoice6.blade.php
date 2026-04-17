<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_invoice_label') - {{ $invoice->invoice_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* -- Base -- */
        body {
            font-family: "DejaVu Sans";
            color: #1A1A2E;
            margin: 0;
            padding: 0;
        }

        html {
            margin: 0px;
            padding: 0px;
            margin-top: 0px;
        }

        table {
            border-collapse: collapse;
        }

        hr {
            margin: 0 30px;
            color: rgba(0, 0, 0, 0.1);
            border: 0.5px solid #E8E8E8;
        }

        /* -- Header -- */
        .header-container {
            background: #1A1A2E;
            position: absolute;
            width: 100%;
            height: 130px;
            left: 0px;
            top: 0px;
        }

        .header-logo {
            text-transform: capitalize;
            color: #FFFFFF;
            font-size: 20px;
            font-weight: bold;
            margin: 0;
            padding-top: 30px;
        }

        .header-section-left {
            padding-left: 30px;
            vertical-align: middle;
        }

        .header-section-right {
            text-align: right;
            padding-right: 30px;
            vertical-align: middle;
        }

        .invoice-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #E94560;
            font-weight: bold;
        }

        .invoice-number-header {
            font-size: 22px;
            font-weight: bold;
            color: #FFFFFF;
            margin: 2px 0 0 0;
        }

        .invoice-date-header {
            font-size: 10px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        /* -- Content Wrapper -- */
        .content-wrapper {
            display: block;
            margin-top: 100px;
            padding-bottom: 20px;
        }

        /* -- Company Address -- */
        .company-address-container {
            padding: 25px 0 0 30px;
            float: left;
            width: 45%;
        }

        .company-address {
            font-size: 10px;
            line-height: 14px;
            color: #636E72;
            word-wrap: break-word;
            margin-top: 4px;
        }

        /* -- Billing -- */
        .billing-address-container {
            display: block;
            float: right;
            padding: 25px 30px 0 0;
        }

        .billing-address {
            font-size: 10px;
            line-height: 15px;
            color: #636E72;
            margin-top: 5px;
            width: 200px;
            word-wrap: break-word;
        }

        /* -- Shipping -- */
        .shipping-address-container {
            float: right;
            display: block;
            padding-right: 30px;
        }

        .shipping-address-container--left {
            float: left;
            display: block;
            padding-left: 0;
        }

        .shipping-address {
            font-size: 10px;
            line-height: 15px;
            color: #636E72;
            margin-top: 5px;
            width: 160px;
            word-wrap: break-word;
        }

        .address-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #E94560;
            font-weight: bold;
            margin-bottom: 4px;
        }

        /* -- Invoice Details -- */
        .invoice-details-container {
            display: block;
            float: right;
            padding: 10px 30px 0 0;
        }

        .attribute-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            color: #95A5A6;
            padding-right: 15px;
        }

        .attribute-value {
            font-size: 11px;
            text-align: right;
            color: #1A1A2E;
            font-weight: bold;
        }

        /* -- Items Table -- */
        .items-table {
            margin-top: 30px;
            padding: 0px 30px 10px 30px;
            page-break-before: avoid;
            page-break-after: auto;
        }

        .items-table hr {
            height: 0.1px;
            border-color: #F0F0F0;
        }

        .item-table-heading {
            font-size: 13.5;
            text-align: center;
            padding: 5px;
            color: #1A1A2E;
        }

        tr.item-table-heading-row th {
            background: #F8F9FA;
            border-bottom: 2px solid #1A1A2E;
            font-size: 9px;
            line-height: 18px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #1A1A2E;
            padding: 10px 5px;
        }

        tr.item-row td {
            font-size: 11px;
            line-height: 16px;
            color: #2D3436;
        }

        .item-cell {
            font-size: 11px;
            text-align: center;
            padding: 8px 5px;
            color: #2D3436;
            border-bottom: 1px solid #F0F0F0;
        }

        .item-description {
            color: #95A5A6;
            font-size: 9px;
            line-height: 12px;
            margin-top: 2px;
        }

        .item-cell-table-hr {
            margin: 0 30px;
        }

        /* -- Total Display Table -- */
        .total-display-container {
            padding: 0 25px;
        }

        .total-display-table {
            border-top: none;
            page-break-inside: avoid;
            page-break-before: auto;
            page-break-after: auto;
            margin-top: 15px;
            float: right;
            width: auto;
        }

        .total-table-attribute-label {
            font-size: 11px;
            color: #636E72;
            text-align: left;
            padding-left: 10px;
        }

        .total-table-attribute-value {
            font-weight: bold;
            text-align: right;
            font-size: 11px;
            color: #1A1A2E;
            padding-right: 10px;
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .total-border-left {
            border: 2px solid #1A1A2E !important;
            border-right: 0px !important;
            padding: 10px !important;
            background: #1A1A2E;
            color: #FFFFFF !important;
            font-size: 12px !important;
        }

        .total-border-right {
            border: 2px solid #1A1A2E !important;
            border-left: 0px !important;
            padding: 10px !important;
            background: #1A1A2E;
            color: #E94560 !important;
            font-size: 14px !important;
            font-weight: bold;
        }

        /* -- Notes -- */
        .notes {
            font-size: 10px;
            color: #95A5A6;
            margin-top: 25px;
            margin-left: 30px;
            width: 442px;
            text-align: left;
            page-break-inside: avoid;
        }

        .notes-label {
            font-size: 9px;
            line-height: 22px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #1A1A2E;
            font-weight: bold;
            padding-bottom: 6px;
        }

        /* -- Helpers -- */
        .text-primary { color: #E94560; }
        .text-center { text-align: center; }
        table .text-left { text-align: left; }
        table .text-right { text-align: right; }
        .border-0 { border: none; }
        .py-2 { padding-top: 2px; padding-bottom: 2px; }
        .py-8 { padding-top: 8px; padding-bottom: 8px; }
        .py-3 { padding: 3px 0; }
        .pr-20 { padding-right: 20px; }
        .pr-10 { padding-right: 10px; }
        .pl-20 { padding-left: 20px; }
        .pl-10 { padding-left: 10px; }
        .pl-0 { padding-left: 0; }
    </style>

    @if (App::isLocale('th'))
        @include('app.pdf.locale.th')
    @endif
</head>

<body>
    <div class="header-container">
        <table width="100%" style="padding-top: 20px;">
            <tr>
                <td width="55%" class="header-section-left">
                    @if ($logo)
                        <img style="height: 82px;" src="{{ $logo }}" alt="Company Logo">
                    @elseif ($invoice->customer->company)
                        <h1 class="header-logo">{{ $invoice->customer->company->name }}</h1>
                    @endif
                </td>
                <td width="45%" class="header-section-right">
                    <div class="invoice-label">@lang('pdf_invoice_label')</div>
                    <div class="invoice-number-header">Nº {{ $invoice->invoice_number }}</div>
                    <div class="invoice-date-header">{{ $invoice->formattedInvoiceDate }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="content-wrapper">
        <div style="overflow: hidden;">
            <div class="company-address-container company-address">
                {!! $company_address !!}
            </div>

            <div class="billing-address-container billing-address">
                @if ($billing_address)
                    <div class="address-label">@lang('pdf_bill_to')</div>
                    {!! $billing_address !!}
                @endif
            </div>

            <div style="clear: both;"></div>
        </div>

        <div style="padding: 10px 30px 0 0; overflow: hidden;">
            @if ($shipping_address && $shipping_address !== '</br>')
                <div class="shipping-address-container shipping-address" style="float: left; padding-left: 30px;">
                    <div class="address-label">@lang('pdf_ship_to')</div>
                    {!! $shipping_address !!}
                </div>
            @endif

            <table style="float: right; margin-right: 30px;">
                <tr>
                    <td class="attribute-label">@lang('pdf_invoice_date')</td>
                    <td class="attribute-value">&nbsp;{{ $invoice->formattedInvoiceDate }}</td>
                </tr>
                <tr>
                    <td class="attribute-label">@lang('pdf_invoice_due_date')</td>
                    <td class="attribute-value">&nbsp;{{ $invoice->formattedDueDate }}</td>
                </tr>
            </table>
            <div style="clear: both;"></div>
        </div>

        @include('app.pdf.invoice.partials.table')

        <div class="notes">
            @if ($notes)
                <div class="notes-label">
                    @lang('pdf_notes')
                </div>
                {!! $notes !!}
            @endif
        </div>

    </div>
    @include('app.pdf.invoice.partials.verifactu')
</body>

</html>

