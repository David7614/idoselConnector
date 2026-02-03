#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="generate-orders"
LOG="/home/yii/sambaprod.m2itsolutions.pl/logs/integration-orders-log.txt"
PHP="/usr/bin/php"
YII="/home/yii/sambaprod.m2itsolutions.pl/yii"

LOCKDIR="/home/yii/.locks"
LOCKFILE="$LOCKDIR/${APP_NAME}.lock"
mkdir -p "$LOCKDIR"

# --- blokada: tylko jedna instancja ---
exec 200>"$LOCKFILE"
if ! flock -n 200; then
  printf '%s [INFO] %s już działa – wychodzę.\n' "$(date -Is)" "$APP_NAME" >>"$LOG"
  exit 0
fi
trap 'flock -u 200' EXIT

printf '%s [INFO] START pętli (%s)\n' "$(date -Is)" "$APP_NAME" >>"$LOG"

run_once() {
  # (opcjonalnie ochrona przed zawiśnięciem pojedynczego runu)
  # timeout --foreground 30m \
  "$PHP" "$YII" "xml-generator/$APP_NAME" 2>&1 | tee -a "$LOG"
}

# --- pętla: odpalaj od razu po poprzednim ---
while :; do
  if ! run_once; then
    rc=$?
    printf '%s [WARN] run rc=%d – retry za 5s\n' "$(date -Is)" "$rc" | tee -a "$LOG"
    sleep 5
  fi
  # krótka pauza, by nie zjadać CPU gdy job kończy się natychmiast
  sleep 1
done
