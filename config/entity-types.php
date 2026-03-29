<?php

declare(strict_types=1);

/**
 * Application-specific entity types.
 *
 * Return an array of EntityType instances to register additional entity
 * types beyond those provided by Waaseyaa packages.
 *
 * Example:
 *   return [
 *       new \Waaseyaa\Entity\EntityType(
 *           id: 'product',
 *           label: 'Product',
 *           class: \App\Entity\Product::class,
 *           keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
 *       ),
 *   ];
 */

return [
    new \Waaseyaa\Entity\EntityType(
        id: 'contact_submission',
        label: 'Contact Submission',
        description: 'Inquiries received through the contact form',
        class: \App\Entity\ContactSubmission::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'brand',
        label: 'Brand',
        description: 'Business brands for dual-brand operation',
        class: \App\Entity\Brand::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'name'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'lead',
        label: 'Lead',
        description: 'Sales pipeline leads with qualification status',
        class: \App\Entity\Lead::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'lead_activity',
        label: 'Lead Activity',
        description: 'Activity log entries tracking lead interactions',
        class: \App\Entity\LeadActivity::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'action'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'lead_attachment',
        label: 'Lead Attachment',
        description: 'Files and documents attached to leads',
        class: \App\Entity\LeadAttachment::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'filename'],
    ),
    new \Waaseyaa\Entity\EntityType(
        id: 'outreach',
        label: 'Outreach',
        description: 'Outbound communications to prospects',
        class: \App\Entity\Outreach::class,
        keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'subject'],
    ),
];
