#!/bin/bash
#
# Installs the "vxost-v2" theme into phpMyAdmin.
#
# The theme does not rewrite phpMyAdmin: it starts from the stock pmahomme
# theme and appends the dashboard stylesheet to it. A phpMyAdmin update can
# overwrite the themes folder, in which case just run this again.
#
# Uso:   sudo bash install.sh [percorso-phpmyadmin]
#
set -euo pipefail

SOURCE="$(cd "$(dirname "$0")" && pwd)"
THEME_NAME="vxost-v2"

# Percorso di phpMyAdmin: argomento esplicito, altrimenti si prova a dedurlo
if [ $# -ge 1 ]; then
    PMA="$1"
else
    for candidate in \
        "/Applications/VXOST/vxostfiles/phpmyadmin" \
        "/opt/lampp/phpmyadmin" \
        "/opt/lampp/phpMyAdmin" \
        "C:/vxost/phpMyAdmin"
    do
        if [ -d "$candidate" ]; then PMA="$candidate"; break; fi
    done
fi

if [ -z "${PMA:-}" ] || [ ! -d "$PMA/themes" ]; then
    echo "phpMyAdmin non trovato. Indica il percorso:  sudo bash install.sh /percorso/phpmyadmin"
    exit 1
fi

BASE="$PMA/themes/pmahomme"
DEST="$PMA/themes/$THEME_NAME"

if [ ! -f "$BASE/css/theme.css" ]; then
    echo "Tema di base pmahomme non trovato in $BASE"
    exit 1
fi

echo "phpMyAdmin : $PMA"
echo "Tema       : $DEST"

# 1. Copy the base theme, which brings the icons and the structure
rm -rf "$DEST"
cp -R "$BASE" "$DEST"

# 2. Identity of the new theme
cp "$SOURCE/$THEME_NAME/theme.json" "$DEST/theme.json"

# 3. The dashboard stylesheet goes last, so it wins over the base theme's
#    rules without anyone having to edit them.
cat "$SOURCE/$THEME_NAME/css/overrides.css" >> "$DEST/css/theme.css"

# 4. Read permissions for the server
chmod -R a+rX "$DEST"

echo
echo "Tema installato."
echo "Per attivarlo aggiungi a $PMA/config.inc.php:"
echo
echo "    \$cfg['ThemeDefault'] = '$THEME_NAME';"
echo
echo "oppure scegli \"$THEME_NAME\" dal selettore dei temi nella home di phpMyAdmin."
