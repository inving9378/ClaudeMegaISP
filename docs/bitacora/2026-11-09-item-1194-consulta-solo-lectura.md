# Consulta de solo lectura: posibles afectados por el check «Añadir rol de vendedor» (#1193, #1194)

Para correr en producción por quien tenga acceso; no escribe nada. Equivale al comando
`php artisan usuarios:sospechosos-rol-vendedor`, que además revisa los logs. La corrección de
cada persona la aprueba Irving.

```sql

-- 1) Hoy solo con rol Vendedor (posibles afectados: perdieron su rol base al guardar el formulario).
SELECT u.id,
       u.login_user,
       u.estado,
       u.is_seller,
       GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ' + ') AS roles,
       tc.department AS puesto_talento,
       tc.status     AS estado_talento,
       u.updated_at
FROM users u
JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mr.role_id
LEFT JOIN talento_colaboradores tc ON tc.user_id = u.id AND tc.deleted_at IS NULL
GROUP BY u.id, u.login_user, u.estado, u.is_seller, tc.department, tc.status, u.updated_at
HAVING roles = 'Vendedor'
ORDER BY u.updated_at DESC;

-- 2) En riesgo: Vendedor + un rol con id mayor (el formulario muestra «Vendedor» y al guardar quita el otro).
SELECT u.id, u.login_user, GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ' + ') AS roles_en_orden_del_formulario
FROM users u
JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
JOIN roles r ON r.id = mr.role_id
GROUP BY u.id, u.login_user
HAVING SUM(r.name = 'Vendedor') = 1
   AND COUNT(*) > 1
   AND MIN(r.id) = (SELECT id FROM roles WHERE name = 'Vendedor');

-- 3) Historial: cambios desde el formulario donde se perdió un rol y quedó Vendedor
--    (tabla del item #9991150; si no existe en producción, esta consulta da error y se omite).
SELECT id, target_user_id, target_login, created_at, roles_antes, roles_despues, actor_login
FROM user_role_change_audits
WHERE JSON_CONTAINS(roles_despues, '"Vendedor"')
  AND JSON_LENGTH(roles_antes) > JSON_LENGTH(roles_despues)
ORDER BY created_at;

-- 4) Si no hay auditoría: buscar en storage/logs/laravel-*.log las líneas
--    «Roles actualizados desde el form de Usuarios (diff dirigido)» con "quitados".
--    Respaldo para reconstruir: el dump diario más viejo de /var/backups/mysql (tabla model_has_roles).
```
