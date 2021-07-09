<style>
    *, *:before, *:after {
        box-sizing: border-box;
    }

    .header {
        display: inline-block;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        height: 50mm;
        margin-left: 0;
        padding: 5mm 15mm 5mm 10mm;
        position: relative;
        text-align: left;
        width: 210mm;
    }

    .header-image {
        height: 40mm;
        margin-top: 5mm;
        margin-bottom: 0;
        position: relative;
    }

    .header-image img {
        height: auto;
        margin: 5mm 0 0 0;
        max-height: 20mm;
        max-width: 350px;
    }

    .body-header-text {
        border-bottom: 1px solid #999;
        clear: both;
        display: inline-block;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 10px;
        line-height: 2rem;
        margin-bottom: 1rem;
        width: 100%;
    }

    .body-header-text p {
        margin: 0;
    }

    .customer,
    .customer address {
        display: block;
        float: left;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        width: 55%;
        font-style: normal !important;
        font-size: 14px !important;
    }

    .data {
        float: right;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        width: 45%;
    }

    .value-id,
    .value-date,
    .value-customer {
        font-weight: bold;
    }

    .data-highlight {
        background: #e5e5e5;
        border-radius: 3px;
        margin-left: auto;
        padding: 10px;
        width: 100%;
    }

    .delivery-address {
        font-size: 12px;
        margin-top: 0;
        margin-left: auto;
        padding: 10px;
        width: 100%;
    }

    .delivery-address-label {
        font-weight: bold;
    }

    .data table {
        border-spacing: 0;
        border-collapse: collapse;
    }

    .data table td + td {
        line-height: 20px;
        padding: 0 0 0 10px;
    }

    .data-highlight h2 {
        font-size: 1.2rem;
        margin: 0 0 10px 0;
        padding: 0;
        text-align: left;
        text-transform: uppercase;
    }

    .data-highlight table {
        border-collapse: separate;
        width: 100%;
    }

    .data-highlight td {
        font-size: 14px;
    }

    .data-highlight td:first-child {
        width: 35%;
    }

    .data-highlight td span {
        background: #fff;
        border-radius: 3px;
        display: inline-block;
        margin: 0 0 2px;
        padding: 0 10px;
        width: 160px;
    }

    .customer h3 {
        margin: 0 0 10px 0;
    }

    .header-text {
        border-bottom: 1px solid #999;
        clear: both;
        display: inline-block;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 10px;
        line-height: 2rem;
        margin-bottom: 1rem;
        width: 100%;
    }
</style>

<!-- Workaround to achieve full a4 format as PDF file -->
<div style="position: absolute; left: 0; top: 0; width: 210mm; background: #fff; height: 5mm; z-index: -1;"></div>
<div style="position: absolute; left: 0; top: 0; width: 5mm; background: #fff; height: 297mm; z-index: -1;"></div>

<div class="header header-top">
    <div class="header-image">
        {assign var=Logo value=\QUI\ERP\Defaults::getLogo()}
        {image image=$Logo height="120" svgtopng=1 host=1}
    </div>

    <div class="header-text">
        {$shortAddress}
    </div>

    <div class="header-line">

    </div>
</div>
