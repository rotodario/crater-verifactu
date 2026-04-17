<!DOCTYPE html>
<html>

<head>
    <title>@lang('pdf_invoice_label') - {{ $invoice->invoice_number }}</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <style type="text/css">
        /* -- Base -- */
        body {
            font-family: "DejaVu Sans";
            color: #2C3E50;
            margin: 0;
            padding: 0;
        }

        html {
            margin: 0px;
            padding: 0px;
            margin-top: 30px;
        }

        table {
            border-collapse: collapse;
        }

        hr {
            margin: 0 30px;
            color: rgba(0,0,0,0.1);
            border: 0.5px solid #ECF0F1;
        }

        /* -- Left Accent Sidebar -- */
        .side-accent {
            position: fixed;
            top: 0;
            left: 0;
            width: 8px;
            height: 100%;
            background: linear-gradient(180deg, #FF8A3D 0%, #F97316 100%);
            background: #F97316;
        }

        /* -- Header -- */
        .header-container {
            padding: 4px 30px 6px 40px;
            width: 100%;
        }

        .header-logo {
            text-transform: capitalize;
            color: #2C3E50;
            font-size: 20px;
            font-weight: bold;
            margin: 0;
        }

        .company-address {
            font-size: 10px;
            line-height: 14px;
            color: #7F8C8D;
            word-wrap: break-word;
            margin-top: 4px;
        }

        .invoice-header-right {
            text-align: left;
            vertical-align: bottom;
            padding-left: 20px;
            padding-right: 0;
        }

        .invoice-type-label {
            font-size: 24px;
            font-weight: bold;
            color: #F97316;
            margin: 0;
            line-height: 26px;
        }

        .invoice-number-sub {
            font-size: 11px;
            color: #95A5A6;
            margin-top: 3px;
            word-break: break-word;
        }

        .header-divider {
            border: none;
            border-bottom: 3px solid #F97316;
            margin: 12px 30px 0 40px;
        }

        /* -- Content Wrapper -- */
        .content-wrapper {
            display: block;
            padding-top: 0px;
            padding-bottom: 20px;
        }

        /* -- Address Section -- */
        .address-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #F97316;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .customer-address-container {
            display: block;
            float: left;
            width: 45%;
            padding: 15px 0 0 40px;
        }

        .billing-address-container {
            display: block;
            float: left;
        }

        .billing-address {
            font-size: 10px;
            line-height: 15px;
            color: #7F8C8D;
            margin-top: 5px;
            width: 200px;
            word-wrap: break-word;
        }

        .shipping-address-container {
            float: right;
            display: block;
        }

        .shipping-address-container--left {
            float: left;
            display: block;
            padding-left: 0;
        }

        .shipping-address {
            font-size: 10px;
            line-height: 15px;
            color: #7F8C8D;
            margin-top: 5px;
            width: 160px;
            word-wrap: break-word;
        }

        /* -- Invoice Details -- */
        .invoice-details-container {
            display: block;
            float: right;
            padding: 15px 30px 0 0;
        }

        .detail-table {
            background: #FFF7ED;
            border: 1px solid #FED7AA;
        }

        .detail-table td {
            padding: 6px 12px;
        }

        .attribute-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            color: #95A5A6;
        }

        .attribute-value {
            font-size: 11px;
            text-align: right;
            color: #2C3E50;
            font-weight: bold;
        }

        /* -- Items Table -- */
        .items-table {
            margin-top: 25px;
            padding: 0px 30px 10px 40px;
            page-break-before: avoid;
            page-break-after: auto;
        }

        .items-table hr {
            height: 0.1px;
            border-color: #ECF0F1;
        }

        .item-table-heading {
            font-size: 13.5;
            text-align: center;
            padding: 5px;
            color: #2C3E50;
        }

        tr.item-table-heading-row th {
            background: #FFF7ED;
            border-bottom: 1px solid #F97316;
            border-top: 1px solid #ECF0F1;
            font-size: 9px;
            line-height: 18px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #2C3E50;
            padding: 10px 5px;
        }

        tr.item-row td {
            font-size: 11px;
            line-height: 16px;
            color: #2C3E50;
        }

        .item-cell {
            font-size: 11px;
            text-align: center;
            padding: 8px 5px;
            color: #2C3E50;
            border-bottom: 1px solid #F5F5F5;
        }

        .item-description {
            color: #95A5A6;
            font-size: 9px;
            line-height: 12px;
            margin-top: 2px;
        }

        .item-cell-table-hr {
            margin: 0 30px 0 40px;
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
            color: #7F8C8D;
            text-align: left;
            padding-left: 10px;
        }

        .total-table-attribute-value {
            font-weight: bold;
            text-align: right;
            font-size: 11px;
            color: #2C3E50;
            padding-right: 10px;
            padding-top: 2px;
            padding-bottom: 2px;
        }

        .total-border-left {
            border: 1px solid #F97316 !important;
            border-right: 0px !important;
            padding: 10px !important;
            background: #E8F8F5;
            color: #2C3E50 !important;
            font-size: 12px !important;
            font-weight: bold;
        }

        .total-border-right {
            border: 1px solid #F97316 !important;
            border-left: 0px !important;
            padding: 10px !important;
            background: #F97316;
            color: #FFFFFF !important;
            font-size: 14px !important;
            font-weight: bold;
        }

        /* -- Notes -- */
        .notes {
            font-size: 10px;
            color: #95A5A6;
            margin-top: 25px;
            margin-left: 40px;
            width: 432px;
            text-align: left;
            page-break-inside: avoid;
        }

        .notes-label {
            font-size: 9px;
            line-height: 22px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #F97316;
            font-weight: bold;
            padding-bottom: 6px;
        }

        /* -- Helpers -- */
        .text-primary { color: #F97316; }
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
    <div class="side-accent"></div>

    <div class="header-container">
        <table width="100%">
            <tr>
                <td width="60%" style="vertical-align: top; padding-left: 10px;">
                    @if ($logo)
                        <img style="height: 112px;" src="{{ $logo }}" alt="Company Logo">
                    @else
                        <h1 class="header-logo">{{ $invoice->customer->company->name }}</h1>
                    @endif
                    <div class="company-address">
                        {!! $company_address !!}
                    </div>
                </td>
                <td width="40%" class="invoice-header-right">
                    <div class="invoice-type-label">@lang('pdf_invoice_label')</div>
                    <div class="invoice-number-sub">Nº <strong style="color: #2C3E50; font-size: 13px;">{{ $invoice->invoice_number }}</strong></div>
                </td>
            </tr>
        </table>
    </div>

    <hr class="header-divider">

    <div class="content-wrapper">
        <div class="main-content">
            <div class="customer-address-container">
                <div class="billing-address-container billing-address">
                    @if ($billing_address)
                        <div class="address-label">@lang('pdf_bill_to')</div>
                        {!! $billing_address !!}
                    @endif
                </div>

                <div @if ($billing_address !== '</br>') class="shipping-address-container shipping-address" @else class="shipping-address-container--left shipping-address" @endif>
                    @if ($shipping_address && $shipping_address !== '</br>')
                        <div class="address-label" style="margin-top: 15px;">@lang('pdf_ship_to')</div>
                        {!! $shipping_address !!}
                    @endif
                </div>
                <div style="clear: both;"></div>
            </div>

            <div class="invoice-details-container">
                <table class="detail-table">
                    <tr>
                        <td class="attribute-label">@lang('pdf_invoice_number')</td>
                        <td class="attribute-value">&nbsp;{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td class="attribute-label">@lang('pdf_invoice_date')</td>
                        <td class="attribute-value">&nbsp;{{ $invoice->formattedInvoiceDate }}</td>
                    </tr>
                    <tr>
                        <td class="attribute-label">@lang('pdf_invoice_due_date')</td>
                        <td class="attribute-value">&nbsp;{{ $invoice->formattedDueDate }}</td>
                    </tr>
                </table>
            </div>
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

