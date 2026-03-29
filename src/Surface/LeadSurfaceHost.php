<?php

declare(strict_types=1);

namespace App\Surface;

use App\Surface\Action\LeadBoardConfigAction;
use App\Surface\Action\LeadQualifyAction;
use App\Surface\Action\LeadTransitionStageAction;
use Waaseyaa\Access\EntityAccessHandler;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;
use Waaseyaa\Api\Schema\SchemaPresenter;
use Waaseyaa\Entity\EntityTypeManager;

/**
 * Custom admin surface host for the NorthOps lead pipeline.
 *
 * Registers pipeline-specific action handlers (board config, stage
 * transitions, AI qualification) on top of the generic CRUD surface.
 */
final class LeadSurfaceHost extends GenericAdminSurfaceHost
{
    public function __construct(
        EntityTypeManager $entityTypeManager,
        LeadBoardConfigAction $boardConfigAction,
        LeadTransitionStageAction $transitionAction,
        LeadQualifyAction $qualifyAction,
        ?EntityAccessHandler $accessHandler = null,
        ?SchemaPresenter $schemaPresenter = null,
    ) {
        parent::__construct(
            entityTypeManager: $entityTypeManager,
            accessHandler: $accessHandler,
            schemaPresenter: $schemaPresenter,
            tenantId: 'northops',
            tenantName: 'NorthOps',
            adminPermission: 'administer content',
        );

        $this->actions = [
            'board-config' => $boardConfigAction,
            'transition-stage' => $transitionAction,
            'qualify' => $qualifyAction,
        ];
    }
}
