#!/bin/bash
#
# Installa il tema "xampp-v2" in phpMyAdmin.
#
# Il tema non riscrive phpMyAdmin: parte dal tema predefinito pmahomme e vi
# aggiunge in coda il foglio di stile della dashboard. Un aggiornamento di
# phpMyAdmin puo' sovrascrivere la cartella dei temi: in quel caso basta
# rieseguire questo script.
#
# Uso:   sudo bash install.sh [percorso-phpmyadmin]
#
set -euo pipefail

SOURCE="$(cd "$(dirname "$0")" && pwd)"
THEME_NAME="xampp-v2"

# Percorso di phpMyAdmin: argomento esplicito, altrimenti si prova a dedurlo
if [ $# -ge 1 ]; then
    PMA="$1"
else
    for candidate in \
        "/Applications/XAMPP/xamppfiles/phpmyadmin" \
        "/opt/lampp/phpmyadmin" \
        "/opt/lampp/phpMyAdmin" \
        "C:/xampp/phpMyAdmin"
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

# 1. Copia del tema di base, che fornisce icone e struttura
rm -rf "$DEST"
cp -R "$BASE" "$DEST"

# 2. Identita' del nuovo tema
cp "$SOURCE/$THEME_NAME/theme.json" "$DEST/theme.json"

# 3. Il foglio della dashboard viene aggiunto in coda, cosi' vince sulle regole
#    del tema di base senza doverlo modificare.
cat "$SOURCE/$THEME_NAME/css/overrides.css" >> "$DEST/css/theme.css"

# 4. Permessi di lettura per il server
chmod -R a+rX "$DEST"

echo
echo "Tema installato."
echo "Per attivarlo aggiungi a $PMA/config.inc.php:"
echo
echo "    \$cfg['ThemeDefault'] = '$THEME_NAME';"
echo
echo "oppure scegli \"$THEME_NAME\" dal selettore dei temi nella home di phpMyAdmin."
