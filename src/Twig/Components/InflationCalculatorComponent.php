<?php

namespace App\Twig\Components;

use App\Service\InflationService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsLiveComponent]
class InflationCalculatorComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true, format: 'Y-m-d')]
    public ?DateTime $startDate = null;

    #[LiveProp(writable: true, format: 'Y-m-d')]
    public ?DateTime $endDate = null;

    #[LiveProp(writable: false)]
    public ?array $result = null;

    #[LiveProp(writable: false)]
    public ?string $error = null;

    #[LiveProp(writable: false)]
    public array $availableMonths = [];

    public function __construct(
        private readonly InflationService $inflationService,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
        $this->availableMonths = $this->inflationService->getAvailableMonths();
    }

    #[LiveAction]
    public function calculate(): void
    {
        // Validate dates
        if (!$this->startDate || !$this->endDate) {
            $this->error = $this->translator->trans('Please select both start and end dates');
            $this->result = null;
            return;
        }

        if ($this->startDate > $this->endDate) {
            // Swap them
            [$this->startDate, $this->endDate] = [$this->endDate, $this->startDate];
        }

        // Get available date range
        $dateRange = $this->inflationService->getAvailableDateRange();
        
        if (!$dateRange['startDate'] || !$dateRange['endDate']) {
             $this->error = $this->translator->trans('Inflation data is currently unavailable. Please check API configuration.');
             $this->result = null;
             return;
        }

        $minDate = DateTime::createFromFormat('Y-m', $dateRange['startDate']);
        $maxDate = DateTime::createFromFormat('Y-m', $dateRange['endDate']);

        if (!$minDate || !$maxDate) {
             $this->error = $this->translator->trans('Invalid date format in inflation data.');
             $this->result = null;
             return;
        }

        // Check if dates are within available range
        if ($this->startDate < $minDate || $this->endDate > $maxDate) {
            $this->error = $this->translator->trans(
                'Please select dates between {startDate} and {endDate}',
                [
                    'startDate' => $minDate->format('Y-m'),
                    'endDate' => $maxDate->format('Y-m'),
                ]
            );
            $this->result = null;
            return;
        }

        try {
            $this->result = $this->inflationService->calculateInflation($this->startDate, $this->endDate);
            $this->error = null;
        } catch (\Exception $e) {
            $this->error = $this->translator->trans('An error occurred during calculation: {message}', [
                'message' => $e->getMessage(),
            ]);
            $this->result = null;
        }
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->startDate = null;
        $this->endDate = null;
        $this->result = null;
        $this->error = null;
    }
}
