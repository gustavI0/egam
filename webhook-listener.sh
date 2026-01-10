#!/bin/bash
# Simple webhook listener
while true; do
    if [ -f /tmp/deploy-egam.trigger ]; then
        rm /tmp/deploy-egam.trigger
        /home/ubuntu/egam/deploy.sh >> /home/ubuntu/egam/deploy.log 2>&1
    fi
    sleep 10
done
