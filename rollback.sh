#!/bin/bash
set -euo pipefail

echo "=== Rollback EGAM - $(date) ==="

RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

PROJECT_DIR="/home/ubuntu/sites/egam/drupal-files"
EMAIL="gustavI0@proton.me"

cd "$PROJECT_DIR"

# Lister les derniers commits
echo -e "${YELLOW}Derniers commits:${NC}"
git log --oneline -10

echo ""
read -p "Hash du commit vers lequel revenir: " COMMIT_HASH

if [ -z "$COMMIT_HASH" ]; then
    echo -e "${RED}Aucun commit spécifié. Annulation.${NC}"
    exit 1
fi

# Avant le changement de commit : un index.php différent peut dépendre de fichiers générés par composer install.
echo -e "${YELLOW}🔧 Mise en mode maintenance...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush state:set system.maintenance_mode 1

echo -e "${YELLOW}🔄 Retour au commit $COMMIT_HASH...${NC}"
git checkout "$COMMIT_HASH"

echo -e "${YELLOW}📦 Réinstallation des dépendances...${NC}"
docker exec -u www-data egam_drupal composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}🗄️  Mise à jour de la base de données...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush updatedb -y

echo -e "${YELLOW}📝 Import de la configuration...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush config:import -y

echo -e "${YELLOW}🧹 Nettoyage du cache...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush cache:rebuild

echo -e "${YELLOW}🔓 Désactivation du mode maintenance...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush state:set system.maintenance_mode 0

echo -e "${RED}⚠️  Rollback terminé!${NC}"

echo "Rollback EGAM effectué
Commit: $COMMIT_HASH
Date: $(date)
" | mail -s "⚠️ Rollback EGAM effectué" "$EMAIL"
