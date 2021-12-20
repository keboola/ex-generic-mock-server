#!/bin/bash
set -e

GITHUB_TAG=${GITHUB_REF/refs\/tags\//}

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/ex-generic-mock-server quay.io/keboola/ex-generic-mock-server:$GITHUB_TAG
docker tag keboola/ex-generic-mock-server quay.io/keboola/ex-generic-mock-server:latest
docker images
docker push quay.io/keboola/ex-generic-mock-server:$GITHUB_TAG
docker push quay.io/keboola/ex-generic-mock-server:latest
