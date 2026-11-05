<?php

// I2 Vendedores/Talento: un solo criterio de venta para los dos rankings (RankingVentasService).
return ['ranking_ventas_i2' => (bool) env('UNIFICACION_RANKING_VENTAS_I2', false)];
