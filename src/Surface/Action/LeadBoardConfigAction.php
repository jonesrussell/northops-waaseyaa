<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use App\Domain\Qualification\SectorNormalizer;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;

final class LeadBoardConfigAction implements SurfaceActionHandler
{
    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        return AdminSurfaceResultData::success([
            'stages' => StageTransitionRules::STAGES,
            'transitions' => StageTransitionRules::getTransitions(),
            'sources' => StageTransitionRules::SOURCES,
            'sectors' => SectorNormalizer::SECTORS,
        ]);
    }
}
