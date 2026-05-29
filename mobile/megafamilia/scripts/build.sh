#!/usr/bin/env bash
# build.sh — auto-incrementa build number, compila APK release y copia al
# directorio de descargas del servidor.
#
# Uso:
#   ./scripts/build.sh                   # incrementa patch + build
#   ./scripts/build.sh --bump minor      # incrementa minor, resetea patch
#   ./scripts/build.sh --bump major      # incrementa major, resetea minor+patch
#   ./scripts/build.sh --no-bump         # compila sin cambiar versión
#
# Requiere: flutter en PATH, acceso de escritura a DEPLOY_DIR.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PUBSPEC="$PROJECT_DIR/pubspec.yaml"
CONFIG_DART="$PROJECT_DIR/lib/config.dart"
DEPLOY_DIR="/var/www/megaisp/public/downloads"

# --------------------------------------------------------------------------
# Leer versión actual de pubspec.yaml  (línea: version: X.Y.Z+N)
# --------------------------------------------------------------------------
current_full=$(grep '^version:' "$PUBSPEC" | awk '{print $2}')
current_semver="${current_full%%+*}"    # "0.3.1"
current_build="${current_full##*+}"     # "4"

IFS='.' read -r V_MAJOR V_MINOR V_PATCH <<< "$current_semver"

# --------------------------------------------------------------------------
# Parsear argumentos
# --------------------------------------------------------------------------
BUMP_TYPE="patch"   # default
NO_BUMP=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --bump) BUMP_TYPE="$2"; shift 2 ;;
        --no-bump) NO_BUMP=true; shift ;;
        *) echo "Argumento desconocido: $1" >&2; exit 1 ;;
    esac
done

# --------------------------------------------------------------------------
# Calcular nueva versión
# --------------------------------------------------------------------------
if [[ "$NO_BUMP" == false ]]; then
    NEW_BUILD=$(( current_build + 1 ))
    case "$BUMP_TYPE" in
        major)
            V_MAJOR=$(( V_MAJOR + 1 )); V_MINOR=0; V_PATCH=0 ;;
        minor)
            V_MINOR=$(( V_MINOR + 1 )); V_PATCH=0 ;;
        patch)
            V_PATCH=$(( V_PATCH + 1 )) ;;
        *)
            echo "BUMP_TYPE inválido: $BUMP_TYPE (major|minor|patch)" >&2; exit 1 ;;
    esac
else
    NEW_BUILD="$current_build"
fi

NEW_SEMVER="${V_MAJOR}.${V_MINOR}.${V_PATCH}"
NEW_FULL="${NEW_SEMVER}+${NEW_BUILD}"

echo "────────────────────────────────────────"
echo "  MegaFamilia Build Script"
echo "  ${current_full} → ${NEW_FULL}"
echo "────────────────────────────────────────"

# --------------------------------------------------------------------------
# Actualizar pubspec.yaml
# --------------------------------------------------------------------------
if [[ "$NO_BUMP" == false ]]; then
    sed -i "s/^version: .*/version: ${NEW_FULL}/" "$PUBSPEC"
    echo "[1/5] pubspec.yaml actualizado → $NEW_FULL"
else
    echo "[1/5] pubspec.yaml sin cambios (--no-bump)"
fi

# --------------------------------------------------------------------------
# Actualizar lib/config.dart (appVersion + apkDownloadUrl)
# --------------------------------------------------------------------------
APK_FILENAME="megafamilia-v$(echo "$NEW_SEMVER" | tr '.' '_').apk"
if [[ "$NO_BUMP" == false ]]; then
    sed -i "s/static const String appVersion = '.*'/static const String appVersion = '${NEW_SEMVER}'/" "$CONFIG_DART"
    sed -i "s|static const String apkDownloadUrl = '.*'|static const String apkDownloadUrl = 'http://192.168.105.11/downloads/${APK_FILENAME}'|" "$CONFIG_DART"
    echo "[2/5] config.dart actualizado → $NEW_SEMVER / $APK_FILENAME"
else
    APK_FILENAME="megafamilia-v$(echo "$current_semver" | tr '.' '_').apk"
    echo "[2/5] config.dart sin cambios (--no-bump)"
fi

# --------------------------------------------------------------------------
# Compilar APK release
# --------------------------------------------------------------------------
echo "[3/5] Compilando APK release..."
cd "$PROJECT_DIR"
flutter build apk --release --obfuscate --split-debug-info=build/debug-info 2>&1 | tail -20

APK_SRC="$PROJECT_DIR/build/app/outputs/flutter-apk/app-release.apk"
if [[ ! -f "$APK_SRC" ]]; then
    echo "ERROR: No se encontró el APK en $APK_SRC" >&2
    exit 1
fi

APK_SIZE=$(stat -c%s "$APK_SRC")
echo "[3/5] APK compilado: $(numfmt --to=iec-i "$APK_SIZE")"

# --------------------------------------------------------------------------
# Copiar a directorio de descargas del servidor
# --------------------------------------------------------------------------
mkdir -p "$DEPLOY_DIR"
cp "$APK_SRC" "$DEPLOY_DIR/$APK_FILENAME"
echo "[4/5] APK copiado → $DEPLOY_DIR/$APK_FILENAME"

# --------------------------------------------------------------------------
# Registrar versión en la tabla app_versions via artisan tinker
# --------------------------------------------------------------------------
echo "[5/5] Registrando versión en app_versions..."
php /var/www/megaisp/artisan tinker --no-interaction <<PHP
\$ver = \App\Modules\Addons\MegaFamilia\Models\AppVersion::where('platform','android')
    ->where('version_name','${NEW_SEMVER}')->first();
if (!\$ver) {
    // Desactivar versiones anteriores
    \App\Modules\Addons\MegaFamilia\Models\AppVersion::where('platform','android')
        ->update(['active' => false]);
    \App\Modules\Addons\MegaFamilia\Models\AppVersion::create([
        'platform'       => 'android',
        'version_name'   => '${NEW_SEMVER}',
        'version_code'   => ${NEW_BUILD},
        'file_path'      => 'downloads/${APK_FILENAME}',
        'file_size'      => ${APK_SIZE},
        'changelog'      => 'Build ${NEW_FULL}',
        'is_mandatory'   => false,
        'active'         => true,
        'released_at'    => now()->toDateString(),
    ]);
    echo "Versión ${NEW_FULL} registrada.\n";
} else {
    echo "Versión ${NEW_SEMVER} ya existe en app_versions.\n";
}
PHP

# --------------------------------------------------------------------------
# Resumen
# --------------------------------------------------------------------------
echo ""
echo "✔  Build completado exitosamente"
echo "   Versión:  ${NEW_FULL}"
echo "   APK:      $DEPLOY_DIR/$APK_FILENAME"
echo "   Tamaño:   $(numfmt --to=iec-i "$APK_SIZE")"
echo "   SHA-256:  $(sha256sum "$DEPLOY_DIR/$APK_FILENAME" | awk '{print $1}')"
