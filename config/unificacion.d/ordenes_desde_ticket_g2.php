<?php

// G2 Tickets: «Asignar trabajo» desde un ticket crea la orden de Talento ligada al ticket (tasks.ticket_id),
// con el cliente resuelto por clients.id (tickets.customer_lead); el ticket lista sus órdenes y
// «Crear tarea» deja de copiar clients.id a client_main_information_id. Apagado = comportamiento actual.
return ['ordenes_desde_ticket_g2' => (bool) env('UNIFICACION_ORDENES_DESDE_TICKET_G2', false)];
