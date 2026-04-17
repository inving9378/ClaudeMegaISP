

<html>
    <head>
        <style type="text/css">
            @page {
                sheet-size: 209mm 279.5mm;
                margin: 80px 0 0 0;
                margin-header: 0mm;
                footer: footer;
                header: secondpageheader;
                margin-bottom: 80px;
                margin-footer: 0mm;
                border: 0px;
            }

            @page :first {
                header: firstpage;
            }

            @page callsPage {
                header: secondpageheader;
            }

            @page voiceDetailsPage {
                header: secondpageheader;
            }

            .voiceDetailsPage {
                padding: 17px 20px 0 20px;
                page-break-before: right;
                page: voiceDetailsPage;
            }

            .callsPage {
                padding: 17px 20px 0 20px;
                page-break-before: right;
                page: callsPage;
            }

            * {
                box-sizing: border-box;
            }

            .page_holder {
                background: #fff;
                font-family: 'Helvetica', sans-serif;
                margin: 0 auto;
                padding: 0;
            }

            .header {
                margin: 0;
                padding: 10px 20px;
                background: #1976d2;
                width: 100%;
                border: 0;
                text-align: center;
            }

            .header table {
                border-colapse: colapse;
                border: 0;
                width: 100%;
            }

            .header table td {
                vertical-align: middle;
            }

            .header table td.logo {
                text-align: left;
                width: auto;
            }

            .header table td.header_name {
                text-align: left;
                line-height: 28px;
                font-size: 20px;
                font-weight: normal;
                color: #ffffff;
                text-transform: uppercase;
                font-family: 'Helvetica', sans-serif;
                padding-left: 20px;
            }

            .header2 {
                margin: 0;
                padding: 14px 20px;
                background: #1976d2;
                width: 100%;
                border: 0;
                text-align: center;
            }

            .header2 .logo {
                display: block;
                text-align: left;
                line-height: 30px;
                margin-right: 16px;
                padding-top: 0px;
            }

            .header2 .header_name2 {
                float: left;
                line-height: 20px;
                font-size: 20px;
                font-weight: normal;
                width: 100%;
                color: #ffffff;
                text-transform: uppercase;
                padding-top: 8px;
                font-family: 'Helvetica', sans-serif;
                text-align: left;
            }

            .clear {
                clear: both;
                width: 100%;
            }

            .decor_line {
                margin-top: 20px;
                border-top: 1px solid #e0e0e0;
            }

            .first_line table {
                width: 100%;
                font-family: 'Helvetica', sans-serif;
            }

            .line_heading {
                font-family: 'Helvetica', sans-serif;
                font-size: 40px;
                font-weight: bold;
                color: #000;
                text-align: left;
                line-height: 24px;
                padding: 0;
            }

            .first_line table td.invoice_detais {
                width: 40%;
                font-family: 'Helvetica', sans-serif;
                font-size: 14px;
            }

            .second_line {
                padding-top: 20px;
            }

            .second_line table {
                vertical-align: top;
                width: 100%;
            }

            .second_line table td.left_part {
                width: 55%;
            }

            .second_line table td.right_part {
                width: 40%;
            }

            .second_line table td {
                font-size: 12px;
                font-family: 'Helvetica', sans-serif;
            }

            .second_line table td.left_part .heading,
            .second_line table td.right_part .heading {
                font-size: 12px;
                font-weight: bold;
                color: #000000;
                padding-bottom: 7px;
                padding-left: 0;
                padding-right: 0;
                font-family: 'Helvetica', sans-serif;
            }

            .second_line table td.left_part .name,
            .second_line table td.right_part .name {
                font-size: 14px;
                font-weight: bold;
                color: #000000;
                padding-bottom: 7px;
                padding-left: 0;
                padding-right: 0;
                font-family: 'Helvetica', sans-serif;
            }

            .second_line table td.right_part .blue {
                padding-top: 7px;
                color: #1976d2;
                font-weight: bold;
                font-size: 12px;
            }

            .page_content {
                padding: 17px 20px 0 20px;
                text-align: center;
            }

            .page_content .right_part table td:nth-child(1) {
                width: 80px;
            }

            .third_line {
                width: 100%;
                padding-top: 18px;
            }

            .invoice-items table {
                border-collapse: collapse;
                width: 100%;
            }

            .invoice-items table th {
                color: #000000;
                font-size: 12px;
                text-align: left;
                font-weight: bold;
                padding: 6px 8px;
                border-top: solid 1px #e0e0e0;
                border-bottom: solid 1px #e0e0e0;
                font-family: 'Helvetica', sans-serif;
            }

            .invoice-items table td {
                color: #000;
                font-size: 10px;
                text-align: left;
                font-weight: normal;
                padding: 6px 8px;
                border-bottom: solid 1px #e0e0e0;
                font-family: 'Helvetica', sans-serif;
                vertical-align: middle;
                height: 36px;
            }

            .invoice-items table th:nth-child(1) {
                text-align: left;
            }

            .invoice-items table td:nth-child(2) {
                text-align: left;
                font-style: italic;
            }

            .invoice-items table th:nth-child(1),
            .invoice-items table td:nth-child(1) {
                width: 25px;
            }

            .invoice-items table th:nth-child(3),
            .invoice-items table th:nth-child(4),
            .invoice-items table th:nth-child(5),
            .invoice-items table th:nth-child(6),
            .invoice-items table th:nth-child(7),
            .invoice-items table td:nth-child(3),
            .invoice-items table td:nth-child(5) {
                width: 80px;
                text-align: center;
            }

            .invoice-items table td:nth-child(4),
            .invoice-items table td:nth-child(6),
            .invoice-items table td:nth-child(7) {
                width: 80px;
                text-align: right;
                padding-right: 16px;
            }

            .invoice-items table td:nth-child(7) {
                font-weight: bold;
            }

            .voiceDetailsPage .invoice-items table th:nth-child(3),
            .voiceDetailsPage .invoice-items table th:nth-child(4),
            .voiceDetailsPage .invoice-items table th:nth-child(5),
            .voiceDetailsPage .invoice-items table th:nth-child(6),
            .voiceDetailsPage .invoice-items table td:nth-child(3),
            .voiceDetailsPage .invoice-items table td:nth-child(4),
            .voiceDetailsPage .invoice-items table td:nth-child(5) {
                width: 120px;
                text-align: center;
            }

            .voiceDetailsPage .invoice-items table td:nth-child(6) {
                text-align: right;
                padding-right: 32px;
            }

            .invoice_total {
                padding: 20px 16px 20px 0;
            }

            .invoice_total .tables_holder {
                width: 33%;
                float: right;
            }

            .invoice_total .tables_holder table {
                border-collapse: collapse;
                width: 100%;
            }

            .invoice_total .tables_holder table tr td {
                padding-top: 5px;
                font-family: 'Helvetica', sans-serif;
            }

            .invoice_total .tables_holder table tr td:nth-child(1) {
                font-size: 10px;
                line-height: 10px;
                color: #000;
                border-collapse: collapse;
                width: 50%;
            }

            .invoice_total .tables_holder table tr td:nth-child(2) {
                font-size: 10px;
                font-weight: bold;
                text-align: right;
                width: 50%;
            }

            .invoice_total .tables_holder table tr.blue td:nth-child(1),
            .invoice_total .tables_holder table tr.blue td {
                padding-top: 14px;
                text-transform: uppercase;
                font-weight: bold;
                color: #1976d2;
            }

            .footer {
                background: #1976d2;
                text-align: right;
                padding: 0 20px;
                line-height: 29px;
            }

            .footer .pagination {
                font-size: 12px;
                color: #fff;
            }

            .table_wrap {
                text-align: left;
                margin-bottom: 36px;
            }

            .table_wrap .heading_table {
                font-weight: bold;
                font-size: 12px;
                color: #000000;
                font-family: 'Helvetica', sans-serif;
                margin-bottom: 7px;
                padding-left: 8px;
            }

            .voice-statistics-heading {
                font-weight: bold;
                font-size: 20px;
                color: #000000;
                font-family: 'Helvetica', sans-serif;
                margin-bottom: 20px;
                padding-left: 8px;
            }

            .table_wrap table {
                border-collapse: collapse;
                width: 100%;
            }

            .table_wrap table tr th {
                font-weight: bold;
                font-family: 'Helvetica', sans-serif;
                text-align: left;
                text-transform: none;
                padding: 6px 8px;
                border-top: solid 1px #e0e0e0;
                border-bottom: solid 1px #e0e0e0;
                font-size: 12px;
                color: #000;
            }

            .table_wrap table tr td {
                font-size: 12px;
                color: #000;
                font-family: 'Helvetica', sans-serif;
                text-align: left;
                font-weight: normal;
                border-bottom: solid 1px #e0e0e0;
                padding: 6px 8px;
            }

            .table_wrap table tr td:nth-child(1) {
                font-style: italic;
            }

            .voice table tr th:nth-child(2),
            .voice table tr th:nth-child(3),
            .voice table tr th:nth-child(4),
            .voice table tr td:nth-child(2),
            .voice table tr td:nth-child(3) {
                text-align: center;
                width: 120px;
            }

            .voice table tr td:nth-child(4) {
                font-weight: bold;
                text-align: right;
                padding-right: 36px;
                width: 120px;
            }

            .data table tr td:nth-child(2),
            .data table tr td:nth-child(3),
            .messages table tr td:nth-child(2),
            .messages table tr td:nth-child(3) {
                width: 120px;
            }

            .messages table tr td:nth-child(3),
            .data table tr td:nth-child(3) {
                font-weight: bold;
                text-align: center;
                width: 120px;
                text-align: right;
                padding-right: 36px;
            }

            .data table tr th:nth-child(2),
            .data table tr th:nth-child(3),
            .messages table tr th:nth-child(2),
            .messages table tr th:nth-child(3) {
                text-align: center;
            }

            .data table tr td:nth-child(2),
            .messages table tr td:nth-child(2) {
                text-align: center;
            }
        </style>
    </head>
    <body>
    <htmlpageheader name="firstpage">
        <div class="header">
            <table>
                <tr style="min-height: 80px">
                    <td class="logo">
                        <img style="max-height:80px;max-width:250px" src="/var/www/splynx/uploads/files/2021-07/afe8f179aabae14b">
                    </td>
                    <td class="header_name">
                        Meganet Telecomunicaciones S.A. de C.V.
                    </td>
                </tr>
            </table>
        </div>
    </htmlpageheader>
    <htmlpageheader name="secondpageheader">
        <div class="header2">
            <div class="header_name2"><span>Meganet Telecomunicaciones S.A. de C.V.</span></div>
            <div class="clear"></div>
        </div>
    </htmlpageheader>
    <div class="page_holder">
        <div class="page_content">
            <div class="first_line">
                <table style="width:100%">
                    <tr>
                        <td class="line_heading">
                            CANCELACION
                            <p>&nbsp;</p>
                            <p> DE SERVICIO </p>
                        </td>
                        <td style="width:40%" class="invoice_detais">
                            <table>
                                <tr>
                                    <td>Número:             </td>
                                    <td>{{ customer.id }}</td>
                                </tr>
                                <tr>
                                    <td>Fecha:</td>
                                    <td>{{ customer.last_online }}</td>
                                </tr>
                                <tr>
                                    <td>Contrato:</td>
                                    <td>{{ customer.id }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <div class="clear"></div>
            </div>
            <div class="decor_line"></div>

            <div class="second_line">
                <table>
                    <tr>
                        <td class="left_part">
                            <table>
                                <tr>
                                    <td class="heading">
                                        <strong>Datos de la Empresa </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="name">
                                        <strong>Meganet Telecomunicaciones S.A. de C.V.</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>AV HDA LA PURISIMA MZ3 LT 54 CASA A EX HDA SANTA INES</td>
                                </tr>
                                <tr>
                                    <td>NEXTLALPAN</td>
                                </tr>
                                <tr>
                                    <td>55790, Mexico</td>
                                </tr>

                                                                <tr>
                                        <td>
                                            INVING9378@HOTMAIL.COM
                                        </td>
                                    </tr>

                                                        </table>
                        </td>
                        <td></td>
                        <td class="right_part">
                            <table>
                                <tr>
                                    <td class="heading">
                                        <strong>Datos del Cliente</strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="name">{{ customer.name }}</td>
                                </tr>
                                                                <tr>
                                        <td>{{ customer.street_1 }}</td>
                                    </tr>
                                                                                            <tr>
                                        <td>{{ customer.zip_code }}</td>
                                    </tr>
                                                                                            <tr>
                                        <td>{{ customer.city }}</td>
                                    </tr>
                                                            <tr>
                                    <td class="blue">Total vencido:
                                        <strong>{{ customer_billing.deposit }} pesos</strong></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="left_part">

                        <p>Mediante este documento se ratifica la cancelacion del/ los servicios contratados en el contrato {{ customer.id }} </p>
                        <p>Ratifica la recepcion del equipo proporcionado en comodato al suscriptor con S/N :{{ customer_info.passport }}</p></td>
                        <p>Se recibe el equipo de forma satisfactoria y en condiciones de uso.</p></td>

                        <td></td>
                        <td class="right_part">
                        </td>
                    </tr>
                </table>
            </div>
            <div class="third_line">
                <div class="invoice-items">
                    <table>
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Descripción</th>
                            <p>---------------------------------------</p>
                            <th>Cantidad</th>
                            <th>Precio Excl.</th>
                            <th>IVA %</th>
                            <th>Total Excl.</th>
                            <th>Total Incl.</th>
                            <p>&nbsp;</p></th>
                        </tr>
                        </thead>


                                                            </table>
                </div>

              <div class="invoice_total">
                    <div class="tables_holder">
                        <table>
                                                    <tr>
                                <td>Total Exclusivo:</td>
                                <td>{{ customer_billing.deposit }} pesos</td>
                            </tr>
                            <tr>
                                <td>Impuesto Total:</td>
                                <td>0.00 pesos</td>
                            </tr>
                            <tr class="blue">
                                <td>Total:</td>
                                <td>{{ customer_billing.deposit }} pesos</td>
                            </tr>
                        </table>
                    </div>
                <div class="clear"></div>
                <p>&nbsp;</p>
                </div>
            </div>
        </div>
    </div>




















    <htmlpagefooter name="footer">
        <div class="footer">
            <div class="pagination">{PAGENO}</div>
        </div>
    </htmlpagefooter>
    </body>
    </html>
