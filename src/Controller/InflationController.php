<?php

namespace App\Controller;

use App\Theme\BaseTheme\DefaultLayoutController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InflationController extends DefaultLayoutController
{
    #[Route('/enflasyon-hesapla', name: 'app_inflation_calculator')]
    public function calculator(): Response
    {
        return $this->render('inflation/calculator.html.twig', [
            'title' => $this->translator->trans('Inflation Calculator'),
        ]);
    }
}
