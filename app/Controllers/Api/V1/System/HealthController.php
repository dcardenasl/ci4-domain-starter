<?php

namespace App\Controllers\Api\V1\System;

use App\Libraries\Monitoring\HealthChecker;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Health Check Controller
 *
 * ARCHITECTURAL DECISION: Does NOT extend ApiController
 *
 * Reason: Infrastructure endpoint requiring minimal overhead
 * - Called every 5-10 seconds by orchestrators (Kubernetes, Docker Swarm)
 * - No authentication or business logic needed
 * - Must be lightweight and fast to respond
 * - Industry standard pattern (Spring Boot Actuator, Express health checks)
 *
 * Direct instantiation of dependencies is intentional for performance.
 * This controller serves infrastructure/monitoring needs, not business API requests.
 *
 * @see CLAUDE.md "Architectural Exceptions"
 */
class HealthController extends Controller
{
    protected HealthChecker $healthChecker;

    public function __construct()
    {
        $this->healthChecker = new HealthChecker();
    }

    /**
     * Health check endpoint
     *
     * GET /health
     *
     * Returns health status of all system components
     *
     * @return ResponseInterface
     */
    public function index(): ResponseInterface
    {
        // Run all health checks
        $checks = $this->healthChecker->checkAll();

        // Determine overall status
        $overallStatus = $this->healthChecker->getOverallStatus($checks);

        // Determine HTTP status code
        $statusCode = match ($overallStatus) {
            'healthy' => 200,
            'degraded' => 200, // Still operational
            'unhealthy' => 503,
            default => 500,
        };

        $response = [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => $checks,
        ];

        return $this->response
            ->setJSON($response)
            ->setStatusCode($statusCode);
    }

    /**
     * Simple ping endpoint
     *
     * GET /ping
     *
     * Lightweight endpoint for basic availability check
     *
     * @return ResponseInterface
     */
    public function ping(): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'ok',
            'timestamp' => date('Y-m-d H:i:s'),
        ])->setStatusCode(200);
    }

    /**
     * Readiness check
     *
     * GET /ready
     *
     * Checks if application is ready to serve traffic
     *
     * @return ResponseInterface
     */
    public function ready(): ResponseInterface
    {
        // Check critical components only
        $databaseCheck = $this->healthChecker->checkDatabase();

        $isReady = ($databaseCheck['status'] ?? 'unhealthy') === 'healthy';

        $statusCode = $isReady ? 200 : 503;

        return $this->response->setJSON([
            'status' => $isReady ? 'ready' : 'not_ready',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $databaseCheck,
        ])->setStatusCode($statusCode);
    }

    /**
     * Liveness check
     *
     * GET /live
     *
     * Checks if application is alive (basic functionality)
     *
     * @return ResponseInterface
     */
    public function live(): ResponseInterface
    {
        return $this->response->setJSON([
            'status' => 'alive',
            'timestamp' => date('Y-m-d H:i:s'),
            'uptime_seconds' => (int) (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']),
        ])->setStatusCode(200);
    }
}
