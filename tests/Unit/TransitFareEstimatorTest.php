<?php

namespace Tests\Unit;

use App\Services\TransitFareEstimator;
use PHPUnit\Framework\TestCase;

class TransitFareEstimatorTest extends TestCase
{
    private TransitFareEstimator $estimator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->estimator = new TransitFareEstimator();
    }

    public function test_it_estimates_supported_local_transport_fares(): void
    {
        $this->assertSame(1.00, $this->estimator->estimate('Rapid Bus', 'BUS', 8000));
        $this->assertSame(2.50, $this->estimator->estimate('Rapid Bus', 'BUS', 12000));
        $this->assertSame(2.70, $this->estimator->estimate('KTM Komuter', 'COMMUTER_TRAIN', 12000));
        $this->assertSame(3.20, $this->estimator->estimate('MRT Kajang', 'SUBWAY', 12000));
    }

    public function test_it_does_not_guess_unsupported_long_distance_services(): void
    {
        $this->assertNull($this->estimator->estimate('ETS Gold', 'HIGH_SPEED_TRAIN', 250000));
        $this->assertNull($this->estimator->estimate('Ferry', 'FERRY', 30000));
    }
}
