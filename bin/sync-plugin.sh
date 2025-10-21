#!/bin/bash

SOURCE_DIR="${1:-docker-magento}"

if [ "$SOURCE_DIR" = "demosites" ]; then
    DEST="../public_html/app/code/Xendit/M2Invoice"
elif [ "$SOURCE_DIR" = "docker-magento" ]; then
    DEST="docker-magento/magento2/app/code/Xendit/M2Invoice"
fi

# Initial sync
rsync -av --delete --exclude='docker-magento' --exclude='.git' . $DEST/

echo "Starting file watcher for auto-sync..."
echo "Watching for changes in current directory (excluding docker-magento)"

# Watch for changes and sync automatically
fswatch -o . --exclude='docker-magento' --exclude='.git' | while read f; do
    echo "Changes detected, syncing..."
    rsync -av --delete --exclude='docker-magento' --exclude='.git' . $DEST/
    echo "Sync completed at $(date)"
done
