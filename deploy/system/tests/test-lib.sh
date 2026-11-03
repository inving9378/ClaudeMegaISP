#!/usr/bin/env bash
# =============================================================================
# test-lib.sh — prueba de as_root (deploy/system/lib.sh) y de su-pty.py
# =============================================================================
# Sin root y sin contraseñas reales: `id`, `sudo` y `su` son simulados (shims en
# PATH) y la "página" que contesta las contraseñas es una función que escribe en
# la FIFO de la corrida, igual que lo hace ModuleSystemService::submitPassword().
#
#   bash deploy/system/tests/test-lib.sh      # exit 0 = todo OK
# =============================================================================
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB="$HERE/../lib.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
PASS=0; FAIL=0
ok()    { echo "  ✔ $*"; PASS=$((PASS+1)); }
bad()   { echo "  ✘ $*"; FAIL=$((FAIL+1)); }
check() { if eval "$1"; then ok "$2"; else bad "$2"; echo "    salida:"; sed 's/^/      /' "$T/out"; fi; }

mkdir -p "$T/bin"

cat > "$T/bin/id" <<'EOF'
#!/usr/bin/env bash
case "$1" in
  -u)  echo "${FAKE_UID:-1000}" ;;
  -un) echo "${FAKE_USER:-pepe}" ;;
  -nG) echo "${FAKE_GROUPS:-pepe}" ;;
  *)   exec /usr/bin/id "$@" ;;
esac
EOF

cat > "$T/bin/sudo" <<'EOF'
#!/usr/bin/env bash
echo "sudo $*" >> "$FAKE_LOG"
nopw=0; stdin_pw=0; validate=0; list=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    -n) nopw=1; shift ;;
    -S) stdin_pw=1; shift ;;
    -k) shift ;;
    -p) shift 2 ;;
    -v) validate=1; shift ;;
    -l) list=1; shift ;;
    --) shift; break ;;
    *)  break ;;
  esac
done
permitido() { [[ -n "${FAKE_SUDO_NOPASSWD:-}" && "$*" =~ $FAKE_SUDO_NOPASSWD ]]; }
if (( list )); then
  permitido "$@" && exit 0
  echo "sudo: a password is required" >&2; exit 1
fi
if (( nopw )); then
  if [[ "${FAKE_SUDO_DENY:-0}" == 1 ]]; then
    echo "${FAKE_USER:-pepe} is not in the sudoers file.  This incident will be reported." >&2; exit 1
  fi
  permitido "$@" && exec "$@"
  echo "sudo: a password is required" >&2; exit 1
fi
if (( stdin_pw )); then
  IFS= read -r pw || pw=""
  if [[ "${FAKE_SUDO_DENY:-0}" == 1 ]]; then
    echo "${FAKE_USER:-pepe} is not in the sudoers file.  This incident will be reported." >&2; exit 1
  fi
  if [[ "$pw" != "${FAKE_SUDO_PASSWORD:-}" ]]; then
    echo "Sorry, try again." >&2; echo "sudo: 1 incorrect password attempt" >&2; exit 1
  fi
  (( validate )) && exit 0
  exec "$@"
fi
exit 3
EOF

# su simulado: "su root -c CMD". Pide la contraseña en la terminal (pty) como el real.
cat > "$T/bin/fake-su" <<'EOF'
#!/usr/bin/env bash
echo "su $1 $2" >> "$FAKE_LOG"
if [[ "${FAKE_SU_DENY:-0}" == 1 ]]; then echo "su: Permission denied" >&2; exit 1; fi
if [[ "${FAKE_SU_NOPROMPT:-0}" == 1 ]]; then sleep 5; exit 0; fi
printf 'Password: '
[[ "${FAKE_SU_ECHO:-0}" == 1 ]] || stty -echo
IFS= read -r pw
stty echo 2>/dev/null
printf '\n'
if [[ "$pw" != "${FAKE_SU_PASSWORD:-}" ]]; then sleep 0.2; echo "su: Authentication failure" >&2; exit 1; fi
exec bash -c "$3"
EOF
chmod +x "$T/bin/"*

# responder RESPUESTA... — la "página": contesta en orden cada petición de contraseña.
responder() {
  local dir="$1"; shift
  local r actual i
  for r in "$@"; do
    for i in $(seq 1 200); do [[ -f "$dir/need_password" ]] && break; sleep 0.05; done
    [[ -f "$dir/need_password" ]] || return 1
    actual="$(cat "$dir/need_password")"
    printf '%s\n--\n' "$actual" >> "$dir/asks"
    printf '%s\n' "$r" > "$dir/password.fifo"
    for i in $(seq 1 200); do
      [[ -f "$dir/need_password" ]] || break
      [[ "$(cat "$dir/need_password" 2>/dev/null)" != "$actual" ]] && break
      sleep 0.05
    done
  done
}

