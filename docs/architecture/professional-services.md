# Professional Services Architecture

## Purpose

A service is a concrete offer made by a professional to clients. It is distinct from a Skill, which describes what the professional knows.

## Ownership

Every service belongs to exactly one ProfessionalProfile. The authenticated professional determines the owner; client input never supplies professional_profile_id.

## Lifecycle

DRAFT -> PUBLISHED -> UNPUBLISHED
                 -> ARCHIVED

Publishing is an explicit domain action and requires an active category, coherent pricing, active associated skills, and a cover image.

## Images

A service can have up to eight images. Exactly one cover is maintained when images exist. The first image becomes the cover automatically.

## Public visibility

The public API exposes only PUBLISHED services whose category remains active.

## Historical integrity

Future requests, quotes, and orders must snapshot the relevant service information at transaction time. Mutable service records must not rewrite historical transactions.
