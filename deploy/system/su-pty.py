#!/usr/bin/env python3
"""
su-pty.py — corre un comando como root con `su`, dándole la contraseña de root.

`su` no acepta la contraseña por una tubería: la lee de una terminal. Este helper
abre una pseudo-terminal, arranca `su root -c <comando>` dentro, espera el aviso
de contraseña y la escribe. La salida del comando se reenvía tal cual.

Uso (lo llama deploy/system/lib.sh, no hace falta usarlo a mano):
    printf '%s\n' "$PW" | python3 su-pty.py -- CMD [ARGS...]

La contraseña es la primera línea de stdin. El comando corre con stdin vacío
(/dev/null), para que nunca se quede esperando algo que nadie va a teclear.

Código de salida: el del comando; 125 = contraseña incorrecta; 124 = su nunca
pidió la contraseña; 126 = su no se pudo ejecutar o lo negó; 2 = uso incorrecto.

Variables para pruebas: MEGAISP_SU_BIN (programa su), MEGAISP_SU_PROMPT_TIMEOUT
(segundos esperando el aviso de contraseña; 30 por defecto).
"""
import os
import pty
import re
import select
import shlex
import signal
import sys
import time

PROMPT = re.compile(rb'(password|contrase\xc3\xb1a)[^\n]*:\s*$', re.IGNORECASE)
AUTH_FAIL = re.compile(
    rb'^su: (authentication failure|incorrect password)|fall\xc3\xb3 la autenticaci',
    re.IGNORECASE | re.MULTILINE,
)


def exit_code(status):
    if os.WIFEXITED(status):
        return os.WEXITSTATUS(status)
    if os.WIFSIGNALED(status):
        return 128 + os.WTERMSIG(status)
    return 1


def main():
    args = sys.argv[1:]
    if args[:1] == ['--']:
        args = args[1:]
    if not args:
        print('su-pty: falta el comando', file=sys.stderr)
        return 2

    password = sys.stdin.readline().rstrip('\n')
    if not password:
        print('su-pty: no llegó la contraseña por stdin', file=sys.stderr)
        return 2
    secret = password.encode()

    su_bin = os.environ.get('MEGAISP_SU_BIN', 'su')
    prompt_timeout = float(os.environ.get('MEGAISP_SU_PROMPT_TIMEOUT', '30'))
    inner = 'exec </dev/null; exec ' + ' '.join(shlex.quote(a) for a in args)
    env = dict(os.environ)
    env['LC_ALL'] = 'C.UTF-8'

    pid, fd = pty.fork()
    if pid == 0:
        try:
            os.execvpe(su_bin, [su_bin, 'root', '-c', inner], env)
        except OSError as e:
            os.write(2, ('su-pty: no se pudo ejecutar %s: %s\n' % (su_bin, e)).encode())
        os._exit(126)

    out = sys.stdout.buffer
    before = b''          # lo que su imprime antes de pedir la contraseña
    after = b''           # inicio de lo impreso después (para reconocer el rechazo)
    sent = False
    deadline = time.time() + prompt_timeout
    status = None         # estado de salida de su, en cuanto termine

    while True:
        timeout = 0.5 if sent else max(0.0, min(0.5, deadline - time.time()))
        ready, _, _ = select.select([fd], [], [], timeout)
        if not ready:
            if not sent and time.time() >= deadline:
                os.kill(pid, signal.SIGKILL)
                os.waitpid(pid, 0)
                print('su-pty: su nunca pidió la contraseña', file=sys.stderr)
                return 124
            # Si el comando dejó un proceso en segundo plano con la terminal abierta, el
            # fin de archivo no llega nunca: se corta en cuanto su terminó y ya no hay salida.
            if status is None:
                done, st = os.waitpid(pid, os.WNOHANG)
                if done:
                    status = st
            if status is not None:
                break
            continue
        try:
            data = os.read(fd, 4096)
        except OSError:
            data = b''        # EIO: el proceso terminó y se cerró la terminal
        if not data:
            break

        if not sent:
            before += data
            if PROMPT.search(before):
                os.write(fd, secret + b'\n')
                sent = True
            continue

        if len(after) < 1024:
            after += data
        data = data.replace(b'\r\n', b'\n').replace(secret, b'****')
        out.write(data)
        out.flush()

    if status is None:
        _, status = os.waitpid(pid, 0)
    rc = exit_code(status)

    if not sent:
        msg = before.replace(b'\r\n', b'\n').replace(secret, b'****').decode('utf-8', 'replace').strip()
        print('su-pty: su terminó sin pedir la contraseña: %s' % (msg or '(sin salida)'), file=sys.stderr)
        return 126
    if rc != 0 and AUTH_FAIL.search(after.replace(b'\r\n', b'\n')):
        return 125
    return rc


if __name__ == '__main__':
    sys.exit(main())
