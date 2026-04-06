<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline\EventSubscriber;

use App\Domain\Pipeline\EventSubscriber\QualificationHandlerInterface;
use App\Domain\Pipeline\EventSubscriber\SignalIngestedSubscriber;
use App\Domain\Qualification\QualifierInterface;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\Lead;
use App\Entity\LeadActivity;
use App\Entity\LeadSignal;
use App\Support\NotifierInterface;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

final class SignalIngestedSubscriberTest extends TestCase
{
    private function createSignal(): LeadSignal
    {
        return new LeadSignal([
            'id' => 1,
            'label' => 'Test Signal',
            'signal_type' => 'job_posting',
            'source' => 'remoteok',
            'source_url' => 'https://example.com/job/1',
            'external_id' => 'ro-123',
            'strength' => 70,
            'organization_name' => 'Acme Corp',
        ]);
    }

    private function createLead(): Lead
    {
        return new Lead([
            'id' => 42,
            'label' => 'Acme Corp',
            'stage' => 'lead',
            'brand_id' => 1,
        ]);
    }

    public function testNotifiesDiscordAndRecordsActivity(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('sendEmbed');

        $activityStorage = $this->createMock(EntityStorageInterface::class);
        $activityStorage->expects($this->once())->method('save')
            ->with($this->isInstanceOf(LeadActivity::class));

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')
            ->with('lead_activity')
            ->willReturn($activityStorage);

        $subscriber = new SignalIngestedSubscriber($etm, $notifier);
        $subscriber(new SignalIngestedEvent($this->createSignal(), $this->createLead()));
    }

    public function testSkipsActivityWhenNoLead(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('sendEmbed');

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->expects($this->never())->method('getStorage');

        $subscriber = new SignalIngestedSubscriber($etm, $notifier);
        $subscriber(new SignalIngestedEvent($this->createSignal(), null));
    }

    public function testAutoQualifiesLeadWhenEnabled(): void
    {
        $qualResult = [
            'rating' => 80,
            'keywords' => ['devops'],
            'sector' => 'tech',
            'summary' => 'Good fit',
            'confidence' => 0.85,
            'raw' => '{}',
            'score' => 75,
            'recommended_brand' => 'northops',
        ];

        $qualService = $this->createMock(QualifierInterface::class);
        $qualService->expects($this->once())
            ->method('qualify')
            ->willReturn($qualResult);

        $qualSubscriber = $this->createMock(QualificationHandlerInterface::class);
        $qualSubscriber->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(Lead::class), $qualResult);

        $notifier = $this->createMock(NotifierInterface::class);

        $activityStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage = $this->createMock(EntityStorageInterface::class);
        $leadStorage->expects($this->once())->method('save');

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturnMap([
            ['lead_activity', $activityStorage],
            ['lead', $leadStorage],
        ]);

        $subscriber = new SignalIngestedSubscriber(
            $etm,
            $notifier,
            $qualService,
            $qualSubscriber,
            autoQualify: true,
        );
        $subscriber(new SignalIngestedEvent($this->createSignal(), $this->createLead()));
    }

    public function testAutoQualifyDisabledSkipsQualification(): void
    {
        $qualService = $this->createMock(QualifierInterface::class);
        $qualService->expects($this->never())->method('qualify');

        $notifier = $this->createMock(NotifierInterface::class);
        $activityStorage = $this->createMock(EntityStorageInterface::class);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($activityStorage);

        $subscriber = new SignalIngestedSubscriber(
            $etm,
            $notifier,
            $qualService,
            autoQualify: false,
        );
        $subscriber(new SignalIngestedEvent($this->createSignal(), $this->createLead()));
    }

    public function testAutoQualifyFailureSendsDiscordError(): void
    {
        $qualService = $this->createMock(QualifierInterface::class);
        $qualService->method('qualify')
            ->willThrowException(new \RuntimeException('API timeout'));

        $qualSubscriber = $this->createMock(QualificationHandlerInterface::class);

        $notifier = $this->createMock(NotifierInterface::class);
        // Called twice: once for signal notification, once for error
        $notifier->expects($this->exactly(2))->method('sendEmbed');

        $activityStorage = $this->createMock(EntityStorageInterface::class);
        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($activityStorage);

        $subscriber = new SignalIngestedSubscriber(
            $etm,
            $notifier,
            $qualService,
            $qualSubscriber,
            autoQualify: true,
        );
        $subscriber(new SignalIngestedEvent($this->createSignal(), $this->createLead()));
    }
}
