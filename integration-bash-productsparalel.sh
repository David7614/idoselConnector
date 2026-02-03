#!/usr/bin/env bash
set -Eeuo pipefail

APP_NAME="generate-products-paralel"
LOG="/home/yii/sambaprod.m2itsolutions.pl/logs/integration-log-products-paralel.txt"
PHP="/usr/bin/php"
YII="/home/yii/sambaprod.m2itsolutions.pl/yii"

LOCKDIR="/home/yii/.locks"
LOCKFILE="$LOCKDIR/${APP_NAME}.lock"
mkdir -p "$LOCKDIR"

# --- BLOKADA: tylko jedna instancja ---
exec 200>"$LOCKFILE"
if ! flock -n 200; then
  printf '%s [INFO] %s już działa – wychodzę.\n' "$(date -Is)" "$APP_NAME" >>"$LOG"
  exit 0
fi
trap 'flock -u 200' EXIT

printf '%s [INFO] START pętli (%s)\n' "$(date -Is)" "$APP_NAME" >>"$LOG"

run_once() {
  # (opcjonalnie dodaj timeout, np.: timeout --foreground 30m ...)
  "$PHP" "$YII" "xml-generator/$APP_NAME" 2>&1 | tee -a "$LOG"
}

# --- pętla: startuj od razu po zakończeniu poprzedniego runu ---
while :; do
  if ! run_once; then
    rc=$?
    printf '%s [WARN] run rc=%d – retry za 5s\n' "$(date -Is)" "$rc" | tee -a "$LOG"
    sleep 5
  fi
  # krótka pauza, by nie mielić CPU gdy job kończy się błyskawicznie
  sleep 1
done
