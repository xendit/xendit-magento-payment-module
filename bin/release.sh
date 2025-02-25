#!/bin/bash
echo "Release script started"
echo "Make sure to already follow the guideline before submit at https://xendit.atlassian.net/wiki/spaces/TPI/pages/356122802/Magento+Publish+Guide"
FILES_TO_ZIP="Block Controller etc External Gateway Helper Logger Model Plugin Setup Test view CHANGELOG.md LICENSE README.md registration.php composer.json"

# Create a zip file
ZIP_FILE="release.zip"
zip -r $ZIP_FILE $FILES_TO_ZIP

echo "Files have been zipped into $ZIP_FILE"