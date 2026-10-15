## 2026-10-14 20:35 — Renombrar la versión V1.60-14.10.2026 → V1.59-14.10.2026 (bitácora PREVIA)

**Por qué:** la numeración saltó de V1.58 a V1.60; la V1.59 no existe en ningún lado (tags
locales, origin, GitHub Releases, tabla `releases`). Se pide renombrar la V1.60 a V1.59.

**Quién:** Claude Code, sesión en DEV (`/var/www/megaisp`), a petición del usuario de la sesión.

**Estado ANTES (respaldo para revertir):**
- GitHub Release `413272250`, tag `V1.60-14.10.2026`, publicado 2026-10-14T23:32:23Z, sin assets.
- Tag anotado `V1.60-14.10.2026` (objeto `67656b78`) → commit `ede875dc` (ya en `origin/main`,
  contiene el fix de facturación `a0ad7ebd`).
- Fila `releases.id=132` (BD dev `megaisp`): `version=V1.60-14.10.2026`,
  `github_release_id=413272250`, `estado_publicacion=publicada`.
  `release_descriptions` va por `release_id`, no por versión → no se toca.

**Comandos (en este orden):**
1. `DELETE /repos/inving9378/ClaudeMegaISP/releases/413272250` (API GitHub).
2. `git push origin :refs/tags/V1.60-14.10.2026` + `git tag -d V1.60-14.10.2026`.
3. `git tag -a V1.59-14.10.2026 -m "Release V1.59-14.10.2026" ede875dc` + `git push origin V1.59-14.10.2026`.
4. `UPDATE releases SET version='V1.59-14.10.2026' WHERE id=132` (BD dev).
5. `php artisan releases:publish-github V1.59-14.10.2026` (crea el Release nuevo y re-sella
   `github_release_id`/`published_at`).

**Riesgo conocido:** si PROD ya hubiera instalado la V1.60, la V1.59 le saldrá como "más vieja" y
no se la ofrecerá (`VersionComparator` compara major.minor). No pierde código (es el mismo commit),
solo quedaría con la etiqueta V1.60 en su tabla `releases`.

**Revertir:** repetir los pasos con los nombres al revés (tag V1.60 sobre `ede875dc`,
`releases.version` a V1.60, `releases:publish-github V1.60-14.10.2026`).

## 2026-10-14 20:33 — Resultado: V1.59-14.10.2026 publicada

- Release `413272250` (V1.60) borrado en GitHub (HTTP 204); tag `V1.60-14.10.2026` borrado en
  origin y local. `releases/tags/V1.60-14.10.2026` → 404.
- Tag anotado `V1.59-14.10.2026` (objeto `2eda73ed`) → `ede875dc`, empujado a origin.
- `releases.id=132` → `version=V1.59-14.10.2026`, `github_release_id=413360813`,
  `estado_publicacion=publicada` (sellado por `releases:publish-github`, `deployment_logs #86`).
- `releases/latest` en GitHub devuelve `V1.59-14.10.2026`.
- PROD está en V1.58 (confirmado por el usuario) → `VersionComparator::isNewer(V1.59, V1.58)=true`:
  la verá en "Buscar actualizaciones". El riesgo de "PROD ya instaló V1.60" no aplica.
- `NextVersionResolver` ahora propone `V1.60` para la siguiente versión (sin salto).
- Causa del salto original: la V1.60 se publicó a mano por CLI (`deployment_logs #85`,
  `source=releases:publish-github`), no con el botón "+ Nueva versión"; el número no salió del
  cálculo automático.

## 2026-10-14 20:45 — Mover el tag V1.59 para que incluya esta bitácora (bitácora PREVIA)

Pedido del usuario: que la bitácora viaje dentro de la V1.59. Solo agrega commits de `docs/` sobre
`ede875dc` (cero código), así que lo que instala PROD es idéntico salvo este archivo.

1. `git push origin main` (fast-forward `ede875dc` → commit que contiene esta entrada).
2. Borrar el Release `413360813` en GitHub y el tag `V1.59-14.10.2026` (origin + local).
3. `git tag -a V1.59-14.10.2026 -m "Release V1.59-14.10.2026" <HEAD de main>` + push del tag.
4. `releases.id=132` → `commit_sha` = ese HEAD.
5. `php artisan releases:publish-github V1.59-14.10.2026` (sin `--force`: el commit ya está en origin/main).

PROD sigue en V1.58 → no hay instalación a medias de la V1.59 que se rompa al mover el tag
(además PROD hace `git fetch origin --tags --force`).