# corre RESPUESTA... — ejecuta $CMD (con lib.sh cargado) contestando lo que pida.
corre() {
  rm -rf "$T/run"; mkdir -p "$T/run"; mkfifo "$T/run/password.fifo"
  : > "$T/log"; : > "$T/run/asks"
  ( export PATH="$T/bin:$PATH" FAKE_LOG="$T/log" MEGAISP_SYSTEM_RUN_DIR="$T/run" MEGAISP_SU_BIN="$T/bin/fake-su"
    set -euo pipefail
    # shellcheck source=../lib.sh
    source "$LIB"
    eval "$CMD" ) > "$T/out" 2>&1 < /dev/null &
  local pid=$!
  if (( $# > 0 )); then responder "$T/run" "$@"; fi
  wait "$pid"; RC=$?
}

pedidas()    { grep -c '^attempt=' "$T/run/asks"; }
sin_secreto(){ ! grep -qE 'clave1|raiz|mala|otra' "$T/out"; }
limpiar_env(){ unset FAKE_UID FAKE_USER FAKE_GROUPS FAKE_SUDO_NOPASSWD FAKE_SUDO_DENY FAKE_SUDO_PASSWORD \
                     FAKE_SU_PASSWORD FAKE_SU_DENY FAKE_SU_NOPROMPT FAKE_SU_ECHO MEGAISP_SYSTEM_PW_TIMEOUT \
                     MEGAISP_SU_PROMPT_TIMEOUT; export FAKE_USER=pepe; }

echo "1. Ya somos root"
limpiar_env; export FAKE_UID=0
CMD='as_root echo hola-root'; corre
check '[[ $RC -eq 0 ]] && grep -q hola-root "$T/out"' "corre directo"
check '! grep -q sudo "$T/log" && [[ $(pedidas) -eq 0 ]]' "no usa sudo ni pide contraseña"

echo "2. En el grupo sudo, sudo sin contraseña"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_NOPASSWD='.*'
CMD='as_root echo sin-clave; as_root echo otra-vez'; corre
check '[[ $RC -eq 0 ]] && grep -q sin-clave "$T/out" && grep -q otra-vez "$T/out"' "corre con sudo -n"
check '[[ $(grep -c "sudo -n true" "$T/log") -eq 1 ]] && grep -q "sudo -n -- echo sin-clave" "$T/log" && [[ $(pedidas) -eq 0 ]]' "prueba sudo -n una sola vez y no pide contraseña"
CMD='as_root bash -c "exit 3"'; corre
check '[[ $RC -eq 3 ]] && [[ $(pedidas) -eq 0 ]]' "si el comando corre y falla, devuelve su código sin pedir contraseña"

echo "2b. Fuera del grupo sudo, aunque tenga una regla sin contraseña"
limpiar_env; export FAKE_SUDO_NOPASSWD='^echo' FAKE_SU_PASSWORD=raiz
CMD='as_root echo fuera'; corre "PW raiz"
check '[[ $RC -eq 0 ]] && grep -q fuera "$T/out" && grep -q "^via=su" "$T/run/asks"' "va directo a su"
check '! grep -q "^sudo" "$T/log"' "no toca sudo (no genera incidentes)"

echo "3. En el grupo sudo, la regla pide contraseña"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_PASSWORD=clave1
CMD='as_root echo uno; as_root echo dos'; corre "PW clave1"
check '[[ $RC -eq 0 ]] && grep -q uno "$T/out" && grep -q dos "$T/out"' "corre los dos comandos"
check '[[ $(pedidas) -eq 1 ]] && grep -q "^via=sudo" "$T/run/asks" && grep -q "^user=pepe" "$T/run/asks"' "pide UNA vez la contraseña de pepe (sudo)"
check 'grep -q "sudo -n true" "$T/log"' "antes probó sudo -n"
check '! grep -q "a password is required" "$T/out"' "no ensucia la salida con el rechazo de sudo -n"
check 'sin_secreto' "la contraseña no aparece en la salida"
check '[[ ! -e "$T/run/need_password" ]]' "quita el aviso de la página al recibirla"

echo "4. En el grupo sudo, primero mal y luego bien"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_PASSWORD=clave1
CMD='as_root echo listo'; corre "PW mala" "PW clave1"
check '[[ $RC -eq 0 ]] && grep -q listo "$T/out"' "termina bien"
check '[[ $(pedidas) -eq 2 ]] && grep -q "intento 1 de 3" "$T/out"' "avisa el intento fallido y vuelve a pedir"

echo "5. En el grupo sudo, tres veces mal"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_PASSWORD=clave1
CMD='as_root echo nunca'; corre "PW mala" "PW otra" "PW mala"
check '[[ $RC -ne 0 ]] && ! grep -qx nunca "$T/out"' "falla sin correr el comando"
check '[[ $(pedidas) -eq 3 ]]' "pidió tres veces"

echo "6. En el grupo sudo pero sudoers no lo deja → su"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_DENY=1 FAKE_SU_PASSWORD=raiz
CMD='as_root echo por-su'; corre "PW clave1" "PW raiz"
check '[[ $RC -eq 0 ]] && grep -q por-su "$T/out"' "corre con su"
check '[[ $(pedidas) -eq 2 ]] && grep -q "^via=su" "$T/run/asks" && grep -q "^user=root" "$T/run/asks"' "pide primero la de pepe y luego la de root"
check 'grep -q "no lo deja" "$T/out" && sin_secreto' "explica el cambio y no muestra contraseñas"

echo "7. Fuera del grupo sudo → su directo"
limpiar_env; export FAKE_SU_PASSWORD=raiz
CMD='as_root echo a; as_root echo b'; corre "PW raiz"
check '[[ $RC -eq 0 ]] && grep -q "^a$" "$T/out" && grep -q "^b$" "$T/out"' "corre los dos comandos con su"
check '[[ $(pedidas) -eq 1 ]] && grep -q "^via=su" "$T/run/asks" && ! grep -q "sudo -S" "$T/log"' "pide solo la de root, sin intentar sudo con contraseña"

echo "8. su con contraseña mala tres veces"
limpiar_env; export FAKE_SU_PASSWORD=raiz
CMD='as_root echo nunca'; corre "PW mala" "PW otra" "PW mala"
check '[[ $RC -ne 0 ]] && [[ $(pedidas) -eq 3 ]] && grep -q "Contraseña de root incorrecta" "$T/out"' "falla tras tres intentos"

echo "9. Cancelar desde la página"
limpiar_env; export FAKE_SU_PASSWORD=raiz
CMD='as_root echo nunca'; corre "CANCEL"
check '[[ $RC -ne 0 ]] && grep -q "Cancelado" "$T/out" && ! grep -qx nunca "$T/out"' "se detiene sin correr nada"

echo "10. Nadie contesta"
limpiar_env; export FAKE_SU_PASSWORD=raiz MEGAISP_SYSTEM_PW_TIMEOUT=1
CMD='as_root echo nunca'; corre
check '[[ $RC -ne 0 ]] && grep -q "a tiempo" "$T/out" && [[ ! -e "$T/run/need_password" ]]' "falla por tiempo y quita el aviso"

echo "11. Código de salida y stdin vacío"
limpiar_env; export FAKE_SU_PASSWORD=raiz
CMD='as_root bash -c "exit 7"'; corre "PW raiz"
check '[[ $RC -eq 7 ]]' "su devuelve el código del comando (7)"
CMD='as_root cat; echo fin-su'; corre "PW raiz"
check '[[ $RC -eq 0 ]] && grep -q fin-su "$T/out"' "un comando que lee stdin no se cuelga (su)"
limpiar_env; export FAKE_GROUPS="pepe sudo" FAKE_SUDO_PASSWORD=clave1
CMD='as_root cat; echo fin-sudo'; corre "PW clave1"
check '[[ $RC -eq 0 ]] && grep -q fin-sudo "$T/out" && sin_secreto' "un comando que lee stdin no se cuelga ni recibe la contraseña (sudo)"

echo "12. Terminal con eco encendido"
limpiar_env; export FAKE_SU_PASSWORD=raiz FAKE_SU_ECHO=1
CMD='as_root echo con-eco'; corre "PW raiz"
check '[[ $RC -eq 0 ]] && grep -q con-eco "$T/out" && sin_secreto' "tapa la contraseña aunque la terminal la repita"

echo "13. su no permitido / su que nunca pide"
limpiar_env; export FAKE_SU_DENY=1
CMD='as_root echo nunca'; corre "PW raiz"
check '[[ $RC -ne 0 ]] && grep -q "su no funcionó (código 126)" "$T/out"' "su negado → error claro"
limpiar_env; export FAKE_SU_NOPROMPT=1 MEGAISP_SU_PROMPT_TIMEOUT=1
CMD='as_root echo nunca'; corre "PW raiz"
check '[[ $RC -ne 0 ]] && grep -q "código 124" "$T/out"' "su sin aviso de contraseña → error claro"

echo "14. El comando deja un proceso en segundo plano con la terminal abierta"
limpiar_env; export FAKE_SU_PASSWORD=raiz
CMD='as_root bash -c "sleep 20 & echo arrancado"'; inicio=$SECONDS; corre "PW raiz"; dur=$((SECONDS - inicio))
check '[[ $RC -eq 0 ]] && grep -q arrancado "$T/out" && (( dur < 8 ))' "su-pty no se queda esperando al proceso de fondo (${dur}s)"

echo
echo "Resultado: ${PASS} OK, ${FAIL} fallidos"
[[ $FAIL -eq 0 ]]
