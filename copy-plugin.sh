#!/bin/bash

# Check if the first argument is provided
if [ -z "$1" ]; then
    echo "Please provide the source directory"
    exit 1
fi

# Assign the source directory to a variable
SOURCE_DIR="$1"

# Define the files and directories to zip
FILES_TO_COPY="Block Controller etc External Gateway Helper Logger Model Plugin Setup Test view CHANGELOG.md LICENSE README.md registration.php composer.json setup-compile.sh"

if [ "$SOURCE_DIR" = "devilbox" ]; then
    DESTINATION="../devilbox/data/www/magento/magento2/app/code/Xendit/M2Invoice"
elif [ "$SOURCE_DIR" = "demosites" ]; then
    DESTINATION="../public_html/app/code/Xendit/M2_Invoice"
else
    echo "Invalid source directory"
    exit 1
fi

echo "Destination: $DESTINATION"

# Copy files to the destination directory
cp -r $FILES_TO_COPY $DESTINATION || mkdir -p $DESTINATION && cp -r $FILES_TO_COPY $DESTINATION

# Check if the copy was successful
if [ $? -eq 0 ]; then
    echo "Files copied successfully, please run 'bash setup-compile.sh' to compile the module"
else
    echo "Failed to copy files"
    proxit 1
fi