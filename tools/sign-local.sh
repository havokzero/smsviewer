#!/bin/bash
set -euo pipefail

MODULE="${1:-smsviewer}"
KEYID="${2:-}"

if [[ -z "$KEYID" ]]; then
  echo "Usage: $0 [module_name] <gpg_key_id>"
  exit 1
fi

if [[ $EUID -ne 0 ]]; then
  echo "Run as root."
  exit 1
fi

MODULE_PATH="/var/www/html/admin/modules/${MODULE}"
SIGNER="/usr/src/devtools/sign.php"

if [[ ! -d "$MODULE_PATH" ]]; then
  echo "Module path not found: $MODULE_PATH"
  exit 1
fi

if [[ ! -f "$SIGNER" ]]; then
  echo "Missing signer: $SIGNER"
  echo "Clone devtools first: git clone https://github.com/FreePBX/devtools /usr/src/devtools"
  exit 1
fi

export GPG_TTY=$(tty)

rm -f "/etc/freepbx.secure/${MODULE}.sig"
rm -f "${MODULE_PATH}/module.sig"

php "$SIGNER" "$MODULE_PATH" --local "$KEYID"
fwconsole ma refreshsignatures
fwconsole reload
fwconsole chown

echo "Finished local signing for ${MODULE}."