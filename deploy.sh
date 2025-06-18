#!/bin/sh
# Example deploy script
rsync -avz --exclude='.git' ./ user@yourserver:/path/to/webroot/
