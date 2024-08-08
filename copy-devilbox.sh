#!/bin/bash

# Define the files and directories to zip
FILES_TO_COPY="Block Controller etc External Gateway Helper Logger Model Plugin Setup Test view CHANGELOG.md LICENSE README.md registration.php composer.json setup-compile.sh"

DESTINATION="../devilbox/data/www/magento/magento2/app/code/Xendit/M2Invoice"

# Copy files to the destination directory
cp -r $FILES_TO_COPY $DESTINATION || mkdir -p $DESTINATION && cp -r $FILES_TO_COPY $DESTINATION

# Check if the copy was successful
if [ $? -eq 0 ]; then
    echo "Files copied successfully, please run 'bash setup-compile.sh' to compile the module"
else
    echo "Failed to copy files"
    proxit 1
fi