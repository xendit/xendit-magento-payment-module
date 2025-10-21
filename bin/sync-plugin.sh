#!/bin/bash

SOURCE_DIR="${1:-docker-magento}"
FILES_TO_COPY="Block Controller etc External Gateway Helper Logger Model Plugin Setup Test view CHANGELOG.md LICENSE README.md registration.php composer.json"

if [ "$SOURCE_DIR" = "demosites" ]; then
    DESTINATION="../public_html/app/code/Xendit/M2Invoice"

    echo "Destination: $DESTINATION"

    # Copy files to the destination directory
    cp -r $FILES_TO_COPY $DESTINATION || mkdir -p $DESTINATION && cp -r $FILES_TO_COPY $DESTINATION

    # Check if the copy was successful
    if [ $? -eq 0 ]; then
        echo "Files copied successfully, please run 'bash setup-compile.sh' under folder \"app\" to compile the module"
    else
        echo "Failed to copy files"
        exit 1
    fi

elif [ "$SOURCE_DIR" = "docker-magento" ]; then
    DEST="docker-magento/magento2/app/code/Xendit/M2Invoice"

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
fi
