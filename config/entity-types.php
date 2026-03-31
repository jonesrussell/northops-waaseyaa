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
        fieldDefinitions: [
            'label' => ['label' => 'Title', 'type' => 'string'],
            'stage' => ['label' => 'Stage', 'type' => 'string'],
            'company_name' => ['label' => 'Company', 'type' => 'string'],
            'contact_name' => ['label' => 'Contact Name', 'type' => 'string'],
            'contact_email' => ['label' => 'Contact Email', 'type' => 'string'],
            'contact_phone' => ['label' => 'Contact Phone', 'type' => 'string'],
            'source' => ['label' => 'Source', 'type' => 'string'],
            'source_url' => ['label' => 'Source URL', 'type' => 'string'],
            'sector' => ['label' => 'Sector', 'type' => 'string'],
            'value' => ['label' => 'Deal Value', 'type' => 'number'],
            'finder_fee_percent' => ['label' => 'Finder Fee %', 'type' => 'number'],
            'closing_date' => ['label' => 'Closing Date', 'type' => 'string'],
            'assigned_to' => ['label' => 'Assigned To', 'type' => 'string'],
            'qualify_rating' => ['label' => 'AI Rating', 'type' => 'number'],
            'qualify_confidence' => ['label' => 'AI Confidence', 'type' => 'number'],
            'qualify_notes' => ['label' => 'AI Notes', 'type' => 'text'],
            'qualify_keywords' => ['label' => 'Keywords', 'type' => 'string'],
            'recommended_brand' => ['label' => 'Recommended Brand', 'type' => 'string'],
            'draft_email_subject' => ['label' => 'Draft Email Subject', 'type' => 'string'],
            'draft_email_body' => ['label' => 'Draft Email Body', 'type' => 'text'],
            'draft_pdf_markdown' => ['label' => 'Draft PDF', 'type' => 'text'],
            'created_at' => ['label' => 'Created', 'type' => 'string'],
            'updated_at' => ['label' => 'Updated', 'type' => 'string'],
            'stage_changed_at' => ['label' => 'Stage Changed', 'type' => 'string'],
            'budget_range' => ['label' => 'Budget Range', 'type' => 'string'],
            'urgency' => ['label' => 'Urgency', 'type' => 'string'],
            'tier' => ['label' => 'Tier', 'type' => 'string'],
            'organization_type' => ['label' => 'Organization Type', 'type' => 'string'],
            'funding_status' => ['label' => 'Funding Status', 'type' => 'string'],
            'routing_confidence' => ['label' => 'Routing Confidence', 'type' => 'number'],
            'lead_source' => ['label' => 'Lead Source', 'type' => 'string'],
            'last_scored_at' => ['label' => 'Last Scored', 'type' => 'string'],
            'specialist_context' => ['label' => 'Specialist Context', 'type' => 'text'],
        ],
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
