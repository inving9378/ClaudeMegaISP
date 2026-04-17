<html lang="en">

<head>
    <meta charset="utf-8">
    <title></title>
    <style>
        @page {
            margin: 120px 20px 10px;
        }

        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 100%;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
        }

        td,
        th,
        tr {
            word-wrap: break-word;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px !important;
        }

        td {
            padding: 2px;
        }

        body {
            margin: 10px;
            padding: 10px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .ml-2 {
            margin-left: 5px;
        }

        .m-auto {
            margin: 0 auto;
        }

        .p5 {
            padding: 5px;
        }

        .p3 {
            padding: 3px;
        }

        .cuadrado {
            width: 50px;
            height: 50px;
            border: 1px solid;
            color: #F00;
        }

        .bt0 {
            border-top: 0;
        }

        .bb0 {
            border-bottom: 0;
        }

        .br0 {
            border-right: 0;
        }

        .bl0 {
            border-left: 0;
        }

        .lhs {
            line-height: 0.5
        }

        .tj {
            text-align: justify;
            text-justify: inter-word;
            font-size: 8px !important;
        }

        .tab1 {
            padding-left: 4em;
        }

        .tab2 {
            padding-left: 8em;
        }

        ol.d {
            list-style-type: lower-alpha !important;
        }

        .va-t {
            vertical-align: top;
        }

        .AROJAS_Y_NEGRITAS {
            color: #F00;
            font-weight: bold;
        }

        .tj p {
            margin: 5px 0;
        }
    </style>
</head>

<body>

    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.image_src}/images/logo_meganet_oficial.png" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">
                        <h4>MEGANETT</h4>
                        <h4>MEGANET TELECOMUNICACIONES S.A DE C.V.</h4>
                        <h4>MTE1709083F3</h4>
                        <h4>AV HACIENDA LA PURISIMA MZ 3 LT 54 CASA A, EX HACIENDA SANTA INES,</h4>
                        <h4>NEXTLALPAN, ESTADO DE MEXICO, C.P. 55796</h4>
                        <h4>ATENCION A CLIENTES: 5542106277</h4>
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="3" align="center">
                <div class="">SUSCRIPTOR:${data.id}</div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div class="">
                    Nombre
                </div>
            </td>
            <td align="center">
                <div class="">
                    Apellido Paterno
                </div>
            </td>
            <td align="center">
                <div class="">
                    Apellido Materno
                </div>
            </td>
        </tr>
        <tr>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="nombre">
                    ${data.name}
                </div>
            </td>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="apellido-materno">${data.father_last_name}

                </div>
            </td>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="apellido-materno">${data.mother_last_name}
                </div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="7" align="center">
                <div class="">DOMICILIO</div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div class="">
                    Calle
                </div>
            </td>
            <td align="center">
                <div class="">
                    #Ext
                </div>
            </td>
            <td align="center">
                <div class="">
                    #Int
                </div>
            </td>
            <td align="center">
                <div class="">
                    Colonia
                </div>
            </td>
            <td align="center">
                <div class="">
                    Alcaldía/Municipio
                </div>
            </td>
            <td align="center">
                <div class="">
                    Estado
                </div>
            </td>
            <td align="center">
                <div class="">
                    C.P.
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 25%; border-bottom: 0" align="center">
                <div class="calle">
                    ${data.street}
                </div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="ext">
                    ${data.external_number}
                </div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="int">
                    ${data.internal_number}
                </div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="colonia">
                    ${data.colony}
                </div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="alcaldia-municipio">
                    ${data.municipality}
                </div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="estado">
                    ${data.state}
                </div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="cp">
                    ${data.zip}
                </div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="width: 25%">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0">TELÉFONO</td>
                        <td style="border: 0; width: 68%">
                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0">
                                        <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="border: 0; text-align: center">Fijo</td>
                                                <td align="center" style="width: 30%">x</td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td style="border: 0">
                                        <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="border: 0; text-align: center">Móvil</td>
                                                <td align="center" style="width: 30%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 25%"></td>
            <td style="width: 8.33%" align="center">
                <div class=" content">
                    RFC
                </div>
            </td>
            <td style="width: 25%">${data.nif_pasaport}</td>
            <td style="width: 16.66%"></td>
        </tr>
    </table>

    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="3" align="center">
                <div class="">SERVICIOS CONTRATADOS</div>
            </td>
        </tr>
        <tr>
            <td align="center" class="bb0" style="width: 33.33%;">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0; text-align: right; padding-right: 5px; width: 60%">TELEFONÍA</td>
                        <td align="center" style="width: 10%" class="marcar"></td>
                        <td style="width: 30%; border: 0"></td>
                    </tr>
                </table>
            </td>
            <td align="center" class="bb0" style="width: 33.33%;">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0; text-align: right; padding-right: 5px; width: 60%">INTERNET</td>
                        <td align="center" style="width: 10%" class="marcar"></td>
                        <td style="width: 30%; border: 0"></td>
                    </tr>
                </table>
            </td>
            <td align="center" class="bb0" style="width: 33.33%;">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0; text-align: right; width: 70%; padding-right: 5px">TELEFONÍA E INTERNET
                        </td>
                        <td align="center" style="width: 10%" class="marcar"></td>
                        <td style="width: 20%; border: 0"></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="bb0" align="center" style="width: 40%;" rowspan="2">
                <div class="text-center ">
                    DESCRIPCIÓN PAQUETE/OFERTA <br>(INCISO I) Nom numeral 5.1.2.1)
                </div>
            </td>
            <td align="center" style="width: 30%;">
                <div class="text-center ">TARIFA</div>
            </td>
            <td align="center" style="width: 20%;">
                <div class="text-center ">FECHA DE PAGO</div>
            </td>
            <td align="center" style="width: 10%;">
                <div class="text-center "></div>
            </td>
        </tr>
        <tr>
            <td class="bb0">
                <div class="">FOLIO IFT:${data.ift}</div>
            </td>
            <td class="bb0" align="center">
                <div class="">
                    <p>Modalidad Mensualidades fijas <br>
                        POR ADELANTADO <br>
                        (RECURRENTE) </p>
                </div>
            </td>
            <td class="bb0">
                <div class=""></div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td class="bt0 bb0" style="width: 40%;">ANCHO DE BANDA ____MB</td>
            <td style="width: 15%;">
                <div class="">Total Mensualidad</div>
            </td>
            <td style="width: 7.5%;">
                <div class="">$ customer . mrr_total</div>
            </td>
            <td style="width: 7.5%;">
                <div class="">M.N</div>
            </td>
            <td style="width: 20%;" align="center">
                <div>VIGENCIA DEL CONTRATO</div>
            </td>
            <td style="width: 10%;" align="center">
                <div class=" text-center">12 Meses Renovacion automatica</div>
            </td>
        </tr>
        <tr>
            <td class="bt0 bb0" style="width: 40%;"> ${data.ift} Inciso C, D</td>
            <td style="width: 15%;">
                <div class="">Aplica Tarifa por Reconexión:
                    <table border="0" cellspacing="0" cellpadding="0" width="100%">
                        <tr>
                            <td style="border: 0">
                                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td style="border: 0; text-align: center">Si</td>
                                        <td align="center" style="width: 30%">x</td>
                                    </tr>
                                </table>
                            </td>
                            <td style="border: 0">
                                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                    <tr>
                                        <td style="border: 0; text-align: center">No</td>
                                        <td align="center" style="width: 30%"></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 7.5%;">
                <div class="99">$99</div>
            </td>
            <td style="width: 7.5%;">
                <div class="">M.N</div>
            </td>
            <td style="width: 20%;" align="center">
                <div>PENALIDAD</div>
            </td>
            <td style="width: 10%;" align="center">
                <div class=" text-center">$299 mas meses restantes al contrato</div>
            </td>
        </tr>
        <tr>
            <td colspan="6" align="center">
                <div class="">En el Estado de cuenta y/o factura se podrá visualizar la fecha de corte del
                    servicio y fecha
                    de pago.
                </div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="bb0" style="width: 49%" align="center">
                EQUIPO ENTREGADO EN: COMODATO
            </td>
            <td class="bb0 bt0" style="width: 2%"></td>
            <td class="bb0" style="width: 49%" align="center">
                INSTALACIÓN DEL EQUIPO
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="bb0" style="width: 24.5%">
                <div class="">
                    <strong>Módem</strong>
                </div>
            </td>
            <td class="bb0" style="width: 24.5%"></td>
            <td class="bb0 bt0" style="width: 2%"></td>
            <td class="bb0" style="width: 24.5%">
                <strong>Domicilio Instalación:</strong>
            </td>
            <td class="bb0" style="width: 24.5%">${data.street}
                ${data.external_number}
                ${data.internal_number} ${data.zip}
                ${data.municipality}
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="bb0" style="width: 24.5%">
                <div class="">
                    <strong>Marca:</strong>
                </div>
            </td>
            <td class="bb0" style="width: 24.5%"></td>
            <td class="bb0 bt0" style="width: 2%"></td>
            <td class="bb0" style="width: 8.5%">
                <strong>Fecha:</strong>
            </td>
            <td class="bb0" style="width: 16%"><strong> ${data.now}</strong></td>
            <td class="bb0" style="width: 8.5%">
                <strong>Hora:</strong>
            </td>
            <td class="bb0" style="width: 16%"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="bb0" style="width: 24.5%">
                <div class="">
                    <strong>Modelo:</strong>
                </div>
            </td>
            <td class="bb0 modelo" style="width: 24.5%"></td>
            <td class="bb0 bt0" style="width: 2%"></td>
            <td class="bb0" style="width: 8.5%">
                <strong>Costo de activacion:</strong>
            </td>
            <td class="bb0" style="width: 40.5%">
                <strong>$299</strong>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td class="" style="width: 24.5%">
                <div class="">
                    <strong>Número de Serie:</strong>
                </div>
            </td>
            <td class="" style="width: 24.5%">
                <div class="">

                </div>
            </td>
            <td class="bb0 bt0" style="width: 2%"></td>
            <td class="" style="width: 49%" rowspan="2">
                <div>Para poder utilizar el Servicio se requiere la instalación del Equipo por parte de MEGANETT
                    en el domicilio del SUSCRIPTOR para ACTIVAR el servicio y empezar a prestar el
                    servicio en un plazo que no exceda de 10 días hábiles posteriores a la firma del contrato.<span
                        class="AROJAS_Y_NEGRITAS"> EL PAGO A TECNICO DEBERA SER EN MONTO EXACTO, YA QUE ELLOS NO PORTAN
                        CAMBIO.</span></div>
            </td>
        </tr>
        <tr>
            <td class="" style="width: 24.5%">
                <div class="">
                    <strong>Garantía de cumplimiento de obligación:</strong>
                </div>
            </td>
            <td class="" style="width: 24.5%">
                <div class="">
                    Pagaré para garantizar la devolución del equipo entregado en comodato.
                </div>
            </td>
            <td class="bb0 bt0" style="width: 2%"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="2" align="center">
                <div class="">MÉTODO DE PAGO</div>
            </td>
        </tr>
        <tr>
            <td class="bb0 bt0">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td align="center" style="width: 10%" class="marcar">x</td>
                        <td style="border: 0; text-align: left; width: 90%">
                            <strong> &nbsp;&nbsp;Efectivo:
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 70%"><strong>Datos para el método de pago elegido.</strong></td>
        </tr>
        <tr>
            <td class="bb0 bt0">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td align="center" style="width: 10%" class="marcar"></td>
                        <td style="border: 0; text-align: left; width: 90%">
                            <strong> &nbsp;&nbsp;Domiciliado con Tarjeta:</strong>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="datos" rowspan="4">
                <p>Cuenta: 65-50637569-5 </p>
                <p>
                    Clave interbancaria: 014180655063756953</p>
                <p>
                    Tarjeta: 5579 0890 0178 8689 </p>
                <p>
                    Banco Santander
                    Enviar Whatsapp 55-25-71-67-18
                </p>
            </td>
        </tr>
        <tr>
            <td class="bb0 bt0">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td align="center" style="width: 10%" class="marcar">&nbsp;</td>
                        <td style="border: 0; text-align: left; width: 90%">
                            <strong> &nbsp;&nbsp;Transferencia Bancaria:</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="bb0 bt0">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td align="center" style="width: 10%" class="marcar">&nbsp;</td>
                        <td style="border: 0; text-align: left; width: 90%">
                            <strong> &nbsp;&nbsp;Depósito a cuenta Bancaria:</strong>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="bt0"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="2" align="center">
                <div class="">AUTORIZACIÓN PARA CARGO DE TARJETA DE CRÉDITO O DÉBITO</div>
            </td>
        </tr>
        <tr>
            <td height="40" colspan="2" align="center" class="bb0">
                <div>Por medio de la presente SI <span class="ml-2 cuadrado p5">&nbsp;&nbsp;</span> NO <span
                        class="ml-2 cuadrado p5">&nbsp;&nbsp;&nbsp;</span> autorizo a "El PROVEEDOR", para que cargue a
                    mi
                    tarjeta de
                    crédito o débito, la cantidad por concepto de servicios que mensualmente me presta. La vigencia de
                    los
                    cargos será por ______ meses.
                </div>
                <br>
                <div class="text-center"><span class="cuadrado">Firma____________</span></div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="width: 10%">Banco:</td>
            <td style="width: 40%"></td>
            <td style="width: 15%">Número de Tarjeta:</td>
            <td style="width: 35%"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="4" align="center">
                <div>SERVICIOS ADICIONALES</div>
            </td>
        </tr>
        <tr>
            <td class="bb0" style="width: 5%">1.-</td>
            <td class="bb0" style="width: 45%"></td>
            <td class="bb0" style="width: 5%">2.-</td>
            <td class="bb0" style="width: 45%"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" height="10">
        <tr>
            <td class="bb0" style="width: 30%">DESCRIPCIÓN</td>
            <td class="bb0" style="width: 10%">COSTO:</td>
            <td class="bb0" style="width: 10%"></td>
            <td class="bb0" style="width: 30%">DESCRIPCIÓN</td>
            <td class="bb0" style="width: 10%">COSTO:</td>
            <td class="bb0" style="width: 10%">MovieNet <span>Usuario: </span> <span>Contraseña:</span>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                Usuario:<p>Contraseña:
            </td>
            <td colspan="3" height="10">
                <div>
                    peliculas.meganett.com.mx host:192.168.105.2 puerto:8096
                </div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="4" align="center">
                <div>CONCEPTOS FACTURABLES <br> (Ejemplo: Costo por cambio de domicilio, Costos administrativos
                    adicionales)
                </div>
            </td>
        </tr>
        <tr>
            <td class="bb0" style="width: 5%">1.-</td>
            <td class="bb0" style="width: 45%"></td>
            <td class="bb0" style="width: 5%">2.-</td>
            <td class="bb0" style="width: 45%"></td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td class="bb0" style="width: 30%">DESCRIPCIÓN</td>
            <td class="bb0" style="width: 10%">COSTO:</td>
            <td class="bb0" style="width: 10%"></td>
            <td class="bb0" style="width: 30%">DESCRIPCIÓN</td>
            <td class="bb0" style="width: 10%">COSTO:</td>
            <td class="bb0" style="width: 10%"></td>
        </tr>
    </table>

    <div style="page-break-after:always;"></div>
    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.image_src}/images/logo_meganet_oficial.png" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">
                        <h4>MEGANETT</h4>
                        <h4>MEGANET TELECOMUNICACIONES S.A DE C.V.</h4>
                        <h4>MTE1709083F3</h4>
                        <h4>AV HACIENDA LA PURISIMA MZ 3 LT 54 CASA A, EX HACIENDA SANTA INES,</h4>
                        <h4>NEXTLALPAN, ESTADO DE MEXICO, C.P. 55796</h4>
                        <h4>ATENCION A CLIENTES: 5542106277</h4>
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="6" align="center">EL SUSCRIPTOR AUTORIZA SE LE ENVÍE POR CORREO ELECTRÓNICO:</td>
        </tr>
        <tr>
            <td class="bb0">Factura</td>
            <td class="bb0">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">SI</td>
                                    <td align="center" style="width: 30%">x</td>
                                </tr>
                            </table>
                        </td>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">NO</td>
                                    <td align="center" style="width: 30%"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="bb0">Carta de Derechos Mínimos
                <Minimos></Minimos>
            </td>
            <td class="bb0">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">SI</td>
                                    <td align="center" style="width: 30%">x</td>
                                </tr>
                            </table>
                        </td>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">NO</td>
                                    <td align="center" style="width: 30%"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="bb0">Contrato de Adhesión</td>
            <td class="bb0">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">SI</td>
                                    <td align="center" style="width: 30%">x</td>
                                </tr>
                            </table>
                        </td>
                        <td style="border: 0">
                            <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0; text-align: center">NO</td>
                                    <td align="center" style="width: 30%"></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="width: 25%" align="center">CORREO ELECTRÓNICO AUTORIZADO:</td>
            <td style="width: 25%">${data.email}</td>
            <td align="center" class="cuadrado" style="width: 25%">FIRMA SUSCRIPTOR:</td>
            <td style="width: 25%"></td>
        </tr>
    </table>

    <br>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td align="center">AUTORIZACIÓN PARA USO DE INFORMACIÓN DEL SUSCRIPTOR</td>
        </tr>
        <tr>
            <td>
                <div class="p3">
                    1. El Suscriptor SI <span class="ml-2 cuadrado p5">&nbsp;x&nbsp;</span> NO <span
                    class="ml-2 cuadrado p5">&nbsp;&nbsp;&nbsp;</span> autoriza que su información sea cedida o
                    transmitida por el proveedor a terceros con fines mercadotécnicos o publicitarios.
                    <br>
                    <br>
                    <span class="cuadrado p3"><strong>FIRMA</strong>_________</span>
                </div>
                <br>
                <br>
                <div class="p3">
                    1. El suscriptor acepta SI <span class="ml-2 cuadrado p5">&nbsp;x&nbsp;</span> NO <span
                        class="ml-2 cuadrado p5">&nbsp;&nbsp;&nbsp;</span> recibir llamadas del proveedor de
                    promociones de
                    servicios o paquetes.
                    <br>
                    <br>
                    <span class="cuadrado p3"><strong>FIRMA</strong>_________
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <br>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td align="center" colspan="3">MEDIOS DE CONTACTO DEL PROVEEDOR PARA QUEJAS, ACLARACIONES, CONSULTAS Y
                CANCELACIONES
            </td>
        </tr>
        <tr>
            <td style="width: 20%">TELÉFONO:</td>
            <td style="width: 40%">5542106277</td>
            <td style="width: 40%">Disponible Lunes a Sábado de 9:00am-9:00pm</td>
        </tr>
        <tr>
            <td style="width: 30%">CORREO ELECTRÓNICO:</td>
            <td style="width: 30%">soporte@meganett.com.mx</td>
            <td style="width: 40%">Disponible Lunes a Domingo las 24 horas del día</td>
        </tr>
        <tr>
            <td>CENTROS DE ATENCIÓN A CLIENTES:</td>
            <td colspan="2">Consultar horarios y días disponibles, y centros de atención a clientes disponibles en
                la página
                de internet www.meganett.com.mx, en la llamada existe una grabadora indicando el 95% de las soluciones
                para su servicio, de lo contrario comunicarse en horario de oficina para que un asesor le genere tiket y
                se le otorgue seguimiento
            </td>
        </tr>
    </table>

    <br>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td align="center" colspan="2">LA PRESENTE CARÁTULA Y EL CONTRATO DE ADHESIÓN SE ENCUENTRAN DISPONIBLES
                EN:
            </td>
        </tr>
        <tr>
            <td>1. La página del proveedor</td>
            <td>www.meganet.com.mx</td>
        </tr>
        <tr>
            <td>2. Buró comercial de PROFECO</td>
            <td>https://burocomercial.profeco.gob.mx/</td>
        </tr>
        <tr>
            <td>3. Físicamente en los centros de atención del proveedor</td>
            <td>Consultar centros de atención a clientes en www.meganett.com.mx</td>
        </tr>
    </table>

    <br>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td class="bb0">LA PRESENTE CARÁTULA SE RIGE CONFORME A LAS CLÁUSULAS DEL CONTRATO DE ADHESIÓN
                REGISTRADO EN
                PROFECO EL 22/06/2021, CON NÚMERO: 205-2021 DISPONIBLE EN EL SIGUIENTE CÓDIGO:
            </td>
        </tr>
        <tr>
            <td align="center" class="bb0 bt0" style="height: 50px">
                <img src="${data.image_src}/images/qr_meganet.jpg" alt="" height="50px">
            </td>
        </tr>
        <tr>
            <td class="bt0">LAS FIRMAS INSERTADAS ABAJO SON LA ACEPTACIÓN DE LA PRESENTE CARÁTULA Y CLAUSULADO DEL
                CONTRATO
                CON NÚMERO ${data.id}_.
            </td>
        </tr>
    </table>

    <br>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0 !important;" align="center" colspan="2">
                Este contrato se firmó por duplicado en la Ciudad de
                ${data.municipality}_, a
                _____ de __________ de __________ .
            </td>
        </tr>
        <tr>
            <td style="border: 0; width: 50%" align="center">
                <div style="text-align: center;">
                    <img src="${data.image_src}/images/firma_meganet.jpg" alt="" height="30px"
                        style="">
                </div>
                <div style="text-align: center;">_________________________
                    <br>PROVEEDOR
                </div>
            </td>
            <td style="border: 0; width: 50%" align="center">
                <div style="text-align: center"><br><br>_________________________
                    <br>
                    <strong class="bb0"><span class="cuadrado">SUSCRIPTOR</span></strong>
                </div>
            </td>
        </tr>
    </table>


    <br>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0" class="tj">
                <strong>
                    ANEXO 1 DEL CONTRATO DE PRESTACIÓN DE SERVICIOS DE INTERNET FIJO EN CASA Y TELEFONÍA FIJA -EL
                    "CONTRATO"-, QUE CELEBRAN POR UNA PARTE MEGANET TELECOMUNICACIONES S.A DE C.V., A QUIEN EN LO
                    SUCESIVO
                    SE LE DENOMINARÁ "EL MEGANETT", REPRESENTADA EN ESTE ACTO POR SU APODERADO LEGAL, Y POR LA OTRA
                    PARTE LA
                    PERSONA CUYO NOMBRE Y DIRECCIÓN QUEDAN ASENTADOS EN LA CARÁTULA DEL CONTRATO, A QUIEN EN LO SUCESIVO
                    SE
                    LE DENOMINARÁ "EL SUSCRIPTOR".
                </strong>
            </td>
        </tr>
    </table>

    <br>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="width: 4%" class="bl0 bt0 bb0"></td>
            <td style=" width: 92%;">
                <table border="1" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td class="bb0" colspan="2">PAGARÉ</td>
                    </tr>
                    <tr>
                        <td class="bt0 bb0" colspan="2" align="right">
                            BUENO POR $3,000.00 (Tres Mil pesos 00/100 M.N.)
                        </td>
                    </tr>
                    <tr>
                        <td class="bt0 bb0" colspan="2" align="right">
                            Ciudad de ${data.municipality} a ______ de _____________ de
                            2024
                        </td>
                    </tr>
                    <tr>
                        <td class="bt0 bb0" colspan="2">
                            <br><br>
                            Por el presente pagaré reconozco deber y me obligo a pagar incondicionalmente en esta Ciudad
                            a Meganet
                            Telecomunicaciones S.A de C.V la cantidad de $3,000.00 (Tres Mil pesos 00/100 M.N.) por cada
                            equipo que se
                            haya entregado y no haya sido devuelto, una vez terminada la relación contractual del
                            presente contrato que
                            fue celebrada con fecha <strong> ${data.now}</strong> _.
                        </td>
                    </tr>
                    <tr>
                        <td class="bt0 bb0" colspan="2">
                            <br>
                            (Este pagaré no podré cobrarse de manera autónoma a las establecidas en el presente contrato
                            de adhesion)
                        </td>
                    </tr>
                    <tr>
                        <td class="br0 bt0 bb0">
                            <br>
                            Nombre: ${data.name}
                            ${data.father_last_name}
                            ${data.mother_last_name}
                        </td>
                        <td class="bt0 bl0 bb0">
                            <br>
                            <span class="cuadrado">ACEPTO</span>: ____________________________________
                        </td>
                    </tr>
                    <tr>
                        <td class="bt0 bb0" colspan="2">
                            <br>
                            Domicilio: ${data.street}
                            ${data.external_number}
                            ${data.internal_number}
                            ${data.colony}${data.municipality} ${data.state}
                            ${data.zip}${data.estado}
                        </td>
                    </tr>
                    <tr>
                        <td class="br0 bt0">
                            <br>
                            <span class="cuadrado">Firma</span>: ____________________________________
                        </td>
                        <td class="bt0 bl0">
                            <br>
                            Lugar y fecha: _${data.municipality}
                            <strong> ${data.now} </strong>_
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 4%" class="br0 bt0 bb0"></td>
        </tr>
    </table>


    <div style="page-break-after:always;"></div>
    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.image_src}/images/logo_meganet_oficial.png" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">
                        <h4>MEGANETT</h4>
                        <h4>MEGANET TELECOMUNICACIONES S.A DE C.V.</h4>
                        <h4>MTE1709083F3</h4>
                        <h4>AV HACIENDA LA PURISIMA MZ 3 LT 54 CASA A, EX HACIENDA SANTA INES,</h4>
                        <h4>NEXTLALPAN, ESTADO DE MEXICO, C.P. 55796</h4>
                        <h4>ATENCION A CLIENTES: 5542106277</h4>
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0" class="tj">
                <strong>CONTRATO DE PRESTACIÓN DE SERVICIO DE INTERNET EN EL ESQUEMA DE INTERNET FIJO EN CASA Y/O
                    TELEFONÍA FIJA CELEBRADO POR UNA
                    PARTE
                    ENTRE MEGANET TELECOMUNICACIONES, S.A. DE C.V., EN LO SUCESIVO “MEGANETT” Y POR OTRA PARTE, LA
                    PERSONA
                    CUYO
                    NOMBRE Y DIRECCIÓN QUEDAN ASENTADOS EN LA CARÁTULA DEL PRESENTE DOCUMENTO, A QUIEN EN LO SUCESIVO SE
                    LE
                    DENOMINARÁ "SUSCRIPTOR", AL TENOR DE LO SIGUIENTE.</strong>
            </td>
        </tr>
    </table>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0" align="center">DECLARACIONES</td>
        </tr>
    </table>
    <div>
        <ol>
            <li>Ambas partes declaran:
                <ol class="d" style="margin-top: 2px;">
                    <li>Que los datos consistentes en el domicilio, RFC y datos de localización del domicilio son
                        ciertos y se
                        encuentran establecidos en la carátula del presente contrato.
                    </li>
                    <li>Que tienen pleno goce de sus derechos y capacidad legal para contratar y obligarse en
                        términos del
                        presente
                        contrato.
                    </li>
                    <li>Que aceptan que el presente contrato se regirá por la Ley Federal de Protección al
                        Consumidor, Ley
                        Federal de
                        Telecomunicaciones y Radiodifusión, la Norma Oficial Mexicana NOM-184-SCFI-2018, Elementos
                        Normativos y
                        Obligaciones Específicas que deben Observar los Proveedores para la Comercialización y/o
                        Prestación de
                        los
                        Servicios de Telecomunicaciones cuando Utilicen una Red Pública de Telecomunicaciones, y
                        demás
                        normatividad
                        aplicable, por lo que los derechos y obligaciones establecidas en dicho marco normativo se
                        tendrán por
                        aquí
                        reproducidas como si a la letra se insertase.
                    </li>
                    <li>Que la manifestación de la voluntad para adherirse al presente contrato de adhesión y su
                        carátula (la
                        cual
                        forma parte integrante del referido contrato) son las firmas que plasmen las partes en la
                        carátula.
                    </li>
                    <li>Que al momento de que el SUSCRIPTOR active y utilice el Servicio, se obligan a lo
                        establecido en las
                        siguientes:
                    </li>
                </ol>
            </li>
        </ol>
    </div>
    <br>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0" align="center">CLÁUSULAS</td>
        </tr>
        <tr>
            <td style="border: 0">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0; width: 48%" class="tj">
                            <p><strong>PRIMERA: OBJETO DEL CONTRATO.</strong> MEGANETT se obliga a prestar el servicio
                                de
                                Telefonía fija e Internet fijo en casa, (en adelante el Servicio) ya sea individual o
                                conjunta, de manera continua, uniforme, regular y eficiente, a cambio del pago de la
                                tarifa,
                                plan o paquete que el SUSCRIPTOR haya seleccionado en la carátula del presente contrato,
                                los
                                cuales no podrán ser menores a los índices y parámetros de calidad que establezca el
                                Instituto Federal de Telecomunicaciones (en adelante IFT), ni menores a los ofrecidos
                                implícitamente o contratados en el presente instrumento.</p>
                            <p>El presente contrato <strong>se regirá bajo el esquema de mensualidades fijas POR
                                    ADELANTADO</strong>, es decir se va a pagar el servicio de manera previa a
                                utilizarlo.
                                Cualquier cargo por el SERVICIO comienza a partir de la fecha en la que efectivamente
                                MEGANETT inicie la prestación del SERVICIO.</p>
                            <p>MEGANETT es el único responsable frente al SUSCRIPTOR por la prestación del SERVICIO, así
                                como, de los bienes o servicios adicionales contratados.</p>
                            <p>Todo lo pactado o contratado entre el SUSCRIPTOR y MEGANETT de manera verbal o
                                electrónica se
                                le debe confirmar por escrito al SUSCRIPTOR a través del medio que él elija, en un plazo
                                máximo de cinco días hábiles, contados a partir del momento en que se realice el pacto o
                                contratación.</p>
                            <p><strong>SEGUNDA: VIGENCIA.</strong> Este contrato <strong>NO obliga a un plazo</strong>
                                forzoso, por lo que al tener una vigencia indeterminada el SUSCRIPTOR puede darlo por
                                terminado en cualquier momento, <strong>SIN penalidad alguna</strong> y sin necesidad de
                                recabar autorización de MEGANETT, únicamente se tendrá que dar aviso a este último a
                                través
                                del mismo medio en el cual contrató el servicio o por los medios de contacto señalados
                                en la
                                carátula.</p>
                            <p><strong>TERCERA: EQUIPO TERMINAL.</strong> Los equipos y accesorios que son necesarios
                                para
                                recibir el SERVICIO son propiedad de MEGANETT mismos que se entregan al SUSCRIPTOR en
                                <strong>COMODATO</strong> (en préstamo). El SUSCRIPTOR se compromete a la guarda,
                                custodia y
                                conservación del (los) equipo(s), durante todo el tiempo que se encuentre en su poder,
                                hasta
                                la terminación del presente contrato y deberán ser devueltos al MEGANETT, presentando
                                únicamente el desgaste natural por el paso del tiempo, y por su parte el MEGANETT se
                                obliga
                                a dar mantenimiento a los equipos y accesorios para la adecuada prestación del SERVICIO.
                            </p>
                            <p>Cuando las fallas que se presenten en el equipo y accesorios no sean atribuibles al
                                SUSCRIPTOR, MEGANETT se obliga a realizar de manera gratuita las reparaciones
                                necesarias, en
                                tanto este contrato permanezca vigente. Ambas partes deberán coordinarse para establecer
                                la
                                fecha y hora en que se llevarán a cabo dichas actividades. El personal designado por
                                MEGANETT se debe de identificar y mostrar al SUSCRIPTOR la orden de trabajo expedida por
                                MEGANETT.</p>
                            <p>En caso de que el equipo terminal se encuentre en reparación o mantenimiento, MEGANETT
                                debe
                                suspender el cobro del SERVICIO por el periodo que dure la revisión, reparación y/o
                                mantenimiento de dicho equipo terminal, excepto cuando el MEGANETT acredite que el
                                SUSCRIPTOR está haciendo uso del servicio o le haya proporcionado un equipo sustituto.
                            </p>
                            <p>Cuando el Equipo provisto en comodato, sea robado o sea objeto de algún siniestro, el
                                SUSCRIPTOR deberá dar aviso inmediato al MEGANETT, en un plazo que no excederá de
                                veinticuatro horas posteriores al evento para la reposición del Equipo y para suspender
                                el
                                cobro del SERVICIO hasta que el SUSCRIPTOR tenga otro equipo para poder recibir el
                                SERVICIO.
                                El SUSCRIPTOR tendrá un plazo de 30 días hábiles posteriores al robo o siniestro para
                                presentar copia certificada de la constancia correspondiente levantada ante una
                                Autoridad
                                Competente, que acredite el objeto de robo o siniestro para que no tenga costo la
                                reposición
                                del equipo.</p>
                            <p>Si al término o rescisión del Contrato, EL SUSCRIPTOR no devuelve al MEGANETT el Equipo
                                que
                                le fue entregado en comodato en términos de lo previsto en este Contrato, se le hará
                                efectiva la garantía de cumplimiento de obligación consistente en un pagaré que es
                                causal y
                                no negociable; es decir que este pagaré sólo se firma por EL SUSCRIPTOR para garantizar
                                la
                                devolución del Equipo que le fue entregado en comodato.</p>
                            <p>Sí al finalizar la relación contractual y el SUSCRIPTOR sí haya devuelto a MEGANETT el
                                equipo
                                que le fue entregado en comodato, MEGANETT tiene la obligación de devolver el pagaré
                                establecido en el Anexo 1 del presente contrato al SUSCRIPTOR.</p>

                        </td>
                        <td style="border: 0; width: 4%"></td>
                        <td style="border: 0; width: 48%" class="tj va-t">
                            <p>En caso de terminación, rescisión o cancelación del presente Contrato, EL SUSCRIPTOR se
                                obliga a devolver o entregar el Equipo a EL MEGANETT a más tardar dentro del plazo de 10
                                (diez) días naturales contados a partir de la fecha en que notifique la terminación del
                                Contrato.</p>
                            <p>En el momento en el que, el SUSCRIPTOR realice la devolución del Equipo, MEGANETT le debe
                                proporcionar una nota de recepción, la cual deberá contener el número de teléfono,
                                nombre de
                                EL SUSCRIPTOR y nombre de la persona que lo entrega y lo recibe. EL MEGANETT se obliga a
                                devolver el pagaré suscrito en garantía del equipo establecido en el Anexo 1 al término
                                del
                                contrato siempre y cuando EL SUSCRIPTOR entregue el Equipo. En el supuesto que EL
                                SUSCRIPTOR
                                no devuelva el equipo a la terminación del contrato, EL MEGANETT podrá hacer valer el
                                pagaré
                                establecido en el Anexo 1.</p>
                            <p><strong>CUARTA: ENTREGA E INSTALACIÓN.</strong> La entrega e instalación del equipo
                                terminal
                                no podrá ser mayor a 10 días hábiles a partir de la firma del presente contrato.</p>
                            <p>En caso de que MEGANETT no pueda iniciar la prestación del servicio por causas
                                atribuibles a
                                él por imposibilidad física o técnica para la instalación del equipo, debe devolver al
                                SUSCRIPTOR las cantidades que haya pagado por concepto de anticipo, en un plazo no mayor
                                de
                                30 días hábiles siguientes a la fecha límite establecida para la instalación, y se
                                tendrá
                                por terminado el contrato de adhesión sin responsabilidad para el SUSCRIPTOR debiendo
                                pagar
                                a MEGANETT, una penalidad equivalente al 20% de las cantidades que haya recibido por
                                concepto de anticipo, por su incumplimiento en los casos atribuibles a él.</p>
                            <p>El SUSCRIPTOR puede negarse, sin responsabilidad alguna para él, a la instalación o
                                activación del servicio ante la negativa del personal de MEGANETT a identificarse y/o a
                                mostrar la orden de trabajo. Situación que debe informar a MEGANETT en ese momento.</p>
                            <p><strong>QUINTA: TARIFAS.</strong> Las tarifas del servicio se encuentran inscritas en el
                                Registro Público de Concesiones del IFT y pueden ser consultadas en la página del IFT <a
                                    href="www.ift.org.mx">www.ift.org.mx</a>.</p>
                            <p>Las tarifas no podrán establecer condiciones contractuales tales como causas de
                                terminación
                                anticipada o cualquier otra condición que deba ser pactada dentro de los contratos de
                                adhesión. De igual manera, no se podrán establecer términos y/o condiciones de
                                aplicación de
                                las tarifas que contravengan a lo establecido en el presente contrato de adhesión.</p>
                            <p>Los planes, paquetes, cobertura donde el MEGANETT puede prestar el servicio y tarifas se
                                pueden consultar por los medios establecidos en la carátula del presente contrato.</p>
                            <p><strong>SEXTA: SERVICIOS ADICIONALES.</strong> MEGANETT puede ofrecer servicios
                                adicionales
                                al SERVICIO originalmente contratado siempre y cuando sea acordado entre las partes y el
                                SUSCRIPTOR lo solicite y autorice a través de medios físicos, electrónicos, digitales o
                                de
                                cualquier otra nueva tecnología que lo permita.</p>
                            <p>El MEGANETT deberá contar con la opción de ofrecer al SUSCRIPTOR cada servicio adicional
                                o producto
                                por
                                separado, debiendo dar a conocer el precio previamente a su contratación.</p>
                            <p>MEGANETT puede ofrecer planes o paquetes que incluyan los servicios y/o productos que
                                considere
                                convenientes, siempre y cuando tenga el consentimiento expreso del SUSCRIPTOR para tal
                                efecto. Sin
                                embargo, no puede obligar al SUSCRIPTOR a contratar servicios adicionales como requisito
                                para la
                                contratación o continuación de la prestación del SERVICIO.</p>
                            <p>El SUSCRIPTOR puede cancelar los servicios adicionales al SERVICIO originalmente
                                contratado en
                                cualquier
                                momento, por los medios señalados en la carátula para tales efectos, para lo que el
                                MEGANETT tiene
                                un
                                plazo máximo de 5 días naturales a partir de dicha manifestación para cancelarlo, sin
                                que ello
                                implique
                                la suspensión o cancelación de la prestación del SERVICIO originalmente contratado. La
                                cancelación
                                de
                                los Servicios adicionales al SERVICIO originalmente contratado no exime al SUSCRIPTOR
                                del pago de
                                las
                                cantidades adeudadas por los servicios adicionales utilizados.</p>
                            <p><strong>SÉPTIMA: ESTADO DE CUENTA RECIBO Y/O FACTURA.</strong> El MEGANETT debe entregar
                                gratuitamente en
                                el correo del SUSCRIPTOR, con al menos 10 días naturales antes de la fecha de
                                vencimiento del plazo
                                para
                                el pago del SERVICIO contratado, un estado de cuenta, recibo y/o factura el cual deberá
                                de contener
                                de
                                manera desglosada la descripción de los cargos, costos, conceptos y naturaleza del
                                SERVICIO y de los
                                servicios adicionales contratados.</p>

                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    <div style="page-break-after:always;"></div>
    <br>
    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.image_src}/images/logo_meganet_oficial.png" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">
                        <h4>MEGANETT</h4>
                        <h4>MEGANET TELECOMUNICACIONES S.A DE C.V.</h4>
                        <h4>MTE1709083F3</h4>
                        <h4>AV HACIENDA LA PURISIMA MZ 3 LT 54 CASA A, EX HACIENDA SANTA INES,</h4>
                        <h4>NEXTLALPAN, ESTADO DE MEXICO, C.P. 55796</h4>
                        <h4>ATENCION A CLIENTES: 5542106277</h4>
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <table border="0" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="border: 0; width: 48%" class="tj va-t">
                <p>El SUSCRIPTOR puede pactar con el MEGANETT para que, en sustitución de la obligación
                    referida, pueda
                    consultarse el citado estado de cuenta y/o factura, a través de cualquier medio físico,
                    electrónico,
                    digital o de cualquier otra nueva tecnología que lo permita y que al efecto se acuerde
                    entre ambas
                    partes.</p>
                <p>La fecha, forma y lugares de pago se pueden consultar por los medios señalados en la
                    carátula del
                    presente contrato.</p>
                <p>Tratándose de cargos indebidos, MEGANETT deberá efectuar la devolución correspondiente
                    dentro de un
                    plazo
                    no mayor a los 5 días hábiles posteriores a la reclamación. Dicha devolución se
                    efectuará por el
                    mismo
                    medio en el que se realizó el cargo indebido correspondiente y se deberá bonificar el
                    20% sobre el
                    monto
                    del cargo realizado indebidamente.</p>

                <p><strong>OCTAVA: MODIFICACIONES.</strong> MEGANETT dará aviso al SUSCRIPTOR, cuando menos con 15 días
                    naturales de anticipación, de cualquier cambio en los términos y condiciones originalmente
                    contratados.
                    Dicho aviso deberá ser notificado, a través de medios físicos, electrónicos, digitales o de
                    cualquier
                    otra nueva tecnología que lo permita.</p>
                <p>En caso de que el SUSCRIPTOR no esté de acuerdo con el cambio de los términos y condiciones
                    originalmente
                    contratados, podrá optar por exigir el cumplimiento forzoso del contrato bajo las condiciones en que
                    se
                    firmó el mismo, o a solicitar la terminación del presente contrato sin penalidad alguna para el
                    SUSCRIPTOR.</p>
                <p>MEGANETT deberá obtener el consentimiento del SUSCRIPTOR a través de medios físicos o electrónicos o
                    digitales o de cualquier otra nueva tecnología que lo permita, para poder dar por terminado el
                    presente
                    contrato con la finalidad de sustituirlo por otro, o bien para la modificación de sus términos y
                    condiciones. No se requerirá dicho consentimiento cuando la modificación genere un beneficio en
                    favor
                    del SUSCRIPTOR.</p>
                <p>El SUSCRIPTOR puede cambiar de tarifa, paquete o plan, aunque sea de menor monto con el que se
                    contrató,
                    en cualquier momento, pagando en su caso los cargos adicionales que se generen asociados a este
                    cambio.</p>
                <p><strong>NOVENA: SUSPENSIÓN DEL SERVICIO.</strong> El MEGANETT podrá suspender el Servicio, previa
                    notificación por escrito al SUSCRIPTOR, si este último incurre en cualquiera de los siguientes
                    supuestos:</p>
                <p><strong>1.</strong> Por pagos parciales de la tarifa aplicable al SERVICIO.</p>
                <p><strong>2.</strong> Por falta de pago del SERVICIO después de 5 días naturales posteriores a la fecha
                    de
                    pago señalada en la carátula del presente contrato.</p>
                <p><strong>3.</strong> Por utilizar el servicio de manera contraria a lo previsto en el contrato y/o a
                    las
                    disposiciones aplicables en materia de telecomunicaciones.</p>
                <p><strong>4.</strong> Por alterar, modificar o mover el equipo terminal.</p>
                <p><strong>5.</strong> Por declaración judicial o administrativa.</p>
                <p>Una vez solucionada la causa que originó la suspensión del servicio, el MEGANETT deberá reanudar la
                    prestación del servicio en un periodo máximo de 48 horas, debiendo pagar el SUSCRIPTOR los gastos
                    por
                    reconexión, lo cual no podrá ser superior al 20% del pago de una mensualidad.</p>
                <p><strong>DÉCIMA: CONTINUIDAD DEL SERVICIO Y BONIFICACIONES POR INTERRUPCIÓN.</strong> El MEGANETT
                    deberá
                    bonificar y compensar al suscriptor en los siguientes casos:</p>
                <p><strong>1.</strong> Cuando <strong>por causas atribuibles a el MEGANETT</strong> no se preste el
                    servicio
                    de telecomunicaciones en la forma y términos convenidos, contratados, ofrecidos o implícitos o
                    información desplegada en la publicidad del MEGANETT, así como con los índices y parámetros de
                    calidad
                    contratados o establecidos por el IFT, éste debe de compensar al consumidor la parte proporcional
                    del
                    precio del servicio, plan o paquete que se dejó de prestar y como bonificación al menos el 20% del
                    monto
                    del periodo de afectación de la prestación del servicio.</p>
                <p><strong>2.</strong> Cuando la interrupción del servicio sea <strong>por casos fortuitos o de fuerza
                        mayor</strong>, si la misma dura más de 72 horas consecutivas siguientes al reporte que realice
                    el
                    SUSCRIPTOR, el MEGANETT hará la compensación por la parte proporcional del periodo en que se dejó de
                    prestar el servicio contratado, la cual se verá reflejada en el siguiente recibo y/o factura.
                    Además, el
                    MEGANETT deberá bonificar por lo menos el 20% del monto del periodo de afectación.</p>

            </td>
            <td style="border: 0; width: 4%"></td>
            <td style="border: 0; width: 48%" class="tj va-t">
                <p><strong>3.</strong> Cuando se interrumpa el servicio por alguna <strong>causa previsible</strong> que
                    repercuta de manera generalizada o significativa en la prestación del servicio, la misma no podrá
                    afectar el servicio por más de 72 horas consecutivas; el MEGANETT dejará de cobrar al SUSCRIPTOR la
                    parte proporcional del precio del servicio que se dejó de prestar, y deberá bonificar por lo menos
                    el
                    20% del monto del periodo que se afectó.</p>

                <p><strong>4.</strong> Cuando el MEGANETT realice <strong>cargos indebidos</strong>, deberá bonificar el
                    20%
                    sobre el monto del cargo realizado indebidamente.</p>
                <p>A partir de que MEGANETT reciba la llamada por parte del SUSCRIPTOR para reportar las fallas y/o
                    interrupciones en el SERVICIO, MEGANETT procederá a verificar el tipo de falla y con base en ello,
                    se
                    determinará el tiempo necesario para la reparación, el cual no puede exceder las 72 horas siguientes
                    a
                    la recepción del reporte.</p>
                <p><strong>DÉCIMA PRIMERA: MECANISMOS DE BONIFICACIÓN Y COMPENSACIÓN.</strong> En caso de que proceda la
                    bonificación y/o compensación, el MEGANETT se obliga a:</p>
                <p><strong>1.</strong> Realizarlas a más tardar en la siguiente fecha de corte a partir de que se
                    actualice
                    algunos de los supuestos descritos en la cláusula anterior.</p>
                <p><strong>2.</strong> Reflejar en el siguiente estado de cuenta o factura, la bonificación y/o
                    compensación
                    realizada.</p>
                <p><strong>3.</strong> Dicha bonificación y/o compensación se efectuará por los medios que pacten las
                    partes.</p>
                <p><strong>DÉCIMA SEGUNDA: TERMINACIÓN Y CANCELACIÓN DEL CONTRATO.</strong> El presente contrato se
                    podrá
                    cancelar por cualquiera de las partes sin responsabilidad para ellas en los siguientes casos:</p>
                <p><strong>a)</strong> Por la imposibilidad permanente a MEGANETT para continuar con la prestación del
                    SERVICIO, ya sea por caso fortuito o fuerza mayor.</p>
                <p><strong>b)</strong> Si el SUSCRIPTOR no subsana en un término de 90 días naturales cualquiera de las
                    causas que dieron origen a la suspensión del SERVICIO.</p>
                <p><strong>c)</strong> Si el SUSCRIPTOR conecta aparatos adicionales por su propia cuenta, subarrienda,
                    cede
                    o en cualquier forma traspasa los derechos establecidos en el contrato, sin la autorización previa y
                    por
                    escrito a MEGANETT.</p>
                <p><strong>d)</strong> Si MEGANETT no presta el SERVICIO en la forma y términos convenidos, contratados,
                    ofrecidos o implícitos en la información desplegada en la publicidad de MEGANETT, así como con los
                    índices y parámetros de calidad contratados o establecidos por el IFT.</p>
                <p><strong>e)</strong> Si el SUSCRIPTOR proporciona información falsa al MEGANETT para la contratación
                    del
                    Servicio.</p>
                <p><strong>f)</strong> En caso de modificación unilateral de los términos, condiciones y tarifas
                    establecidas en el presente contrato por parte del MEGANETT.</p>
                <p><strong>g)</strong> Por cualquier otra causa prevista en la legislación aplicable y vigente.</p>
                <p><strong>h)</strong> El SUSCRIPTOR puede cancelar, sin el pago de penas convencionales, los servicios
                    de
                    telecomunicaciones contratados al MEGANETT cuando se haya solicitado la portabilidad del número y
                    ésta
                    no se ejecute dentro de las 24 horas, por causas no imputables al SUSCRIPTOR.</p>
                <p><strong>i)</strong> Será causa de terminación del servicio de telefonía la ejecución de la
                    portabilidad
                    numérica, los demás servicios contratados pueden continuar activos en los términos establecidos en
                    el
                    presente contrato.</p>
                <p>El SUSCRIPTOR podrá dar por terminado el contrato en cualquier momento, dando únicamente el aviso a
                    MEGANETT a través del mismo medio en el cual contrató el servicio, o a través los medios físicos o
                    electrónicos o digitales o de cualquier otra nueva tecnología que lo permita. La cancelación o
                    terminación del Contrato no exime al SUCRIPTOR de pagar a MEGANETT los adeudos generados por el/los
                    Servicio(s) efectivamente recibido(s).</p>

            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="3" align="center">
                <div class="">REVALIDACION DE DATOS</div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div class=""> Nombre</div>
            </td>
            <td align="center">
                <div class=""> Apellido Paterno</div>
            </td>
            <td align="center">
                <div class=""> Apellido Materno</div>
            </td>
        </tr>
        <tr>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="nombre"> &nbsp;</div>
            </td>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="apellido-materno"> &nbsp;</div>
            </td>
            <td align="center" style="width: 33.33%; border-bottom: 0">
                <div class="apellido-materno"> &nbsp;</div>
            </td>
        </tr>
    </table>

    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td colspan="7" align="center">
                <div class="">DOMICILIO</div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div class=""> Calle</div>
            </td>
            <td align="center">
                <div class=""> #Ext</div>
            </td>
            <td align="center">
                <div class=""> #Int</div>
            </td>
            <td align="center">
                <div class=""> Colonia</div>
            </td>
            <td align="center">
                <div class=""> Alcaldía/Municipio</div>
            </td>
            <td align="center">
                <div class=""> Estado</div>
            </td>
            <td align="center">
                <div class=""> C.P.</div>
            </td>
        </tr>
        <tr>
            <td style="width: 25%; border-bottom: 0" align="center">
                <div class="calle"> &nbsp;</div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="ext"> &nbsp;</div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="int"> &nbsp;</div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="colonia"> &nbsp;</div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="alcaldia-municipio"> &nbsp;</div>
            </td>
            <td style="width: 16.66%; border-bottom: 0" align="center">
                <div class="estado"> &nbsp;</div>
            </td>
            <td style="width: 8.33%; border-bottom: 0" align="center">
                <div class="cp"> &nbsp;</div>
            </td>
        </tr>
    </table>
    <table border="1" cellspacing="0" cellpadding="0" width="100%">
        <tr>
            <td style="width: 25%">
                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                    <tr>
                        <td style="border: 0">TELÉFONO</td>
                        <td style="border: 0; width: 68%">
                            <table border="0" cellspacing="0" cellpadding="0" width="100%">
                                <tr>
                                    <td style="border: 0">
                                        <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="border: 0; text-align: center">Fijo</td>
                                                <td align="center" style="width: 30%">&nbsp;</td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td style="border: 0">
                                        <table border="1" cellspacing="0" cellpadding="0" width="100%">
                                            <tr>
                                                <td style="border: 0; text-align: center">Móvil</td>
                                                <td align="center" style="width: 30%"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 25%"></td>
            <td style="width: 8.33%" align="center">
                <div class=" content"> RFC</div>
            </td>
            <td style="width: 25%"></td>
            <td style="width: 16.66%"></td>
        </tr>
    </table>

    <div style="page-break-after:always;"></div>
    <header>
        <table border="0" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td style="border: 0">
                    <img src="${data.image_src}/images/logo_meganet_oficial.png" alt="Logo Meganet"
                        style="max-height: 100px; margin-left:30px">
                </td>
                <td style="border: 0" class="text-right">
                    <div class="lhs">
                        <h4>MEGANETT</h4>
                        <h4>MEGANET TELECOMUNICACIONES S.A DE C.V.</h4>
                        <h4>MTE1709083F3</h4>
                        <h4>AV HACIENDA LA PURISIMA MZ 3 LT 54 CASA A, EX HACIENDA SANTA INES,</h4>
                        <h4>NEXTLALPAN, ESTADO DE MEXICO, C.P. 55796</h4>
                        <h4>ATENCION A CLIENTES: 5542106277</h4>
                    </div>
                </td>
            </tr>
        </table>
    </header>
    <table border="1" cellspacing="0" cellpadding="0" width="100%" style="border-bottom: 0">
        <tr>
            <td nowrap colspan="4" valign="bottom">
                <p align="center"><strong>DATOS DE LA RED</strong></p>
            </td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>NOMBRE DE RED:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>CONTRASEÑA:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom"></td>
            <td width="168" nowrap valign="bottom"></td>
            <td width="50" nowrap valign="bottom"></td>
            <td width="100" nowrap valign="bottom"></td>
        </tr>
        <tr>
            <td nowrap colspan="4" valign="bottom">
                <p align="center"><strong>MOVIENET</strong></p>
            </td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>HOST:</strong> </p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp; <strong>movienet.meganett.com.mx:8096</strong></td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>USUARIO MOVIENET:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>CONTRASEÑA MOVIENET: </strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>
        <tr>
            <td nowrap valign="bottom"><strong>NUMERO TELEFONICO:</strong></td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>Portal de Usuario:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;<p>
                    <strong>https://sec.meganett.com.mx/portal/login/</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>Usuario:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;<p><strong>Correo Electronico proporcionado</strong></p>
            </td>
        </tr>
        <tr>
            <td width="160" nowrap valign="bottom">
                <p><strong>Contraseña:</strong></p>
            </td>
            <td nowrap colspan="3" valign="bottom">&nbsp;</td>
        </tr>

    </table>
    <p>Por este medio autorizo al Tecnico de la empresa Meganet Telecomunicaciones S. A. de C. V. a realizar la
        perforación en pared o en el lugar de mi domicilio para que pueda ingresar la fibra óptica dentro del mismo,
        verificando con antelación que el lugar sea idóneo para dicha perforación, tomando en cuenta que la colocacion
        del modem debera ser en un punto central en el domicilio, ya que cualquier daño o desperfecto ocasionado en
        ducteria oculta, paredes bofas o sensibles será de mi entera responsabilidad.</p>
    <p>Del mismo modo acompañar al tecnico durante el tiempo de la instalacion para evitar robos o agreciones por parte
        de los vecinos de la colonia, y ofrecer el resguardo para su seguridad en la misma durante el tiempo de la
        instalacion.</p>
    <p>Contar con un equipo APTO para verificar que el servicio quede funcionando al 100%</p>
    <p align="center">ACEPTO: <span class="nombre">${data.name} </span> <span
            class="apellido-materno">${data.father_last_name}
            ${data.mother_last_name}</span></p>
    <p align="center">&nbsp;</p>
    <p align="center">_______________________________________________<br>
        NOMBRE Y FIRMA DEL TITULAR</p>

    <br>
    <p>Check List</p>
    <p>____1 Revisar Documentacion (INE y Comprobandte de domicilio ), los datos deben coincidir </p>
    <p>____2 Colocar el Modem en un lugar centrico del domicilio</p>
    <p>____3 Revisar que la altura de la fibra al acceso del domicilio sea mayor a los 4.0m de altura</p>
    <p>____4 Realizar aclaracion de lugares de pago, fechas de pago.</p>
    <p>____5 Realizar calificacion en Google</p>
    <p>____6 Tomar fotografias, Ine ambos lados, comprobante de domicilio completo, fachada modem, acceso, parte trasera
        del modem</p>
    <p>____7 Enviar fotografias y coordenadas al terminar la instalacion</p>
    <p>____8 Entregar Movienet en el dispositivo que el cliente proporcione</p>
    <p>____9 Revisar la red Club Meganet en portal cautivo</p>

</body>

</html>
