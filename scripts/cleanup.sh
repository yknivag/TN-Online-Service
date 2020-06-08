#!/bin/bash

## Cleans up all uploaded and stored editions and all processed files to be a maximum of 60 days old.
## Leaves magazines for manual cleanup as there should always be the last one available no matter how old.

UPLOAD_DIR="/home/username/media.attn.org.uk/uploads"
PROCESSED_DIR="/home/username/media.attn.org.uk/uploads/_processed_files"
MEDIA_DIR="/home/username/media.attn.org.uk/httpdocs/media"

find $UPLOAD_DIR -maxdepth 1 -name "[0-9]*" -mtime +60 -exec rm -rf {} \;
find $PROCESSED_DIR -maxdepth 1 -name "[0-9]*" -mtime +60 -exec rm -rf {} \;
find $MEDIA_DIR -maxdepth 1 -name "[0-9]*" -mtime +60 -exec rm -rf {} \;
