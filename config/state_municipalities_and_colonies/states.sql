-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 12-02-2024 a las 05:59:12
-- Versión del servidor: 10.4.28-MariaDB
-- Versión de PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `meganetpro`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `states`
--

ALTER TABLE `states`
  MODIFY COLUMN `id` bigint(20) UNSIGNED NOT NULL,
  MODIFY COLUMN `name` varchar(255) DEFAULT NULL,
  MODIFY COLUMN `created_at` timestamp NULL DEFAULT NULL,
  MODIFY COLUMN `updated_at` timestamp NULL DEFAULT NULL;

--
-- Volcado de datos para la tabla `states`
--

INSERT INTO `states` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'Ciudad de México', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(2, 'Aguascalientes', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(3, 'Baja California', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(4, 'Baja California Sur', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(5, 'Campeche', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(6, 'Coahuila de Zaragoza', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(7, 'Colima', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(8, 'Chiapas', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(9, 'Chihuahua', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(10, 'Durango', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(11, 'Guanajuato', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(12, 'Guerrero', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(13, 'Hidalgo', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(14, 'Jalisco', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(15, 'México', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(16, 'Michoacán de Ocampo', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(17, 'Morelos', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(18, 'Nayarit', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(19, 'Nuevo León', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(20, 'Oaxaca', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(21, 'Puebla', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(22, 'Querétaro', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(23, 'Quintana Roo', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(24, 'San Luis Potosí', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(25, 'Sinaloa', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(26, 'Sonora', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(27, 'Tabasco', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(28, 'Tamaulipas', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(29, 'Tlaxcala', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(30, 'Veracruz de Ignacio de la Llave', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(31, 'Yucatán', '2024-02-11 14:24:56', '2024-02-11 14:24:56'),
(32, 'Zacatecas', '2024-02-11 14:24:56', '2024-02-11 14:24:56');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `states`
--

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `states`
--
ALTER TABLE `states`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
