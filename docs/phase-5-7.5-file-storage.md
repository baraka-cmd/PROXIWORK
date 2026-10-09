# Phase 5.5 — File Storage

## Storage boundaries

### Public
- avatars
- service-images

Stored on the public disk.

### Private
- documents
- verification-files

Stored on the private local disk and never exposed through the public storage link.

## Security rules
- validate upload integrity;
- validate allowed extension;
- enforce size limits;
- generate server-side paths;
- never trust original filenames;
- never expose private paths directly;
- authorize every private download;
- delete replaced/deleted files;
- keep storage behind Laravel's Filesystem abstraction.

## Cloud migration

The application already has an S3-compatible disk configuration. The service uses disk names rather than filesystem paths, so migration to object storage does not require changing business logic.

## Lifecycle

When replacing a file:
1. store the new file;
2. persist the new path;
3. after successful persistence, delete the old file;
4. if persistence fails, keep the old file.

## Result

A dedicated storage service centralizes secure public/private storage boundaries, validation, generated names and deletion. Feature tests cover public/private isolation and rejection of invalid/oversized files.
